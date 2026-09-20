<?php

namespace App\Services\Manufacturing;

use App\Models\Color;
use App\Models\Item;
use App\Models\Recipe;
use App\Services\Items\ItemCodeService;
use Illuminate\Support\Facades\Log;

/**
 * After a BOM is saved once on a base finished-good item, expands:
 * - Pivot colors on the base (which shades the SKU is sold in).
 * - Finished-good child rows per color (for stock & sales).
 * - Raw material child rows per color for ingredients flagged supports_color.
 *
 * Idempotent: safe to run on every import commit.
 */
final class RecipeVariantBootstrapService
{
    public function __construct(
        private ColorCatalogService $colors,
        private ItemCodeService $itemCodes,
    ) {
    }

    /**
     * @param  array<int, string>  $finishColorLabels
     */
    public function syncRecipeVariants(Recipe $recipe, Item $finishedBase, array $finishColorLabels): void
    {
        $finishColorLabels = array_values(array_unique(array_filter(array_map('trim', $finishColorLabels))));
        if ($finishColorLabels === []) {
            return;
        }

        $colorModels = $this->colors->resolveOrCreateMany($finishColorLabels);
        if ($colorModels === []) {
            return;
        }

        // بعد ضبط تكلفة/سعر الأساس في نفس المعاملة — نعمل fresh لنسخ الواقع الحالي إلى النسخ
        /** @var Item $finishedAnchor */
        $finishedAnchor = $finishedBase->fresh() ?? $finishedBase;

        $ids = [];
        foreach ($colorModels as $c) {
            $ids[] = (int) $c->id;
        }
        sort($ids);

        $finishedAnchor->manufacturingColors()->sync($ids);

        foreach ($colorModels as $color) {
            $display = $this->variantDisplayName((string) ($finishedAnchor->category_name ?? ''), (string) $color->name);
            $variant = $this->findOrAdoptVariant($finishedAnchor, $color, $display);
            $this->finalizeVariantEconomicsAndColor($variant->fresh(), $finishedAnchor, $color);
            $this->itemCodes->ensureCode($variant->fresh());
        }

        $recipe->loadMissing('ingredients.item');
        foreach ($recipe->ingredients as $line) {
            $ing = $line->item;
            if (! $ing) {
                continue;
            }

            $anchor = $ing->parent_item_id
                ? Item::query()->find((int) $ing->parent_item_id)
                : $ing;
            if (! $anchor || ! $anchor->supports_color) {
                continue;
            }

            /** @var Item $anchorFresh */
            $anchorFresh = $anchor->fresh() ?? $anchor;

            foreach ($colorModels as $color) {
                $display = $this->variantDisplayName((string) ($anchorFresh->category_name ?? ''), (string) $color->name);
                $v = $this->findOrAdoptVariant($anchorFresh, $color, $display);
                $this->finalizeVariantEconomicsAndColor($v->fresh(), $anchorFresh, $color);
                $this->itemCodes->ensureCode($v->fresh());
            }
        }

        Log::info('[recipe-variants] synced', [
            'recipe_id' => $recipe->id,
            'finished_base_id' => $finishedAnchor->id,
            'color_count' => count($colorModels),
        ]);
    }

    /**
     * يطبّق على النسخ: حقل اللون النصّي لواجهات الأصناف + تعويض المتوسط المرجّح لوحدة التكلفة كالأصل.
     */
    private function finalizeVariantEconomicsAndColor(Item $variant, Item $pricingAnchor, Color $colorRow): void
    {
        $anchor = Item::query()->find($pricingAnchor->id) ?? $pricingAnchor;

        $anchorQty = (float) ($anchor->quantity ?? 0);
        $anchorTotal = (float) ($anchor->total_price ?? 0);
        $anchorUnitStored = (float) ($anchor->unit_price ?? 0);

        /** تكلفة الوحدة «المنطقيّة»: نفس وحدة المتوسط الذي يعرضها الجدول (total÷qty أو unit_price المحفوظ) */
        $effectiveUnitCost = $anchorUnitStored > 1e-9
            ? $anchorUnitStored
            : (abs($anchorQty) > 1e-9 ? $anchorTotal / $anchorQty : 0.0);

        if ($effectiveUnitCost < 0.0) {
            $effectiveUnitCost = abs($effectiveUnitCost);
        }

        $variantQty = (float) ($variant->quantity ?? 0);
        $sellUnit = (float) ($anchor->category_price ?? 0);

        $variant->forceFill([
            'color' => trim((string) $colorRow->name),
            'unit_price' => round($effectiveUnitCost, 6),
            'category_price' => (float) ($anchor->category_price ?? 0),
            'total_price' => abs($variantQty) > 1e-9 ? round($variantQty * $effectiveUnitCost, 4) : 0.0,
            'sell_total_price' => abs($variantQty) > 1e-9 ? round($variantQty * $sellUnit, 4) : 0.0,
        ]);
        $variant->save();

    }

