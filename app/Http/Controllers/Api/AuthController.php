<?php
// app/Http/Controllers/Api/GoalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Repositories\GoalRepository;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\GoalResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    public function __construct(private GoalRepository $goalRepo) {}



    public function login(Request $request): JsonResponse
    {
        // 0) Honeypot
        if ($request->filled('website')) {
            return $this->errorResponse(errors: ['درخواست نامعتبر است.'], code: 422);
        }

        // 1) اعتبارسنجی ورودی‌ها + کپچا
        $validated = $request->validate([
            'email'          => ['required','email'],
            'password'       => ['required','string'],
            'captcha_id'     => ['required','string','size:32'],   // همون id که از /api/captcha/new گرفتی (16 بایت hex)
            'captcha_answer' => ['required','string','max:16'],
        ]);

        // 2) Verify کپچا (stateless با Cache/Redis)
        $cacheKey = "captcha:{$validated['captcha_id']}";
        $storedHash = Cache::pull($cacheKey); // یک‌بار مصرف
        if (!$storedHash) {
            return $this->errorResponse(errors: ['کپچا منقضی شده، لطفاً دوباره تلاش کنید.'], code: 410);
        }

        $pepper = (string) config('app.captcha_pepper', 'replace-with-strong-static-pepper');
        $answerHash = hash('sha256', strtoupper(trim($validated['captcha_answer'])) . $pepper);

        if (!hash_equals($storedHash, $answerHash)) {
            // (اختیاری) اینجا می‌تونی شمارندهٔ تلاش‌ها بگذاری و بعد از N بار، خطای سخت‌تر بدهی
            return $this->errorResponse(errors: ['کد تأیید اشتباه است.'], code: 422);
        }

        // 3) تلاش برای ورود
        $credentials = ['email' => $validated['email'], 'password' => $validated['password']];
        if (!Auth::attempt($credentials)) {
            return $this->errorResponse(errors: ['اطلاعات ورود نادرست است.'], code: 401);
        }

        // 4) ساخت توکن و پاسخ موفق
        /** @var \App\Models\User $user */
        $user  = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return $this->successResponse([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        // 0) Honeypot
        if ($request->filled('website')) {
            return $this->errorResponse(
                errors: ['website' => ['درخواست نامعتبر است.']],
                messageKey: 'درخواست نامعتبر است.',
                code: 422
            );
        }

        // 1) Throttle (IP + ایمیل)
        $ip   = (string) $request->ip();
        $mail = strtolower((string) $request->input('email', ''));
        $rlKey = 'register:' . sha1($ip . '|' . $mail);

        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            $retry = RateLimiter::availableIn($rlKey);
            return $this->errorResponse(
                errors: ['rate_limit' => ['Too many attempts.']],
                messageKey: 'دفعات تلاش بیش از حد مجاز است. بعداً دوباره تلاش کنید.',
                code: 429
            )->header('Retry-After', (string) max(1, $retry));
        }

        // 2) اعتبارسنجی (با خروجی فیلدی)
        $validator = Validator::make(
            [
                'name'                  => $request->input('name'),
                'email'                 => $mail,
                'password'              => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'captcha_id'            => $request->input('captcha_id'),
                'captcha_answer'        => $request->input('captcha_answer'),
            ],
            [
                'name'                  => ['required', 'string', 'max:255'],
                'email'                 => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password'              => ['required', 'string', 'min:6', 'confirmed'],
                'password_confirmation' => ['required', 'string', 'min:6'],
                'captcha_id'            => ['required', 'string', 'size:32'],
                'captcha_answer'        => ['required', 'string', 'max:16'],
            ],
            [
                // (اختیاری) پیام‌های فارسی سفارشی
                'email.email'       => 'فرمت پست الکترونیکی معتبر نیست.',
                'email.unique'      => 'این ایمیل قبلاً ثبت شده است.',
                'password.confirmed'=> 'رمز عبور و تکرار آن مطابقت ندارند.',
            ]
        );

        if ($validator->fails()) {
            RateLimiter::hit($rlKey, 60);
            return $this->errorResponse(
                errors: $validator->errors()->toArray(), // 👈 map فیلدی برای هایلایت
                messageKey: 'Validation Errors',
                code: 422
            );
        }

        $v = $validator->validated();

        // 3) Verify کپچا (یک‌بارمصرف + pepper)
        $cacheKey   = "captcha:{$v['captcha_id']}";
        $storedHash = Cache::pull($cacheKey); // مصرف یک‌باره

        if (!$storedHash) {
            RateLimiter::hit($rlKey, 60);
            return $this->errorResponse(
                errors: ['captcha' => ['کپچا منقضی شده، دوباره تلاش کنید.']],
                messageKey: 'کپچا منقضی شده، دوباره تلاش کنید.',
                code: Response::HTTP_GONE // 410
            );
        }

        $answer = $this->normalizeCaptchaAnswer($v['captcha_answer']);
        $pepper = (string) config('app.captcha_pepper', '');
        if ($pepper === '') {
            return $this->errorResponse(
                errors: [],
                messageKey: 'خطای پیکربندی امنیتی.',
                code: 500
            );
        }
        $answerHash = hash('sha256', strtoupper($answer) . $pepper);

        if (!hash_equals($storedHash, $answerHash)) {
            RateLimiter::hit($rlKey, 60);
            return $this->errorResponse(
                errors: ['captcha' => ['کد تأیید اشتباه است.']],
                messageKey: 'کد تأیید اشتباه است.',
                code: 422
            );
        }

        // 4) ساخت کاربر
        $user = User::create([
            'name'     => $v['name'],
            'email'    => $v['email'], // از قبل lowercase شده
            'password' => Hash::make($v['password']),
        ]);

        // 5) توکن
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        // موفق → RL reset
        RateLimiter::clear($rlKey);

        return $this->successResponse([
            'user'  => $user,
            'token' => $token,
        ], code: 201);
    }
    private function normalizeCaptchaAnswer(string $v): string
    {
        $map = [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ];
        $v = trim($v);
        $v = preg_replace('/\s+/u', '', $v) ?? $v;
        return strtr(strtoupper($v), $map);
    }

}
