<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePageViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * This is a public endpoint, so the rules are the only thing standing
     * between a stranger and the analytics table. Every field is bounded and
     * the ids have to look like ids, not free text.
     */
    public function rules(): array
    {
        return [
            'visitor_id' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'session_id' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],

            // The path is normalized after validation, so extra length here is
            // harmless and rejecting a long url would only lose the view.
            'path' => ['required', 'string', 'max:2048'],
            'route' => ['nullable', 'string', 'max:64'],
            'referrer' => ['nullable', 'string', 'max:2048'],

            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'visitor_id.required' => 'شناسه بازدیدکننده الزامی است.',
            'visitor_id.regex' => 'شناسه بازدیدکننده معتبر نیست.',
            'session_id.required' => 'شناسه نشست الزامی است.',
            'session_id.regex' => 'شناسه نشست معتبر نیست.',
            'path.required' => 'مسیر صفحه الزامی است.',
        ];
    }
}
