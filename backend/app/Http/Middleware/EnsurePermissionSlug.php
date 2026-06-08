<?php

namespace App\Http\Middleware;

use App\Services\Rbac\PermissionResolutionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermissionSlug
{
    public function __construct(
        protected PermissionResolutionService $resolver
    ) {}

    public function handle(Request $request, Closure $next, string ...$slugs): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $parts = [];
        foreach ($slugs as $segment) {
            foreach (explode('|', $segment) as $p) {
                $t = trim($p);
                if ($t !== '') {
                    $parts[] = $t;
                }
            }
        }

        if ($parts === []) {
            return $next($request);
        }

        if ($this->resolver->hasAnyPermission($user, $parts)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
