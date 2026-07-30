<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendAdminNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') === true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
            'url' => ['nullable', 'string', 'max:500', 'regex:/^\/(?!\/)[^\s]*$/'],
            'channel' => ['required', Rule::in(['database', 'webpush'])],
            'audience' => ['required', Rule::in(['selected', 'all'])],
            'user_ids' => [
                Rule::requiredIf($this->input('audience') === 'selected'),
                'array',
                'min:1',
                'max:100',
            ],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
