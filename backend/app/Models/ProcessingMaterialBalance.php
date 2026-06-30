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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function atVendorCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'at_vendor_category_id');
    }
}
