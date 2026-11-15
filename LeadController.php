<?php

namespace App\Http\Controllers;

use App\Events\LeadCreated;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Receives a contact or quote form submission.
 *
 * Single-action controller: routes point at the class, no method name.
 */
class LeadController extends Controller
{
    public function __invoke(StoreLeadRequest $request): JsonResponse
    {
        // Honeypot: pretend it worked, store nothing, notify nobody.
        if ($request->honeypotTripped()) {
            return $request->acceptedResponse();
        }

        $data = $request->validated();

        // Duplicate guard: same email and same message within 10 minutes is
        // treated as a double click or a retry. Answer success, store nothing.
        $isDuplicate = Lead::query()
            ->recentDuplicate($data['email'], $data['message'], 10)
            ->exists();

        if ($isDuplicate) {
            return $request->acceptedResponse();
        }

        $lead = Lead::create([
            ...$data,
            'ip_address' => $request->ip(),
            // Truncated so an oversized header cannot break the insert.
            'user_agent' => Str::limit((string) $request->userAgent(), 255, '') ?: null,
        ]);

        LeadCreated::dispatch($lead);

        return $request->acceptedResponse();
    }
}
