<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class shippingCompanyDetails extends Model
{
    use HasFactory;

    public static function boot()
{
    parent::boot();

    static::created(function($shippingCompanyDetails) {
        $shippingCompanyDetails->ref .= 'R' . $shippingCompanyDetails->id;
        $shippingCompanyDetails->save();
    });
}

    protected $fillable = [
        'order_id',
        'shipping_date',
        'collect_date',
        'status',
        'amount',
        'shipping_company_id',
        'ref',
        'by',
        'old_amount'
    ];

    public function order(){
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function shipping_company()
    {
        return $this->belongsTo(ShippingCompany::class);
    }

}
