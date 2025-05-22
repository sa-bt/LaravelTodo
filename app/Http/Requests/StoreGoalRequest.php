<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['in:low,medium,high'],
            'status' => ['in:not_started,in_progress,completed,failed'],
            'parent_id' => ['nullable', 'exists:goals,id'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'title.required' => __('validation.required', ['attribute' => 'title']),
        ];
    }
}
