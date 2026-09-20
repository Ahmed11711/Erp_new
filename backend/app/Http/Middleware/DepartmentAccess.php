<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DepartmentAccess
{
    public function handle($request, Closure $next, ...$departments)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $userDept = Str::lower(trim((string) ($user->department ?? '')));

        foreach ($departments as $allowed) {
            if (Str::lower(trim((string) $allowed)) === $userDept) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}

