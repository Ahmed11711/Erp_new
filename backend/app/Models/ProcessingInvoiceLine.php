<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingInvoiceLine extends Model
{
    protected $fillable = [
        'processing_invoice_id',
        'line_type',
        'description',
        'quantity',
        'unit_price',
        'total',
        'processing_receipt_line_id',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'total' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ProcessingInvoice::class, 'processing_invoice_id');
    }
}
