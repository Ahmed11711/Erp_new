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
        'shipping_provider_id',
        'collection_company_id',
        'collection_provider_type',
        'collection_provider_id',
        'shipping_receivable_amount',
        'collection_receivable_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'amount_to_collect',
        'delivery_status',
        'collection_status',
        'settlement_status',
        'settlement_voucher_id',
        'liability_holder_type',
        'liability_holder_id',
        'liability_transferred_at',
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
        'delivery_date',
        'delivered_by_user_id',
        'delivery_batch_code',
    ];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function shipping_company()
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    /** شركة التحصيل الإلكتروني (Paymob وغيرها) — اختياري — legacy FK على shipping_companies */
    public function collection_company()
    {
        return $this->belongsTo(ShippingCompany::class, 'collection_company_id');
    }

    public function shipping_provider()
    {
        return $this->belongsTo(ShippingCompany::class, 'shipping_provider_id');
    }

    public function collection_provider_company()
    {
        return $this->belongsTo(CollectionCompany::class, 'collection_provider_id');
    }
    public function shipping_line(){
        return $this->belongsTo(shippingline::class);
    }

}
