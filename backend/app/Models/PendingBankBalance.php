<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingBankBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'details',
        'ref',
        'type',
        'bank_id',
        'user_id',
        'status'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function bank(){
        return $this->belongsTo(Bank::class);
    }
}
