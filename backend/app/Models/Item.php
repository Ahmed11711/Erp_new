<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Services\Manufacturing\ProductTypeResolver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain alias for product rows stored in `categories` (existing ERP items).
 */
class Item extends Category
{
    protected $table = 'categories';

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function lineageRoot(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'lineage_root_id');
    }

    /** النسخة الأقدم التي استُبدلت بهذا الصنف */
    public function replacesItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'replaces_item_id');
    }

    /** النسخة الأحدث التي حلّت محل هذا الصنف */
    public function replacedByItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'replaced_by_item_id');
    }

    public function resolvedProductType(): ProductType
    {
        $raw = trim((string) ($this->product_type ?? ''));
        if ($raw !== '') {
            try {
                return ProductType::from($raw);
            } catch (\ValueError) {
                // ignore invalid stored values
            }
        }

        return ProductTypeResolver::fromWarehouseName((string) ($this->warehouse ?? ''));
    }

    public function canSellOnSalesChannel(): bool
    {
        $type = $this->resolvedProductType();

        if ($type === ProductType::Finished) {
            return true;
        }

        if ($type === ProductType::RawMaterial) {
            return true;
        }

        if ($type === ProductType::SemiFinished) {
            return (bool) $this->allow_wip_sale;
        }

        return false;
    }
}
