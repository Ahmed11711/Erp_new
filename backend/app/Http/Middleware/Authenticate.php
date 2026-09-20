<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // طلبات الـ API لا يوجد لها مسار تسجيل دخول للتحويل إليه؛ إرجاع null يجعل Laravel
        // يستجيب بـ 401 JSON بدلاً من رمي RouteNotFoundException (500) الذي يمنع
        // الواجهة من تجديد التوكن.
        if ($request->is('api', 'api/*') || $request->expectsJson()) {
            return null;
        }

        return \Illuminate\Support\Facades\Route::has('login') ? route('login') : null;
    }
}
