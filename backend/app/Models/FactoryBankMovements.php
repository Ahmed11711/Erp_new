<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryBankMovements extends Model
{
    use HasFactory;

    protected $fillable=[
        "date",
        "description",
        "amount_in",
        'amount_out',
        'balance',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
