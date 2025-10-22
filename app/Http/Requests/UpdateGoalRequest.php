<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $goalId = $this->route('id') ?? $this->route('goal');

        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'status'      => ['nullable', Rule::in(['pending','in_progress','completed'])],
            'priority'    => ['nullable', Rule::in(['low','medium','high'])],

            'parent_id'   => [
                'nullable',
                Rule::exists('goals', 'id')->where(fn($q) => $q->where('user_id', auth()->id())),
                Rule::notIn([$goalId]),
            ],

            'send_task_reminder' => ['nullable', 'boolean'],
            'reminder_time'      => ['nullable', 'date_format:H:i', function ($attribute, $value, $fail) {
                if (request('send_task_reminder') && !$value) {
                    $fail('زمان یادآوری الزامی است.');
                }
            }],
        ];
    }
}
