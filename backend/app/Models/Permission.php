<?php

namespace App\Models;

use App\Services\Rbac\RbacStampService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
        'module',
        'slug',
        'description',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget('rbac:registered_slugs');
            app(RbacStampService::class)->bump();
        });
        static::deleted(function (): void {
            Cache::forget('rbac:registered_slugs');
            app(RbacStampService::class)->bump();
        });
    }

    public function dependencies()
    {
        return $this->hasMany(PermissionDependency::class, 'permission_id');
    }

    public function requiredBy()
    {
        return $this->hasMany(PermissionDependency::class, 'requires_permission_id');
    }
}
