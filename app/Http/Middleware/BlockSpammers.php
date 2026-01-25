<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockSpammers
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // بررسی هانی‌پات: اگر فیلد website پر بود (نباید پر شود چون برای کاربر مخفی است)
        if ($request->has('website') && !empty($request->input('website'))) {
            // برگرداندن خطای 403 بدون افشای زیاد اطلاعات
            abort(403);
        }

        return $next($request);
    }
}
