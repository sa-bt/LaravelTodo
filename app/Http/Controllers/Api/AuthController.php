<?php
// app/Http/Controllers/Api/GoalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\GoalRepository;
use Illuminate\Http\JsonResponse;
use App\Mail\OtpMail;
use App\Models\User;
use App\Notifications\NewUserAwaitingApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;


class AuthController extends Controller
{
    /** سقف حدس کد تأیید روی هر حساب، پیش از سوزاندن خود کد. */
    private const OTP_MAX_ATTEMPTS = 5;

    /** پنجرهٔ شمارش حدس‌ها، به ثانیه. */
    private const OTP_ATTEMPT_DECAY = 600;

    /** فاصلهٔ اجباری بین دو درخواست کد، به ثانیه. با تایمر فرانت هم‌خوان است. */
    private const OTP_RESEND_COOLDOWN = 120;

    /** سقف دریافت کد در شبانه‌روز برای هر حساب. */
    private const OTP_RESEND_DAILY_CAP = 10;

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
        $answerHash = hash('sha256', $this->normalizeCaptchaAnswer($validated['captcha_answer']) . $pepper);

        if (!hash_equals($storedHash, $answerHash)) {
            // (اختیاری) اینجا می‌تونی شمارندهٔ تلاش‌ها بگذاری و بعد از N بار، خطای سخت‌تر بدهی
            return $this->errorResponse(errors: ['کد تأیید اشتباه است.'], code: 422);
        }
        /*
         * 3) تلاش برای ورود
         *
         * عمداً Auth::attempt نیست. آن متد گاردِ پیش‌فرضِ لحظهٔ اجرا را صدا
         * می‌زند و این مسیرها هیچ گروه میان‌افزاری ندارند، پس گارد پیش‌فرض
         * می‌تواند چیزی باشد که یک درخواست دیگر جا گذاشته. اینجا مستقیم با
         * provider کار می‌کنیم: بدون نشست، بدون حالتِ سربار.
         */
        $credentials = ['email' => $validated['email'], 'password' => $validated['password']];
        $provider = Auth::createUserProvider('users');

        /** @var \App\Models\User|null $user */
        $user = $provider->retrieveByCredentials($credentials);

        if (!$user || !$provider->validateCredentials($user, $credentials)) {
            return $this->errorResponse(errors: ['اطلاعات ورود نادرست است.'], code: 401);
        }

        /*
         * دروازهٔ وضعیت حساب، عمداً بعد از اعتبارسنجی گذرواژه.
         *
         * اگر قبلش می‌آمد، هر کسی با زدن ایمیل دیگران می‌فهمید آن حساب وجود
         * دارد و در چه وضعی است. حالا فقط صاحب گذرواژه این را می‌بیند.
         */
        if (!$user->email_verified_at) {
            return $this->errorResponse(
                errors: ['account' => ['ابتدا ایمیل خود را تأیید کنید.']],
                messageKey: 'ابتدا ایمیل خود را تأیید کنید.',
                code: 403
            );
        }

        if ($user->isRejected()) {
            return $this->errorResponse(
                errors: ['account' => ['درخواست عضویت شما پذیرفته نشد.']],
                messageKey: 'درخواست عضویت شما پذیرفته نشد.',
                code: 403
            );
        }

        if (!$user->isApproved()) {
            return $this->errorResponse(
                errors: ['account' => ['حساب شما در انتظار تأیید مدیر است.']],
                messageKey: 'حساب شما در انتظار تأیید مدیر است.',
                code: 403
            );
        }

        // 4) ساخت توکن و پاسخ موفق
        $token = $user->createToken('api-token')->plainTextToken;

        // 💡 اصلاح پاسخ: اطمینان از ارسال نقش (role) کاربر
        // ما از متد toArray() استفاده می‌کنیم تا فیلدهای $hidden حذف شوند،
        // اما فیلد role که در مدل به $hidden اضافه نشده، برگردانده می‌شود.
        $userData = $user->toArray();
        // مطمئن می‌شویم که فیلدهای حساس مثل password و verification_code برگردانده نشوند
        unset($userData['verification_code'], $userData['verification_code_expires_at']);

