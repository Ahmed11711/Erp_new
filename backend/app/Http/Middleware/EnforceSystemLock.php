<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SystemLock\SystemLockService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnforceSystemLock
{
    /** مسارات تبقى متاحة أثناء القفل (جلسة، حالة القفل، ويب هوك). */
    private const ALLOWED_PREFIXES = [
        'auth/login',
        'auth/logout',
        'auth/refresh',
        'auth/me',
        'system-lock',
        'meta/webhook',
        'shopify/webhook',
        'webhooks/',
        'price-list/photo',
    ];

    public function __construct(
        protected SystemLockService $lock
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);
        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $this->lock->isRestricted($user)) {
            return $next($request);
        }

        if ($this->isAllowedPath($request)) {
            return $next($request);
        }

        $payload = $this->lock->statusPayload($user);

        return response()->json([
            'code' => 'SYSTEM_LOCKED',
            'locked' => true,
            'restricted' => true,
            'message' => $payload['message'],
        ], 503);
    }

    private function resolveUser(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        try {
            $authUser = auth('api')->user();
        } catch (Throwable $e) {
            return null;
        }

        return $authUser instanceof User ? $authUser : null;
    }

    private function isAllowedPath(Request $request): bool
    {
        $path = trim($request->path(), '/');
        if (str_starts_with($path, 'api/')) {
            $path = substr($path, 4);
        } elseif ($path === 'api') {
            $path = '';
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($prefix !== '' && str_ends_with($prefix, '/')) {
                if (str_starts_with($path, $prefix) || $path === rtrim($prefix, '/')) {
                    return true;
                }
                continue;
            }
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
