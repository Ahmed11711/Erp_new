<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $guarded=[];

    public function order_products()
    {
        return $this->hasMany(OrderProduct::class);
    }
    public function order_products_archive()
    {
        return $this->hasMany(OrderProductArchive::class);
    }
    public function order_shipment_number()
    {
        return $this->hasMany(OrderShippingNumber::class)->orderBy('created_at', 'desc');
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    public function tempReview()
    {
        return $this->hasMany(OrderTempReview::class);
    }
    public function traking()
    {
        return $this->hasMany(tracking::class);
    }
    public function note()
    {
        return $this->hasMany(Note::class);
    }
    public function maintenReason()
    {
        return $this->hasMany(OrderMaintenReason::class);
    }
    public function shipping_method()
    {
        return $this->belongsTo(ShippingMethod::class);
    }
    public function order_source()
    {
        return $this->belongsTo(OrderSource::class);
    }
    public function order_details()
    {
        return $this->hasOne(OrderDetails::class);
    }
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

}
