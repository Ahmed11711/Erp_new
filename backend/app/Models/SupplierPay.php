<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierPay extends Model
{
    use HasFactory;

    public static function boot()
    {
        parent::boot();

        static::created(function($pay) {
            $pay->pay_number .= 'SP' . $pay->id;
            $pay->save();
        });
    }

    protected $fillable = [
        'pay_number',
        'amount',
        'bank_id',
        'supplier_id',
        'receipt_date'
    ];

    public function bank(){
        return $this->belongsTo(Bank::class);
    }

    public function supplier(){
        return $this->belongsTo(supplier::class);
    }
}
