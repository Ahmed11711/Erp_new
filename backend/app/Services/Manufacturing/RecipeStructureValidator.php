<?php

namespace App\Services\Manufacturing;

use App\Enums\ProductType;
use App\Models\Item;
use App\Models\Recipe;

/**
 * Validates BOM structure: output type, ingredient types, no self-reference.
 */
final class RecipeStructureValidator
{
    private const RAW_WAREHOUSE = 'مخزن مواد خام';
    private const WIP_WAREHOUSE = 'مخزن منتج تحت التشغيل';
    private const FINISHED_WAREHOUSE = 'مخزن منتج تام';

    /**
     * @throws \InvalidArgumentException
     */
    public static function assertValidForRecipe(Recipe $recipe, ?int $outputItemId): void
    {
        if ($outputItemId === null) {
            return;
        }

        $output = Item::query()->find($outputItemId);
        if (! $output) {
            throw new \InvalidArgumentException('المنتج النهائي غير موجود.');
        }

        $outType = $output->resolvedProductType();
        if (! in_array($outType, [ProductType::SemiFinished, ProductType::Finished], true)) {
            throw new \InvalidArgumentException(
                'يجب أن يكون المنتج النهائي للوصفة تحت التشغيل أو منتجاً تاماً (وليس مادة خام).'
            );
        }

        $outputAnchorId = ManufacturingConsumptionResolver::outputAnchorId($output);

        $recipe->loadMissing('ingredients.item');
        foreach ($recipe->ingredients as $ing) {
            $ingItem = $ing->item;
            if (! $ingItem) {
                throw new \InvalidArgumentException('مكوّن الوصفة غير موجود للسطر #' . $ing->id);
            }

            $ingAnchorId = $ingItem->parent_item_id
                ? (int) $ingItem->parent_item_id
                : (int) $ingItem->id;

            if ($ingAnchorId === $outputAnchorId || (int) $ingItem->id === (int) $outputItemId) {
                throw new \InvalidArgumentException('لا يمكن أن يكون المنتج النهائي مكوّناً في الوصفة نفسها.');
            }

            if (self::ingredientAllowedByWarehouse($ingItem, $outputAnchorId)) {
                continue;
            }

            $inType = $ingItem->resolvedProductType();
            if ($inType === ProductType::Finished) {
                throw new \InvalidArgumentException(
                    'يجب أن تكون مكونات الوصفة مواد خام أو تحت التشغيل (صنف منتج تام غير مسموح كمكوّن: '
                    . $ingItem->category_name . ').'
                );
            }
        }
    }

    /**
     * يعتمد على مخزن الصف الفعلي (وليس نوع الأب) — يطابق حركة المخزون والوصفات القديمة.
     */
    private static function ingredientAllowedByWarehouse(Item $ingItem, int $outputAnchorId): bool
    {
        $warehouse = trim((string) ($ingItem->warehouse ?? ''));

        if ($warehouse === self::RAW_WAREHOUSE || $warehouse === self::WIP_WAREHOUSE) {
            return true;
        }

        if ($warehouse === self::FINISHED_WAREHOUSE) {
            $ingAnchorId = $ingItem->parent_item_id
                ? (int) $ingItem->parent_item_id
                : (int) $ingItem->id;

            return $ingAnchorId !== $outputAnchorId;
        }

        return false;
    }
}
