<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'priority'            => ['required', 'in:low,medium,high'],
            'status'              => ['required', 'in:pending,in_progress,completed'],
            'parent_id'           => ['nullable', 'exists:goals,id'],
            'send_task_reminder'  => ['boolean'],
            'reminder_time'       => ['nullable', 'date_format:H:i', function ($attribute, $value, $fail) {
                if (request('send_task_reminder') && !$value) {
                    $fail('زمان یادآوری الزامی است.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'              => 'عنوان هدف الزامی است.',
            'priority.required'           => 'اولویت را انتخاب کنید.',
            'priority.in'                 => 'مقدار اولویت معتبر نیست.',
            'status.required'             => 'وضعیت را مشخص کنید.',
            'status.in'                   => 'مقدار وضعیت معتبر نیست.',
            'parent_id.exists'            => 'هدف والد معتبر نیست.',
            'send_task_reminder.boolean'  => 'فرمت یادآوری معتبر نیست.',
            'reminder_time.date_format'   => 'زمان یادآوری باید به فرمت HH:MM باشد (مثلاً 09:00).',
        ];
    }
}
