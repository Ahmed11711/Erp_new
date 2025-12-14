<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingLineStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'shipping_company_id',
        'order_id',
        'user_id',
        'canceled',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function order(){
        return $this->belongsTo(Order::class);
    }

    public function shippingCompany(){
        return $this->belongsTo(shippingCompany::class);
    }
}
