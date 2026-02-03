<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'account_number',
        'balance',
        'img',
        'description',
        'other_info',
        'account_id',
    ];

    public function account()
    {
        return $this->belongsTo(TreeAccount::class, 'account_id');
    }
}
