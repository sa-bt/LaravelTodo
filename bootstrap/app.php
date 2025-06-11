<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $exception, $request) {
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
    })->create();
