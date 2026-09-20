<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingDispatchLine extends Model
{
    protected $fillable = [
        'processing_dispatch_note_id',
        'processing_order_line_id',
        'category_id',
        'at_vendor_category_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
        'total_cost' => 'float',
    ];

    public function dispatchNote(): BelongsTo
    {
        return $this->belongsTo(ProcessingDispatchNote::class, 'processing_dispatch_note_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(ProcessingOrderLine::class, 'processing_order_line_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function atVendorCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'at_vendor_category_id');
    }
}
