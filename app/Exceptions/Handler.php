<?php
namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = ['password', 'password_confirmation'];

    public function register(): void
    {
        //
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}