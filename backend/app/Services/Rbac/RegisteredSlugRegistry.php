<?php

namespace App\Services\Rbac;

use App\Models\Permission;
use Illuminate\Support\Facades\Cache;

class RegisteredSlugRegistry
{
    /**
     * @return list<string>
     */
    public function all(): array
    {
        return Cache::remember('rbac:registered_slugs', 86400, function (): array {
            $guard = config('auth.defaults.guard');

            return Permission::query()
                ->where('guard_name', $guard)
                ->get(['slug', 'name'])
                ->flatMap(function (Permission $p): array {
                    $slug = strtolower(trim((string) ($p->slug ?: \Illuminate\Support\Str::slug(str_replace(['.', '_'], ' ', $p->name), '.'))));
                    $legacy = strtolower(trim((string) $p->name));

                    return array_values(array_filter([$slug, $legacy !== '' ? $legacy : null]));
                })
                ->unique()
                ->values()
                ->all();
        });
    }
}
