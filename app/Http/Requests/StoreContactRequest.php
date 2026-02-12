<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10'],
            'captcha_id' => ['required', 'string'],
            'captcha_answer' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        // کلیدهای ترجمه برگردانید، نه متن فارسی!
        return [
            'name.required' => 'errors.name_required',
            'name.min' => 'errors.name_min',
            'email.required' => 'errors.email_required',
            'email.email' => 'errors.email_invalid',
            'message.required' => 'errors.message_required',
            'message.min' => 'errors.message_min',
            'captcha_answer.required' => 'errors.captcha_required',
        ];
    }
}
