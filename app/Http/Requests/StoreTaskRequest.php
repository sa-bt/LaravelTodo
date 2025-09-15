<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'day' => ['required'],
            'is_done' => ['boolean'],
            'for' => ['nullable', 'numeric', 'max:365', 'min:1'],
            'goal_id' => [
                'required',
                'exists:goals,id',
                Rule::unique('tasks')->where(
                    fn($q) =>
                    $q->where('day', $this->day)
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
