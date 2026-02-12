<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TrackVisits
{
    public function handle(Request $request, Closure $next)
    {
        // ثبت بازدید برای مسیر فعلی API
        visits($request->path())->increment();

        return $next($request);
    }
}
