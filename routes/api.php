<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoalController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

Route::post('/register', function (Request $request) {
    $request->validate([
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
    ]);

    return response()->json(['token' => $user->createToken('token-name')->plainTextToken]);
});

Route::post('/login', function (Request $request) {
    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    return response()->json(['token' => $user->createToken('token-name')->plainTextToken]);
});

Route::middleware('auth:sanctum')->prefix('goals')->group(function () {
    Route::get('/', [GoalController::class, 'index']);
    Route::post('/', [GoalController::class, 'store']);
    Route::put('/{goal}', [GoalController::class, 'update']);
    Route::delete('/{goal}', [GoalController::class, 'destroy']);
});