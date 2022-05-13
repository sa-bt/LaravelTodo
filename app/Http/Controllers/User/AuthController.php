<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\User\UserResource;
use App\Interfaces\UserInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    protected $repository;

    public function __construct(UserInterface $userRepository)
    {
        $this->repository = $userRepository;
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8', 'confirmed']
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);
    }

    public function login(LoginRequest $request)
    {
        $user = $this->repository->findOneBy(['email'=> $request->email]);
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }
        $token = $user->createToken($request->device_name)->plainTextToken;
        $data = [
            "token" => $token,
            "isAdmin" => boolval($user->role == User::ADMIN_ROLE)
        ];
        return $this->successResponse($data, "Token created successfully", Response::HTTP_OK);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return $this->successResponse([], "Token deleted successfully", Response::HTTP_OK);

    }

    public function user()
    {
        return $this->successResponse(new UserResource(auth()->user()), $this->fetched, Response::HTTP_OK);
    }
}
