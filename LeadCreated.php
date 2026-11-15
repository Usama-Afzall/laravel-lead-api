<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a new lead has been stored.
 *
 * The package sends no email or webhook on its own. Attach listeners in
 * your app to notify Slack, send an email, push to a CRM, and so on.
 * Laravel 11 auto-discovers listeners in app/Listeners whose handle()
 * method type-hints this event. Implement ShouldQueue on a listener to
 * keep slow network calls out of the request cycle.
 */
class LeadCreated
{
    use Dispatchable;

    public function __construct(public readonly Lead $lead)
    {
    }
}
