<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TrackEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'max:255'],
            'properties' => ['sometimes', 'array'],
            'customer_id' => ['sometimes', 'nullable', 'string', 'max:255'], // For known customers
            'identity' => ['sometimes', 'array'],
            'identity.type' => ['required_with:identity', 'string', 'in:cookie,user_id,email_hash,ga_cid'],
            'identity.value' => ['required_with:identity', 'string'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'referrer' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'utm_source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'utm_medium' => ['sometimes', 'nullable', 'string', 'max:255'],
            'utm_campaign' => ['sometimes', 'nullable', 'string', 'max:255'],
            'utm_term' => ['sometimes', 'nullable', 'string', 'max:255'],
            'utm_content' => ['sometimes', 'nullable', 'string', 'max:255'],
            'revenue' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'timestamp' => ['sometimes', 'nullable', 'date'],
            'schema_version' => ['sometimes', 'integer', 'min:1'],
            'sdk_version' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'event.required' => 'The event name is required.',
            'identity.type.in' => 'Identity type must be one of: cookie, user_id, email_hash, ga_cid',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure idempotency_key is present or generate one
        if (!$this->has('idempotency_key')) {
            $this->merge([
                'idempotency_key' => (string) \Str::uuid(),
            ]);
        }

        // Normalize timestamp
        if ($this->has('timestamp')) {
            $this->merge([
                'timestamp' => $this->input('timestamp'),
            ]);
        } else {
            $this->merge([
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }
}
