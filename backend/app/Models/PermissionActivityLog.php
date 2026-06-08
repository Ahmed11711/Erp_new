<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionActivityLog extends Model
{
    protected $fillable = [
        'actor_id',
        'target_user_id',
        'action',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
