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
        $storedHash = Cache::pull($cacheKey);

        if (!$storedHash) {
            return response()->json([
                'errors' => ['captcha' => 'captcha.expired']
            ], 422);
        }

        $pepper = (string) config('app.captcha_pepper', 'replace-with-strong-static-pepper');
        $answer = strtoupper(trim($validated['captcha_answer']));
        $answerHash = hash('sha256', $answer . $pepper);

        if (!hash_equals($storedHash, $answerHash)) {
            return response()->json([
                'errors' => ['captcha' => 'captcha.invalid']
            ], 422);
        }

        // 2) آماده‌سازی داده‌ها
        $contactData = $request->only(['name', 'email', 'message']);
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
            'message' => 'contact.success',
            'data' => $contact
        ], 201);
    }
}
