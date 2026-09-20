<?php

use App\Models\User;
use App\Services\Rbac\PermissionResolutionService;

function rbac_user(): ?User
{
    $u = auth()->user();

    return $u instanceof User ? $u : null;
}

function has_permission(string $slug): bool
{
    $u = rbac_user();

    return $u ? app(PermissionResolutionService::class)->hasPermission($u, $slug) : false;
}

/** @param  list<string>|array<int, string>  $slugs */
function has_any_permission(array $slugs): bool
{
    $u = rbac_user();

    return $u ? app(PermissionResolutionService::class)->hasAnyPermission($u, $slugs) : false;
}

/** @param  list<string>|array<int, string>  $slugs */
function has_all_permissions(array $slugs): bool
{
    $u = rbac_user();

    return $u ? app(PermissionResolutionService::class)->hasAllPermissions($u, $slugs) : false;
}
