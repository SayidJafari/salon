<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * اگر کاربر با گارد «admin» لاگین نکرده باشد برود صفحهٔ لاگین مدیر
     */
    public function handle($request, Closure $next)
    {
        if (! Auth::guard('admin')->check()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
