<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewContactNotification;

class ContactController extends Controller
{

    public function store(StoreContactRequest $request): JsonResponse
    {
        $validated = $request->validated();
        // 1) بررسی و تایید کپچا
        $cacheKey = "captcha:{$validated['captcha_id']}";
        $storedHash = Cache::pull($cacheKey); // یک‌بار مصرف

        if (!$storedHash) {
            return $this->errorResponse(errors: ['کپچا منقضی شده، لطفاً دوباره تلاش کنید.'], code: 410);
        }

        $pepper = (string) config('app.captcha_pepper', 'replace-with-strong-static-pepper');

        // نرمالایز کردن پاسخ کاربر (حذف فاصله و تبدیل به بزرگ)
        $answer = strtoupper(trim($validated['captcha_answer']));
        $answerHash = hash('sha256', $answer . $pepper);

        if (!hash_equals($storedHash, $answerHash)) {
            return $this->errorResponse(errors: ['کد تأیید اشتباه است.'], code: 422);
        }

        // 2) آماده‌سازی داده‌ها برای ذخیره در دیتابیس
        // فقط فیلدهای name, email, message را انتخاب می‌کنیم
        // (فیلدهای captcha_id و captcha_answer که در جدول نیستند حذف می‌شوند)
        $contactData = $request->only(['name', 'email', 'message']);

        // پاکسازی ورودی‌ها (Sanitization) جهت امنیت
        $contactData['message'] = strip_tags($contactData['message']);
        $contactData['name'] = strip_tags($contactData['name']);

        // 3) ذخیره در دیتابیس
        $contact = Contact::create($contactData);

        // 4) ارسال ایمیل به ادمین
        $admin = \App\Models\User::find(1);

        if ($admin) {
            Notification::send($admin, new NewContactNotification($contact));
        }

        return response()->json([
            'message' => 'پیام شما با موفقیت ارسال شد.',
            'data' => $contact
        ], 201);
    }}
