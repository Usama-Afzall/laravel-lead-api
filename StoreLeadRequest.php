<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreLeadRequest extends FormRequest
{
    /** Fields that are trimmed, and turned into null when empty. */
    private const TEXT_FIELDS = [
        'name', 'email', 'phone', 'message', 'source',
        'utm_source', 'utm_medium', 'utm_campaign',
    ];

    public function authorize(): bool
    {
        return true; // Public endpoint. Abuse is handled by throttling.
    }

    /**
     * Normalise input before the rules run.
     */
    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach (self::TEXT_FIELDS as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $clean[$field] = $value === '' ? null : $value;
            }
        }

        if (isset($clean['email'])) {
            $clean['email'] = mb_strtolower($clean['email']);
        }

        foreach (['name', 'phone'] as $field) {
            if (isset($clean[$field])) {
                // Collapse runs of whitespace into a single space.
                $clean[$field] = preg_replace('/\s+/u', ' ', $clean[$field]) ?? $clean[$field];
            }
        }

        if (isset($clean['message'])) {
            // Drop control characters but keep tabs and line breaks.
            $clean['message'] = preg_replace(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                '',
                $clean['message']
            ) ?? $clean['message'];
        }

        $this->merge($clean);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            // Optional. 7 to 20 characters: digits, spaces, ( ) . - and a leading +.
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9][0-9\s().\-]{5,18}[0-9]$/'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'source' => ['nullable', 'string', 'alpha_dash:ascii', 'max:50'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            // "website" is the honeypot. It has no rule on purpose: it is
            // never validated or stored, only checked in honeypotTripped().
        ];
    }

    /**
     * True when a bot filled the hidden "website" field. Real users never
     * see this field, so any value means the submission is automated.
     */
    public function honeypotTripped(): bool
    {
        return filled($this->input('website'));
    }

    /**
     * The one success response. Bots, duplicates and real leads all get
     * the same reply, so the response reveals nothing about what happened.
     */
    public function acceptedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Thanks, your message has been received.',
        ], 201);
    }

    /**
     * Always answer with a JSON 422. A tripped honeypot gets the fake
     * success instead, so bots cannot learn from validation errors.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->honeypotTripped()) {
            throw new HttpResponseException($this->acceptedResponse());
        }

        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
