<?php

namespace App\Http\Requests;

use App\Models\Content;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'content_type' => ['sometimes', Rule::in([
                Content::TYPE_NOTE,
                Content::TYPE_QUOTE,
                Content::TYPE_EXPERIENCE,
            ])],
            'status' => ['sometimes', Rule::in([
                Content::STATUS_DRAFT,
                Content::STATUS_READY,
                Content::STATUS_ARCHIVED,
            ])],
            'source' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
