<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'timeout_seconds' => ['nullable', 'integer', 'min:10', 'max:86400'],
            'change_note' => ['nullable', 'string', 'max:500'],
            'steps' => ['sometimes', 'array', 'min:1', 'max:50'],
            'steps.*.id' => ['required_with:steps', 'string', 'max:100'],
            'steps.*.type' => ['required_with:steps', 'string', 'in:http,script,delay,condition'],
            'steps.*.name' => ['required_with:steps', 'string', 'max:255'],
            'steps.*.depends_on' => ['required_with:steps', 'array'],
            'steps.*.depends_on.*' => ['string', 'max:100'],
            'steps.*.config' => ['required_with:steps', 'array'],
            'steps.*.retry' => ['nullable', 'array'],
        ];
    }
}
