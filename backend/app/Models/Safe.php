<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Safe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'balance',
        'type',
        'is_inside_branch',
        'branch_name',
        'account_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_inside_branch' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(TreeAccount::class, 'account_id');
    }

    public function transactions()
    {
        return $this->hasMany(SafeTransaction::class, 'from_safe_id')
            ->orWhere('to_safe_id', $this->id);
    }
}

