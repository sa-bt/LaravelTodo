<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
    public function render($request, Throwable $e)
    {


        if ($e instanceof ValidationException)
        {
            return $this->errorResponse($e->validator->errors()->messages(),"There was a problem performing the operation",422);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException)
        {
            DB::rollBack();
            return $this->errorResponse( "Not Found","There was a problem performing the operation",404);
        }
        if ($e instanceof RouteNotFoundException )
        {
            DB::rollBack();
            return $this->errorResponse( "Unauthorized","There was a problem performing the operation",401);
        }

        if ($e instanceof MethodNotAllowedHttpException ||
            $e instanceof RelationNotFoundException ||
            $e instanceof QueryException ||
            $e instanceof Exception ||
            $e instanceof Error)
        {
            DB::rollBack();
            return $this->errorResponse( $e->getMessage(),"There was a problem performing the operation");
        }


        if (config('app.debug'))
        {
            return parent::render($request, $e);
        }

        DB::rollBack();
        return $this->errorResponse(500, $e->getMessage());
    }

}
