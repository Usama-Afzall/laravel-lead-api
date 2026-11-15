# Laravel Lead API

A small, production-minded lead-capture endpoint for Laravel 11. Drop it into an app to receive website contact or quote form submissions as JSON.

It validates and normalises input, stores the lead, rate limits abusive clients, silently absorbs bot submissions through a honeypot field, ignores accidental double submits, and dispatches a `LeadCreated` event so you can plug in Slack, email or a CRM without touching the controller.

Stack: PHP 8.2+, Laravel 11, any database Laravel supports.

## Request and response

```bash
curl -X POST https://your-app.test/api/leads \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "+44 20 7946 0958",
    "message": "I would like a quote for a new website.",
    "source": "contact-form",
    "website": ""
  }'
```

Success (`201 Created`):

```json
{ "message": "Thanks, your message has been received." }
```

Validation failure (`422 Unprocessable Entity`):

```json
{
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field must be a valid email address."] }
}
```

Rate limited: `429 Too Many Requests` (Laravel's standard throttle response, with a `Retry-After` header).

| Field | Rules |
|---|---|
| `name` | required, 2 to 100 characters |
| `email` | required, RFC valid, max 254, stored lowercase |
| `phone` | optional, 7 to 20 characters: digits, spaces, `( ) . -`, optional leading `+` |
| `message` | required, 10 to 2000 characters |
| `source` | optional, letters, numbers, dash, underscore, max 50 |
| `utm_source`, `utm_medium`, `utm_campaign` | optional, max 100 |
| `website` | honeypot, must stay empty (hidden from real users) |

## Where each file goes

GitHub uploads here are flat, so place each file in your Laravel app like this:

| File in this repo | Location in your Laravel app |
|---|---|
| `routes-api.php` | Copy the route into `routes/api.php`; copy the limiter into `AppServiceProvider::boot()` |
| `LeadController.php` | `app/Http/Controllers/LeadController.php` |
| `StoreLeadRequest.php` | `app/Http/Requests/StoreLeadRequest.php` |
| `Lead.php` | `app/Models/Lead.php` |
| `LeadCreated.php` | `app/Events/LeadCreated.php` |
| `create_leads_table.php` | `database/migrations/2026_01_01_000000_create_leads_table.php` (any timestamp prefix) |
| `LeadApiTest.php` | `tests/Feature/LeadApiTest.php` |
| `README.md`, `LICENSE` | Not needed in your app |

## Install

1. Laravel 11 has no API routes by default. Run `php artisan install:api` (this also adds Sanctum, which this package does not use).
2. Copy the files into the locations above.
3. Register the `leads` rate limiter in `AppServiceProvider::boot()` using the snippet in `routes-api.php`.
4. Run `php artisan migrate`.
5. Run `php artisan test --filter=LeadApiTest`. The tests use `RefreshDatabase`; the default Laravel 11 `phpunit.xml` uses in-memory SQLite.
6. Add the hidden `website` field to your form (see below).

Honeypot field in your HTML form, hidden from people and from assistive tech:

```html
<input type="text" name="website" tabindex="-1" autocomplete="off"
       aria-hidden="true" style="position:absolute;left:-9999px">
```

## Security notes

- **CORS.** If your front end lives on another origin, run `php artisan config:publish cors` and set `paths` to `['api/*']` and `allowed_origins` to your exact site origin, for example `['https://www.example.com']`. Do not leave `*` in production if you can avoid it.
- **Rate limit.** Public forms get hammered by scripts. The `leads` limiter allows 5 requests per minute per IP and answers 429 beyond that. Behind a proxy or CDN, configure trusted proxies (`trustProxies` in `bootstrap/app.php`) so `$request->ip()` is the real client address, not the proxy.
- **Honeypot.** Many simple bots fill every field they find. A filled `website` field gets a normal-looking 201 response, nothing is stored, no event fires, and the bot learns nothing from validation errors. It costs real users nothing.
- **No CAPTCHA.** This is not a CAPTCHA and does not claim to stop targeted or human-assisted spam. If you need that, add Turnstile, reCAPTCHA or hCaptcha in front of it.
- **Output.** Messages are stored as plain text, not HTML-escaped. Always escape when rendering them (Blade `{{ }}` does this).
- **Personal data.** Leads contain names, emails and IP addresses. Decide on a retention period and privacy notice that suit your jurisdiction.

## Duplicate handling

If the same email sends the same message again within 10 minutes (double click, retry after a timeout), the API returns the same 201 response, stores nothing and fires no event. A different message from the same email is stored normally. The window is the `10` in `LeadController`.

## Adapting it

Create a listener in `app/Listeners`. Laravel 11 auto-discovers listeners that type-hint the event:

```php
namespace App\Listeners;

use App\Events\LeadCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;

class NotifySlack implements ShouldQueue
{
    public function handle(LeadCreated $event): void
    {
        Http::post(config('services.slack.leads_webhook'), [
            'text' => "New lead: {$event->lead->name} <{$event->lead->email}>",
        ]);
    }
}
```

The same pattern works for sending a Mailable or Notification to your team, or creating a contact through the HubSpot API. Implementing `ShouldQueue` keeps slow network calls out of the request (you need a queue worker running).

## Limitations

- Email validation is syntax only (`email:rfc`). It does not check DNS or whether the mailbox exists.
- The duplicate check is a query, not a database constraint, so two identical requests arriving at the same instant could both be stored.
- Rate limiting is per IP and uses your cache store. Use a shared cache (Redis, database) when running several servers.
- No authentication, admin UI or lead export. `handled_at` and the `unhandled()` scope are there for your own workflow.
- A dropped duplicate or honeypot hit returns the same success message by design, so you cannot tell them apart from the client side.
- Test status: PHP syntax was checked with `php -l`. The feature tests were written for Laravel 11 but could not be run in the environment where this was prepared (package registry unreachable), so run them in your app before relying on them.

## License

Source-available, not open source. You may read and evaluate this code, but commercial or production use requires a written licence from Usama Afzall. See `LICENSE` and https://usama.websiteprism.com/contact.

Need this customized? https://usama.websiteprism.com/contact
