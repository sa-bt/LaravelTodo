<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponse;

    protected $dontReport = [];
    protected $dontFlash = ['password', 'password_confirmation'];

    public function register(): void
    {
        // ValidationException → JSON استاندارد با map فیلدها
        $this->renderable(function (ValidationException $e, $request) {
            if (! $request->expectsJson()) {
                return null; // بگذار لاراول مسیر معمول را برود (برای صفحات)
            }

            // errors: {field:[...]}
            $errorsMap = $e->validator->errors()->toArray();

            return $this->errorResponse(
                errors: $errorsMap,
                messageKey: $e->getMessage() ?: 'Validation Errors',
                code: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        });

        // ThrottleRequestsException → پیام فارسی + Retry-After
        $this->renderable(function (ThrottleRequestsException $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }
            $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);

            return $this->errorResponse(
                errors: ['rate_limit' => ['Too many attempts.']],
                messageKey: 'دفعات تلاش بیش از حد مجاز است. بعداً دوباره تلاش کنید.',
                code: Response::HTTP_TOO_MANY_REQUESTS
            )->header('Retry-After', (string) $retryAfter);
        });
    }

    // 401 → JSON استاندارد
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return $this->errorResponse(
                errors: [],
                messageKey: 'Unauthenticated.',
                code: Response::HTTP_UNAUTHORIZED
            );
        }
        return redirect()->guest(route('login'));
    }
}
