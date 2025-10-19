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
        // id فعلی از روت
        $goalId = $this->route('id') ?? $this->route('goal');

        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'status'      => ['nullable', Rule::in(['pending','in_progress','completed'])],
            'priority'    => ['nullable', Rule::in(['low','medium','high'])],

            'parent_id'   => [
                'nullable',
                // باید متعلق به همان کاربر باشد
                Rule::exists('goals', 'id')->where(fn($q) => $q->where('user_id', auth()->id())),
                // خودِ هدف، والد خودش نشود
                Rule::notIn([$goalId]),
            ],

            'send_task_reminder' => ['nullable', 'boolean'],
            'reminder_time'      => ['nullable', 'date_format:H:i'],
        ];
    }
}
