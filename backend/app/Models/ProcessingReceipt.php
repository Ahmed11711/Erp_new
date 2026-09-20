<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessingReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_number',
        'processing_order_id',
        'processing_dispatch_note_id',
        'supplier_id',
        'destination_stock_id',
        'receipt_date',
        'status',
        'notes',
        'posted_at',
        'posted_by',
        'daily_entry_id',
        'stock_transaction_id',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProcessingOrder::class, 'processing_order_id');
    }

    public function dispatchNote(): BelongsTo
    {
        return $this->belongsTo(ProcessingDispatchNote::class, 'processing_dispatch_note_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function destinationStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'destination_stock_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProcessingReceiptLine::class);
    }
}
