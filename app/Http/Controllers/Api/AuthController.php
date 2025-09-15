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
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::attempt($credentials)) {
            return $this->errorResponse(errors: ['اطلاعات ورود نادرست است'], code: 401);
        }


        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;
        return $this->successResponse([
            'user' => $user,
            'token' => $token
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
