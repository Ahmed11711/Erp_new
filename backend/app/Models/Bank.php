<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'balance',
        'usage',
        'asset_id',
    ];

    public function asset()
    {
        return $this->belongsTo(TreeAccount::class, 'asset_id');
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'bank_user', 'bank_id', 'user_id')
            ->withTimestamps();
    }
}
