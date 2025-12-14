<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'asset_date',
        'payment_amount',
        'payment_amount',
        'bank_id',
        'asset_amount',
    ];

    public function bank(){
        return $this->belongsTo(Bank::class);
    }

}
