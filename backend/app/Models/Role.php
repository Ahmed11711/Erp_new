<?php

namespace App\Models;

use App\Services\Rbac\RbacStampService;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'slug',
        'description',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => app(RbacStampService::class)->bump());
        static::deleted(fn () => app(RbacStampService::class)->bump());
    }

    public function departmentTemplate()
    {
        return $this->hasOne(DepartmentRoleTemplate::class, 'role_id');
    }
}
