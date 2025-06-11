<?php
namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = ['password', 'password_confirmation'];

    public function register(): void
    {
        //
           $this->renderable(function (ValidationException $exception, $request) {
        if (! $request->wantsJson()) {
            // tell Laravel to handle as usual
            return null;
        }

        return new JsonResponse([
            'message' => 'Validation Errors',
            'status' => false,
            'errors' => $exception->validator->errors()->all(),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    });
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}