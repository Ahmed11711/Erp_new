<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentRoleTemplate extends Model
{
    protected $fillable = [
        'department',
        'role_id',
        'sample_users_count',
    ];

    protected $casts = [
        'sample_users_count' => 'integer',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
