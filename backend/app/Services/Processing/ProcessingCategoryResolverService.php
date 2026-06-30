<?php

namespace App\Services\Processing;

use App\Models\Category;
use App\Models\Stock;
use App\Services\Items\ItemCodeService;
use App\Models\Item;

/**
 * Resolves or creates a shadow category row in a target warehouse for subcontract transfers.
 */
class ProcessingCategoryResolverService
{
    public function resolveInStock(Category $source, Stock $targetStock): Category
    {
        if ((int) $source->stock_id === (int) $targetStock->id) {
            return $source;
        }

        $existing = Category::query()
            ->where('stock_id', $targetStock->id)
            ->where(function ($q) use ($source) {
                $q->where('parent_item_id', $source->id);
                if ($source->item_code) {
                    $q->orWhere('item_code', $source->item_code);
                }
                $q->orWhere(function ($q2) use ($source) {
                    $q2->whereRaw('TRIM(category_name) = ?', [trim((string) $source->category_name)])
                        ->where('parent_item_id', $source->id);
                });
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $warehouseName = (string) ($targetStock->name ?? $source->warehouse);

        $attrs = [
            'category_name' => $source->category_name,
            'category_price' => $source->category_price ?? 0,
            'unit_price' => $source->unit_price ?? 0,
            'total_price' => 0,
            'sell_total_price' => 0,
            'initial_balance' => 0,
            'minimum_quantity' => $source->minimum_quantity ?? 0,
            'warehouse' => $warehouseName,
            'production_id' => $source->production_id,
            'measurement_id' => $source->measurement_id,
            'stock_id' => $targetStock->id,
            'category_image' => $source->category_image ?: 'no-image.png',
            'item_code' => null,
            'color' => $source->color,
            'recipe_id' => $source->recipe_id,
            'product_type' => $source->product_type ?? 'raw_material',
            'parent_item_id' => $source->id,
            'lineage_root_id' => $source->lineage_root_id ?? $source->id,
            'quantity' => 0,
            'status' => $source->status ?? '1',
        ];

        $category = Category::query()->create($attrs);

        if (! $category->item_code) {
            app(ItemCodeService::class)->ensureCode(Item::query()->findOrFail($category->id));
            $category->refresh();
        }

        return $category;
    }

    /**
     * Create a brand-new, standalone product (not a shadow of the source) in the target warehouse.
     * Used when receiving a processed item into a separate item the user names at receipt time.
     */
    public function createNamedProduct(
        Category $source,
        Stock $targetStock,
        string $name,
        string $productType = 'raw_material'
    ): Category {
        $name = trim($name);
        $warehouseName = (string) ($targetStock->name ?? $source->warehouse);

        $existing = Category::query()
            ->where('stock_id', $targetStock->id)
            ->whereRaw('TRIM(category_name) = ?', [$name])
            ->first();

        if ($existing) {
            return $existing;
        }

        $attrs = [
            'category_name' => $name,
            'category_price' => $source->category_price ?? 0,
            'unit_price' => $source->unit_price ?? 0,
            'total_price' => 0,
            'sell_total_price' => 0,
            'initial_balance' => 0,
            'minimum_quantity' => $source->minimum_quantity ?? 0,
            'warehouse' => $warehouseName,
            'production_id' => $source->production_id,
            'measurement_id' => $source->measurement_id,
            'stock_id' => $targetStock->id,
            'category_image' => 'no-image.png',
            'item_code' => null,
            'color' => $source->color,
            'product_type' => $productType,
            'quantity' => 0,
            'status' => '1',
        ];

        $category = Category::query()->create($attrs);

        if (! $category->item_code) {
            app(ItemCodeService::class)->ensureCode(Item::query()->findOrFail($category->id));
            $category->refresh();
        }

        return $category;
    }
}
