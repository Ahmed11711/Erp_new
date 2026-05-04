<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetails extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'need_by_date',
        'shipping_date',
        'status_date',
        'collection_date',
        'shipping_company_id',
        'collection_company_id',
        'shipping_receivable_amount',
        'collection_receivable_amount',
        'shipping_line_id',
        'edits',
        'postponed',
        'vip',
        'shortage',
        'reviewed',
        'shippment_image',
        'shippment_number',
        'user_reviewed_note',
        'user_reviewed',
    ];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function shipping_company()
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    /** شركة التحصيل الإلكتروني (Paymob وغيرها) — اختياري */
    public function collection_company()
    {
        return $this->belongsTo(ShippingCompany::class, 'collection_company_id');
    }
    public function shipping_line(){
        return $this->belongsTo(shippingline::class);
    }

}
