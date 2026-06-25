<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessingOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'supplier_id',
        'source_stock_id',
        'destination_stock_id',
        'status',
        'expected_return_date',
        'notes',
        'expected_service_total',
        'total_dispatched_qty',
        'total_received_qty',
        'total_service_cost',
        'created_by',
        'approved_by',
        'approved_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'expected_return_date' => 'date',
        'expected_service_total' => 'float',
        'total_dispatched_qty' => 'float',
        'total_received_qty' => 'float',
        'total_service_cost' => 'float',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sourceStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'source_stock_id');
    }

    public function destinationStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'destination_stock_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProcessingOrderLine::class);
    }

    public function dispatchNotes(): HasMany
    {
        return $this->hasMany(ProcessingDispatchNote::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ProcessingReceipt::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ProcessingInvoice::class);
    }

    public function materialBalances(): HasMany
    {
        return $this->hasMany(ProcessingMaterialBalance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
