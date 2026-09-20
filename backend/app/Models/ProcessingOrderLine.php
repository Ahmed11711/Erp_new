<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingOrderLine extends Model
{
    protected $fillable = [
        'processing_order_id',
        'category_id',
        'destination_category_id',
        'at_vendor_category_id',
        'ordered_qty',
        'dispatched_qty',
        'received_good_qty',
        'received_damaged_qty',
        'received_rejected_qty',
        'expected_service_amount',
        'unit_material_cost',
        'notes',
    ];

    protected $casts = [
        'ordered_qty' => 'float',
        'dispatched_qty' => 'float',
        'received_good_qty' => 'float',
        'received_damaged_qty' => 'float',
        'received_rejected_qty' => 'float',
        'expected_service_amount' => 'float',
        'unit_material_cost' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProcessingOrder::class, 'processing_order_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function destinationCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'destination_category_id');
    }

    public function atVendorCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'at_vendor_category_id');
    }

    public function qtyAtVendor(): float
    {
        return max(0, (float) $this->dispatched_qty
            - (float) $this->received_good_qty
            - (float) $this->received_damaged_qty
            - (float) $this->received_rejected_qty);
    }
}
