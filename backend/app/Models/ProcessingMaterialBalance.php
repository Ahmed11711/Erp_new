<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingMaterialBalance extends Model
{
    protected $fillable = [
        'processing_order_id',
        'supplier_id',
        'category_id',
        'at_vendor_category_id',
        'qty_at_vendor',
    ];

    protected $casts = [
        'qty_at_vendor' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProcessingOrder::class, 'processing_order_id');
    }
}
