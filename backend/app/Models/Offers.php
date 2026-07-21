<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offers extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'offer',
        'quote',
        'contact_person',
        'client_phone',
        'customer_company_id',
        'debt_posted_at',
        'debt_amount',
        'converted_order_id',
        'converted_at',
        'dateFrom',
        'dateTo',
        'subtotal',
        'vat',
        'total',
        'phone_number',
        'email',
        'title',
        'note',
        'transportation',
    ];

    protected $casts = [
        'debt_posted_at' => 'datetime',
        'converted_at' => 'datetime',
        'debt_amount' => 'float',
        'total' => 'float',
    ];

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function category()
    {
        return $this->hasMany(OffersCategory::class, 'offer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customerCompany(): BelongsTo
    {
        return $this->belongsTo(customerCompany::class, 'customer_company_id');
    }

}
