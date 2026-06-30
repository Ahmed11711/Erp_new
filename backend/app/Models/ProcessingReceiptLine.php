<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingReceiptLine extends Model
{
    protected $fillable = [
        'processing_receipt_id',
        'processing_order_line_id',
        'category_id',
        'at_vendor_category_id',
        'destination_category_id',
        'good_qty',
        'damaged_qty',
        'rejected_qty',
        'material_unit_cost',
        'allocated_service_cost',
        'dest_unit_cost',
        'dest_sell_price',
        'rejection_return_category_id',
    ];

    protected $casts = [
        'good_qty' => 'float',
        'damaged_qty' => 'float',
        'rejected_qty' => 'float',
        'material_unit_cost' => 'float',
        'allocated_service_cost' => 'float',
        'dest_unit_cost' => 'float',
        'dest_sell_price' => 'float',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(ProcessingReceipt::class, 'processing_receipt_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(ProcessingOrderLine::class, 'processing_order_line_id');
    }

    public function destinationCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'destination_category_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
