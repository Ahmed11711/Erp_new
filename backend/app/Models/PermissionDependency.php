<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionDependency extends Model
{
    protected $fillable = [
        'permission_id',
        'requires_permission_id',
    ];

    public function permission()
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    public function requires()
    {
        return $this->belongsTo(Permission::class, 'requires_permission_id');
    }
}