        return $this->successResponse([
            'user'  => $userData, // 👈 آبجکت تمیز شده کاربر شامل role: 'admin' یا 'user'
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
            // 💡 در اینجا role به صورت پیش‌فرض 'user' خواهد بود
            'role'                      => 'user', 
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
        return strtoupper(strtr($v, $map));
    }
    // در همان AuthController یا کنترلر مربوطه
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'otp'     => ['required', 'string', 'digits:6'],
        ]);

        $user = User::find($request->user_id);

        if ($user->email_verified_at) {
            return $this->errorResponse(
                errors: ['otp' => ['این ایمیل قبلاً تأیید شده است.']],
                messageKey: 'این ایمیل قبلاً تأیید شده است.',
                code: 409
            );
        }

        /*
         * سقف حدس روی خودِ حساب، نه روی نشانی شبکه.
         *
         * محدودیت مسیر فقط یک IP را کند می‌کند؛ یک کد شش‌رقمی را می‌شود از صد
         * نشانی موازی حدس زد. این شمارنده به شناسهٔ کاربر بسته است، پس هر چند
         * تا مهاجم هم که باشند روی یک سقف مشترک می‌نشینند.
         */
        $rlKey = 'otp-verify:' . $user->getKey();

        if (RateLimiter::tooManyAttempts($rlKey, self::OTP_MAX_ATTEMPTS)) {
            return $this->errorResponse(
                errors: ['otp' => ['تلاش بیش از حد. کد جدید بگیرید و دوباره امتحان کنید.']],
                messageKey: 'تلاش بیش از حد. کد جدید بگیرید و دوباره امتحان کنید.',
                code: 429
            )->header('Retry-After', (string) max(1, RateLimiter::availableIn($rlKey)));
        }

        if (
            !$user->verification_code ||
            !$user->verification_code_expires_at ||
            now()->isAfter($user->verification_code_expires_at) ||
            !Hash::check($request->otp, $user->verification_code)
        ) {
            RateLimiter::hit($rlKey, self::OTP_ATTEMPT_DECAY);

            /*
             * بعد از رسیدن به سقف، خودِ کد را هم می‌سوزانیم. وگرنه مهاجم فقط
             * صبر می‌کرد تا پنجرهٔ شمارنده باز شود و از همان‌جا ادامه می‌داد.
             */
            if (RateLimiter::tooManyAttempts($rlKey, self::OTP_MAX_ATTEMPTS)) {
                $user->update([
                    'verification_code' => null,
                    'verification_code_expires_at' => null,
                ]);
            }

            return $this->errorResponse(
                errors: ['otp' => ['کد تأیید اشتباه یا منقضی شده است.']],
                messageKey: 'کد تأیید اشتباه یا منقضی شده است.',
                code: 422
            );
        }

        RateLimiter::clear($rlKey);

        /*
         * forceFill لازم است: email_verified_at در $fillable نیست و با update
         * بی‌صدا کنار گذاشته می‌شد. یعنی تا امروز هیچ حسابی واقعاً تأییدشده
         * علامت نمی‌خورد و شمارندهٔ «تأییدشده» در داشبورد ادمین همیشه صفر بود.
         */
        $user->forceFill([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ])->save();

        /*
         * اعلان به ادمین‌ها عمداً اینجاست و نه در register: تا وقتی کسی ایمیلش
         * را تأیید نکرده، معلوم نیست اصلاً آدم باشد. اگر از register می‌رفت،
         * صف انتظار ادمین با هر ثبت‌نام الکی پر می‌شد.
         */
        $this->notifyAdminsOfPendingUser($user);

        return $this->successResponse([
            'user_id' => $user->id,
            'email'   => $user->email,
            'status'  => 'pending_approval',
        ], messageKey: 'ایمیل شما تأیید شد. حساب پس از تأیید مدیر فعال می‌شود.', code: 200);
    }

    private function notifyAdminsOfPendingUser(User $user): void
    {
        $admins = User::query()->where('role', 'admin')->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewUserAwaitingApproval($user));
        }
    }

    public function logout(Request $request): JsonResponse
    {
        /*
         * فقط توکن همین دستگاه حذف می‌شود، نه همهٔ نشست‌ها. خروج از تلفن نباید
         * کاربر را از رایانه‌اش هم بیرون بیندازد.
         */
        $request->user()->currentAccessToken()?->delete();

        return $this->successResponse(messageKey: 'از حساب خارج شدید.');
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

        /*
         * تا پیش از این، تنها چیزی که جلوی ارسال دوباره را می‌گرفت یک شمارنده در
         * مرورگر بود. مسیر باز و بی‌سقف بود و با شناسهٔ عددیِ قابل حدس، هر کسی
         * می‌توانست صندوق ایمیل یک کاربر واقعی را پر کند و سهمیهٔ ارسال ما را
         * بسوزاند. حالا هم فاصلهٔ اجباری بین دو ارسال هست و هم سقف روزانه.
         */
        $cooldownKey = 'otp-resend:' . $user->getKey();
        $dailyKey = 'otp-resend-daily:' . $user->getKey();

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $retry = RateLimiter::availableIn($cooldownKey);

            return $this->errorResponse(
                errors: ['otp' => ["تا {$retry} ثانیهٔ دیگر نمی‌توانید کد جدید بگیرید."]],
                messageKey: "تا {$retry} ثانیهٔ دیگر نمی‌توانید کد جدید بگیرید.",
                code: 429
            )->header('Retry-After', (string) max(1, $retry));
        }

        if (RateLimiter::tooManyAttempts($dailyKey, self::OTP_RESEND_DAILY_CAP)) {
            return $this->errorResponse(
                errors: ['otp' => ['سقف دریافت کد در شبانه‌روز پر شده است.']],
                messageKey: 'سقف دریافت کد در شبانه‌روز پر شده است.',
                code: 429
            )->header('Retry-After', (string) max(1, RateLimiter::availableIn($dailyKey)));
        }

        RateLimiter::hit($cooldownKey, self::OTP_RESEND_COOLDOWN);
        RateLimiter::hit($dailyKey, 86400);

        // کد تازه یعنی حدس‌های قبلی هم باید پاک شوند.
        RateLimiter::clear('otp-verify:' . $user->getKey());

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