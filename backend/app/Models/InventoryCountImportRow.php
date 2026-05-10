<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountImportRow extends Model
{
    protected $fillable = [
        'import_id',
        'excel_row_number',
        'excel_product_name',
        'excel_warehouse_name',
        'excel_quantity',
        'excel_unit_cost',
        'match_type',
        'match_confidence',
        'matched_name',
        'matched_category_id',
        'system_quantity',
        'quantity_difference',
        'adjustment_direction',
        'assigned_stock_id',
        'assigned_warehouse_name',
        'classification_method',
        'is_new_product',
        'is_duplicate_row',
        'merged_into_row_id',
        'warnings',
        'row_status',
        'error_message',
    ];

    protected $casts = [
        'excel_quantity' => 'decimal:6',
        'excel_unit_cost' => 'decimal:4',
        'match_confidence' => 'decimal:2',
        'system_quantity' => 'decimal:6',
        'quantity_difference' => 'decimal:6',
        'is_new_product' => 'boolean',
        'is_duplicate_row' => 'boolean',
        'warnings' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(InventoryCountImport::class, 'import_id');
    }

    public function matchedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'matched_category_id');
    }

    public function assignedStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'assigned_stock_id');
    }
}
