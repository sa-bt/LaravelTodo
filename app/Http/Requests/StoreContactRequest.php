<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[ا-یa-zA-Z\s]+$/'], // فقط حروف و فاصله
            'email' => ['required', 'email:rfc,dns', 'max:255'], // بررسی ساختار و وجود دامنه
            'message' => ['required', 'string', 'min:10', 'max:2000'], // حداقل و حداکثر طول
            'website' => ['sometimes'], // برای هانی‌پات
            'captcha_id'     => ['required', 'string', 'size:32'],   // همون id که از /api/captcha/new گرفتی (16 بایت hex)
            'captcha_answer' => ['required', 'string', 'max:16'],
        ];
    }

// اضافه کردن پیام‌های خطای اختصاصی برای امنیت
    public function messages(): array
    {
        return [
            'name.regex' => 'نام وارد شده معتبر نیست.',
            'message.min' => 'پیام خیلی کوتاه است (حداقل ۱۰ کاراکتر).',
            'captcha_answer.required' => 'کد امنیتی وارد نشده است.',
        ];
    }
}
