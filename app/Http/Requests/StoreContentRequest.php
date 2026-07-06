<?php

namespace App\Http\Requests;

use App\Models\Content;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'content_type' => ['nullable', Rule::in([
                Content::TYPE_NOTE,
                Content::TYPE_QUOTE,
                Content::TYPE_EXPERIENCE,
            ])],
            'status' => ['nullable', Rule::in([
                Content::STATUS_DRAFT,
                Content::STATUS_READY,
                Content::STATUS_ARCHIVED,
            ])],
            'source' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان محتوا الزامی است.',
            'title.max' => 'عنوان محتوا نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'content_type.in' => 'نوع محتوا معتبر نیست.',
            'status.in' => 'وضعیت محتوا معتبر نیست.',
            'source.max' => 'منبع محتوا نباید بیشتر از ۲۵۵ کاراکتر باشد.',
            'metadata.array' => 'داده‌های تکمیلی باید ساختار معتبر داشته باشند.',
            'published_at.date' => 'زمان انتشار معتبر نیست.',
        ];
    }
}