    /**
     * Find existing variant by parent+color_id, or adopt a legacy item by name match, or create new.
     */
    private function findOrAdoptVariant(Item $base, Color $color, string $displayName): Item
    {
        // 1) Already linked as variant
        $existing = Item::query()
            ->where('parent_item_id', $base->id)
            ->where('color_id', $color->id)
            ->first();

        if ($existing) {
            $shell = $this->baseShellForVariant($base, $displayName, (string) $color->name);
            $shell['color'] = (string) $color->name;
            $existing->update($shell);
            return $existing;
        }

        // 2) Legacy item: same warehouse, name matches pattern "BaseName - Color" or "BaseName — Color"
        $patterns = [
            $base->category_name . ' - ' . $color->name,
            $base->category_name . ' — ' . $color->name,
            strtolower($base->category_name) . ' - ' . strtolower($color->name),
        ];

        $legacy = Item::query()
            ->where('warehouse', $base->warehouse)
            ->whereNull('parent_item_id')
            ->where(function ($q) use ($patterns) {
                foreach ($patterns as $p) {
                    $q->orWhereRaw('LOWER(TRIM(category_name)) = ?', [strtolower(trim($p))]);
                }
            })
            ->first();

        if ($legacy) {
            $legacy->parent_item_id = $base->id;
            $legacy->color_id = $color->id;
            $legacy->color = (string) $color->name;
            $legacy->supports_color = false;
            $legacy->category_name = $displayName;
            $legacy->save();
            return $legacy;
        }

        // 3) Create new
        return Item::query()->create(array_merge(
            $this->baseShellForVariant($base, $displayName, (string) $color->name),
            [
                'parent_item_id' => $base->id,
                'color_id' => $color->id,
            ]
        ));
    }

    private function variantDisplayName(string $baseName, string $colorName): string
    {
        $baseName = trim($baseName);
        $colorName = trim($colorName);

        return $baseName === '' ? $colorName : $baseName . ' - ' . $colorName;
    }

    /** نفس سلوك RecipeSheetImportService:createItem — عمود category_image مطلوب في القاعدة */
    private function variantCategoryImage(Item $base): string
    {
        $img = $base->category_image ?? null;
        if ($img !== null && trim((string) $img) !== '') {
            return (string) $img;
        }

        return (string) (config('items_import.new_item.category_image') ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function baseShellForVariant(Item $base, string $displayName, string $legacyColor): array
    {
        return [
            'category_name' => $displayName,
            'warehouse' => $base->warehouse,
            'stock_id' => $base->stock_id,
            'production_id' => $base->production_id,
            'measurement_id' => $base->measurement_id,
            'product_type' => $base->product_type,
            'category_price' => $base->category_price,
            'unit_price' => $base->unit_price,
            'supports_color' => false,
            'color' => $legacyColor,
            'recipe_id' => null,
            'allow_wip_sale' => (bool) ($base->allow_wip_sale ?? false),
            'initial_balance' => (float) ($base->initial_balance ?? 0),
            'minimum_quantity' => (float) ($base->minimum_quantity ?? 0),
            'status' => $base->status ?? null,
            'item_code' => null,
            'category_image' => $this->variantCategoryImage($base),
            'ref' => $base->ref ?? null,
        ];
    }
}
