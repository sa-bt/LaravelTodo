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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    public function __construct(private GoalRepository $goalRepo) {}



    public function login(Request $request): JsonResponse
    {
        // 0) Honeypot: اگر پر بود => ربات
        if ($request->filled('website')) {
            return $this->errorResponse(errors: ['درخواست نامعتبر است.'], code: 422);
        }

        // 1) اعتبارسنجی ورودی‌ها + توکن کپچا
        $validated = $request->validate([
            'email'    => ['required','email'],  // اگر نام‌کاربری هم دارید، این را تغییر بدهید
            'password' => ['required','string'],
            'cf_token' => ['required','string'],
        ]);

        // 2) Verify کپچا (Turnstile)
        try {
            $verify = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret'   => config('services.turnstile.secret'),
                'response' => $validated['cf_token'],
                'remoteip' => $request->ip(),
            ])->json();
        } catch (\Throwable $e) {
            // خطای شبکه/Cloudflare
            return $this->errorResponse(errors: ['بررسی کپچا انجام نشد. لطفاً دوباره تلاش کنید.'], code: 503);
        }

        if (!($verify['success'] ?? false)) {
            return $this->errorResponse(errors: ['تأیید کپچا ناموفق بود.'], code: 422);
        }

        // 3) تلاش برای ورود
        $credentials = ['email' => $validated['email'], 'password' => $validated['password']];
        if (!Auth::attempt($credentials)) {
            return $this->errorResponse(errors: ['اطلاعات ورود نادرست است'], code: 401);
        }

        // 4) ساخت توکن و پاسخ موفق
        $user  = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return $this->successResponse([
            'user'  => $user,
            'token' => $token,
        ]);
    }


    public function register(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed', // باید فیلد password_confirmation هم ارسال بشه
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([
                $validator->errors(),
            ], code: 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;


        return $this->successResponse([
            'user' => $user,
            'token' => $token
        ], code: 201);
    }
}
