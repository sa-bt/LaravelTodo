<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    use ApiResponse;

    public function handle(Request $request, Closure $next)
    {
        if (auth()->user() && auth()->user()->role == User::ADMIN_ROLE)
            return $next($request);
        return $this->errorResponse(auth()->user()->role,'Unauthorized.',Response::HTTP_UNAUTHORIZED);
    }
}
