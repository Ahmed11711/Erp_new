<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessingInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'external_invoice_no',
        'processing_order_id',
        'supplier_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'grand_total',
        'paid_amount',
        'due_amount',
        'status',
        'capitalize_to_inventory',
        'notes',
        'invoice_image',
        'daily_entry_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'float',
        'tax_amount' => 'float',
        'grand_total' => 'float',
        'paid_amount' => 'float',
        'due_amount' => 'float',
        'capitalize_to_inventory' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProcessingOrder::class, 'processing_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProcessingInvoiceLine::class);
    }
}
