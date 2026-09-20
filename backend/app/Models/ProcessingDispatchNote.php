<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessingDispatchNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'dispatch_number',
        'processing_order_id',
        'supplier_id',
        'source_stock_id',
        'dispatch_date',
        'dispatch_type',
        'status',
        'notes',
        'representative_type',
        'shipping_company_id',
        'external_representative_name',
        'posted_at',
        'posted_by',
        'daily_entry_id',
        'stock_transaction_id',
    ];

    protected $casts = [
        'dispatch_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProcessingOrder::class, 'processing_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sourceStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'source_stock_id');
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class, 'shipping_company_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProcessingDispatchLine::class);
    }
}
