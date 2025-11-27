<?php
// app/Http/Controllers/Api/GoalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\GoalRepository;
use Illuminate\Http\JsonResponse;
use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\Rules\Password;


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
            'email'          => ['required', 'email'],
            'password'       => ['required', 'string'],
            'captcha_id'     => ['required', 'string', 'size:32'],   // همون id که از /api/captcha/new گرفتی (16 بایت hex)
            'captcha_answer' => ['required', 'string', 'max:16'],
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
dd($validated);
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
                'password'              => [
                    'required',
                    'string',
                    'confirmed',
                    // 👈 قوانین جدید برای قوّت پسورد:
                    Password::min(10)      // حداقل 10 کاراکتر
                        ->mixedCase()      // شامل حروف کوچک و بزرگ
                        ->numbers()        // شامل عدد
                        ->symbols()        // شامل نمادها (@$!%*#?&)
                        // ->uncompromised(), // چک کردن در لیست رمزهای درز کرده
                ],
                'password_confirmation' => ['required', 'string', 'min:6'],
                'captcha_id'            => ['required', 'string', 'size:32'],
                'captcha_answer'        => ['required', 'string', 'max:16'],
            ],
            [
                // (اختیاری) پیام‌های فارسی سفارشی
                'email.email'       => 'فرمت پست الکترونیکی معتبر نیست.',
                'email.unique'      => 'این ایمیل قبلاً ثبت شده است.',
                'password.confirmed' => 'رمز عبور و تکرار آن مطابقت ندارند.',
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

        // 4) ساخت کاربر
        $code = strval(random_int(100000, 999999));

        $user = User::create([
            'name'                      => $v['name'],
            'email'                     => $v['email'],
            'password'                  => Hash::make($v['password']),
            // 💡 email_verified_at همچنان null است
            'verification_code'         => Hash::make($code),
            'verification_code_expires_at' => now()->addMinutes(2),
        ]);

        // 5) ارسال ایمیل
        Mail::send(new OtpMail($user, $code, 2)); // 2 دقیقه زمان انقضا

        // 6) پاسخ موفق
        RateLimiter::clear($rlKey);

        // App/Http/Controllers/Api/AuthController.php

// ...
        return $this->successResponse([
            'user_id' => $user->id,
            'email'   => $user->email,
        // 🚨 پیام فارسی را از آرایه data خارج کنید
        ], messageKey: 'ثبت‌نام با موفقیت انجام شد. کد تأیید به ایمیل شما ارسال شد.', code: 201);
    }
    private function normalizeCaptchaAnswer(string $v): string
    {
        $map = [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ];
        $v = trim($v);
        $v = preg_replace('/\s+/u', '', $v) ?? $v;
        return strtr(strtoupper($v), $map);
    }
    // در همان AuthController یا کنترلر مربوطه
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'otp'     => ['required', 'string', 'digits:6'],
        ]);

        $user = User::find($request->user_id);

        // چک کردن انقضا و کد
        if (!$user || $user->email_verified_at) { /* ... */
        } // هندلینگ خطا

        if (
            !$user->verification_code ||
            !$user->verification_code_expires_at ||
            now()->isAfter($user->verification_code_expires_at) ||
            !Hash::check($request->otp, $user->verification_code)
        ) {
            return $this->errorResponse(
                errors: ['otp' => ['کد تأیید اشتباه یا منقضی شده است.']],
                messageKey: 'کد تأیید اشتباه یا منقضی شده است.',
                code: 422
            );
        }

        // تأیید موفق و لاگین
        $user->update([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ]);

        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        return $this->successResponse([
            'user'  => $user,
            'token' => $token,
        // 🚨 پیام فارسی را از آرایه data خارج کنید
        ], messageKey: 'تأیید ایمیل با موفقیت انجام شد. به سیستم وارد شدید.', code: 200);
    }
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::find($request->user_id);

        // چک‌های امنیتی
        if ($user->email_verified_at) {
            return $this->errorResponse(messageKey: 'ایمیل قبلاً تأیید شده است.', code: 409);
        }

        // 1. ایجاد کد جدید
        $code = strval(random_int(100000, 999999));

        // 2. به‌روزرسانی کد و انقضا
        $user->update([
            'verification_code'         => Hash::make($code),
            'verification_code_expires_at' => now()->addMinutes(2),
        ]);

        // 3. ارسال ایمیل
        Mail::send(new OtpMail($user, $code, 2)); // 2 دقیقه زمان انقضا

        // App/Http/Controllers/Api/AuthController.php

        return $this->successResponse(
            messageKey: 'کد تأیید جدید به ایمیل شما ارسال شد.',
            code: 200
        );
    }
}
