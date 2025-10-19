<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal_id'    => ['required', 'exists:goals,id'],
            'start_date' => ['required', 'string'], // jYYYY/jMM/jDD (جلالی) - سمت کنترلر پارس می‌کنیم
            'duration'   => ['required', 'integer', 'min:1'],

            // 🔽 اختیاری‌ها برای الگو
            'pattern'    => ['nullable', 'in:daily,alternate_odd,alternate_even'],
            'step'       => ['nullable', 'integer', 'in:1,2'], // 1 = روزانه | 2 = یک‌روزدرمیان
            'offset'     => ['nullable', 'integer', 'in:0,1'], // 0 = روزهای فرد | 1 = روزهای زوج
        ];
    }

    public function messages(): array
    {
        return [
            'goal_id.required'   => 'انتخاب هدف الزامی است.',
            'goal_id.exists'     => 'هدف انتخاب‌شده معتبر نیست.',
            'start_date.required'=> 'تاریخ شروع الزامی است.',
            'duration.required'  => 'مدت الزامی است.',
            'duration.min'       => 'مدت باید حداقل ۱ روز باشد.',
            'pattern.in'         => 'الگوی انتخابی نامعتبر است.',
            'step.in'            => 'مقدار step نامعتبر است.',
            'offset.in'          => 'مقدار offset نامعتبر است.',
        ];
    }
}
