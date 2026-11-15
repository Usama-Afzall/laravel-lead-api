<?php

namespace Tests\Feature;

use App\Events\LeadCreated;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Requires the "leads" rate limiter from routes-api.php to be registered
 * in AppServiceProvider (5 requests per minute per IP).
 */
class LeadApiTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+44 20 7946 0958',
            'message' => 'I would like a quote for a new website.',
            'source' => 'contact-form',
            'website' => '', // honeypot, empty for real users
        ], $overrides);
    }

    public function test_valid_submission_returns_201_and_stores_the_lead(): void
    {
        Event::fake([LeadCreated::class]);

        $this->postJson('/api/leads', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseHas('leads', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'source' => 'contact-form',
        ]);

        Event::assertDispatched(LeadCreated::class, 1);
    }

    public function test_input_is_trimmed_and_normalised(): void
    {
        $this->postJson('/api/leads', $this->payload([
            'name' => "  Jane   Doe \n",
            'email' => '  Jane@Example.COM ',
        ]))->assertCreated();

        $this->assertDatabaseHas('leads', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_request_metadata_is_stored(): void
    {
        $this->withHeader('User-Agent', 'LeadApiTest/1.0')
            ->postJson('/api/leads', $this->payload())
            ->assertCreated();

        $lead = Lead::firstOrFail();
        $this->assertSame('LeadApiTest/1.0', $lead->user_agent);
        $this->assertNotNull($lead->ip_address);
    }

    public function test_missing_required_fields_return_422_json_errors(): void
    {
        $this->postJson('/api/leads', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['name', 'email', 'message']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_invalid_email_phone_and_long_message_are_rejected(): void
    {
        $this->postJson('/api/leads', $this->payload([
            'email' => 'not-an-email',
            'phone' => 'call me maybe',
            'message' => str_repeat('a', 2001),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone', 'message']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_phone_and_source_are_optional(): void
    {
        $this->postJson('/api/leads', $this->payload(['phone' => null, 'source' => null]))
            ->assertCreated();

        $this->assertDatabaseCount('leads', 1);
    }

    public function test_filled_honeypot_returns_201_but_stores_nothing(): void
    {
        Event::fake([LeadCreated::class]);

        $this->postJson('/api/leads', $this->payload(['website' => 'https://spam.example']))
            ->assertCreated()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('leads', 0);
        Event::assertNotDispatched(LeadCreated::class);
    }

    public function test_filled_honeypot_hides_validation_errors_from_bots(): void
    {
        $this->postJson('/api/leads', ['website' => 'https://spam.example'])
            ->assertCreated();

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_duplicate_submission_within_ten_minutes_is_stored_once(): void
    {
        Event::fake([LeadCreated::class]);

        $this->postJson('/api/leads', $this->payload())->assertCreated();
        $this->postJson('/api/leads', $this->payload())->assertCreated();

        $this->assertDatabaseCount('leads', 1);
        Event::assertDispatched(LeadCreated::class, 1);
    }

    public function test_same_email_with_a_different_message_is_stored(): void
    {
        $this->postJson('/api/leads', $this->payload())->assertCreated();
        $this->postJson('/api/leads', $this->payload([
            'message' => 'A second, different enquiry from the same person.',
        ]))->assertCreated();

        $this->assertDatabaseCount('leads', 2);
    }

    public function test_same_submission_after_ten_minutes_is_stored_again(): void
    {
        $this->postJson('/api/leads', $this->payload())->assertCreated();

        $this->travel(11)->minutes();

        $this->postJson('/api/leads', $this->payload())->assertCreated();

        $this->assertDatabaseCount('leads', 2);
    }

    public function test_rate_limit_returns_429_after_five_attempts(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/leads', $this->payload([
                'email' => "person{$i}@example.com",
            ]))->assertCreated();
        }

        $this->postJson('/api/leads', $this->payload(['email' => 'person6@example.com']))
            ->assertStatus(429);

        $this->assertDatabaseCount('leads', 5);
    }
}
