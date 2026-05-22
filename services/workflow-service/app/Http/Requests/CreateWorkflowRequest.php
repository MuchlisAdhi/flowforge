<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RBAC handled by middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'timeout_seconds' => ['nullable', 'integer', 'min:10', 'max:86400'],
            'steps' => ['required', 'array', 'min:1', 'max:50'],
            'steps.*.id' => ['required', 'string', 'max:100'],
            'steps.*.type' => ['required', 'string', 'in:http,script,delay,condition'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.depends_on' => ['present', 'array'],
            'steps.*.depends_on.*' => ['string', 'max:100'],
            'steps.*.config' => ['required', 'array'],
            'steps.*.retry' => ['nullable', 'array'],
            'steps.*.retry.max_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
            'steps.*.retry.backoff' => ['nullable', 'string', 'in:exponential,linear'],
            'steps.*.retry.initial_delay_ms' => ['nullable', 'integer', 'min:100', 'max:60000'],
        ];
    }

    public function messages(): array
    {
        return [
            'steps.required' => 'A workflow must have at least one step',
            'steps.*.type.in' => 'Step type must be: http, script, delay, or condition',
        ];
    }
}
