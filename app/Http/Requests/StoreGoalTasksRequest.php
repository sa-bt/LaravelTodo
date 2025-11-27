<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalTasksRequest extends FormRequest
{
    // ... (authorize و messages بدون تغییر) ...

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'goal_id' => 'required|exists:goals,id',
            
            // تاریخ شمسی (فرمت آن چک نمی‌شود، فقط وجود آن کافی است)
            'start_date' => 'required|string', 
            
            'duration' => 'required|integer|min:1|max:365',
            
            // ✅ فیلدهای جدید برای الگوی تکرار:
            'pattern'    => 'nullable|string|in:daily,alternate_odd,alternate_even,weekly',
            'step'       => 'nullable|integer|min:1',
            'offset'     => 'nullable|integer|min:0',

            // ✅ فیلد آرایه‌ای روزهای هفته:
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'nullable|string|in:SU,MO,TU,WE,TH,FR,SA', // اعتبارسنجی مقادیر داخل آرایه
        ];
    }
}