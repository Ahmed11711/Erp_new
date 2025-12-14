<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class DepartmentAccess
{
    public function handle($request, Closure $next, ...$departments)
    {
        $user = Auth::user();

        if (!in_array($user->department, $departments)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}

