<?php

namespace App\Models;

use App\Services\Rbac\RbacStampService;
use Illuminate\Database\Eloquent\Model;

class UserPermissionOverride extends Model
{
    protected $table = 'user_permission_overrides';

    protected $fillable = [
        'user_id',
        'permission_id',
        'type',
    ];

    protected static function booted(): void
    {
        static::saved(function (UserPermissionOverride $model): void {
            app(RbacStampService::class)->bumpUser((int) $model->user_id);
        });
        static::deleted(function (UserPermissionOverride $model): void {
            app(RbacStampService::class)->bumpUser((int) $model->user_id);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
