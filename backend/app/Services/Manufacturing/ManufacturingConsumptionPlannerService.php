<?php

namespace App\Services\Manufacturing;

use App\Enums\InventoryMovementType;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\ConfirmedManfucture;
use App\Models\Item;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\CategoryInventoryCostService;
use App\Services\Inventory\InventoryMovementLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Builds and applies per-order raw material consumption lines (with optional overrides).
 */
final class ManufacturingConsumptionPlannerService
{
    public function __construct(
        private ManufacturingConsumptionResolver $resolver,
    ) {
    }

    public function shouldConsumeRawMaterials(Item $outputItem, string $status, bool $stayWipOnly): bool
    {
        $isWipSemiFinished = $outputItem->resolvedProductType() === ProductType::SemiFinished;

        return ! $isWipSemiFinished
            || ($isWipSemiFinished && $stayWipOnly && $status === 'تم الانتهاء');
    }

    /**
     * @return array{
     *     applies: bool,
     *     lines: list<array<string, mixed>>,
     *     total_cost: float,
     *     all_sufficient: bool,
     *     message: string|null
     * }
     */
    public function preview(int $outputProductId, float $batchQty, ?array $overrides = null): array
    {
        if ($batchQty <= 0.0000001) {
            return [
                'applies' => false,
                'lines' => [],
                'total_cost' => 0.0,
                'all_sufficient' => true,
                'message' => 'الكمية يجب أن تكون أكبر من صفر.',
            ];
        }

        try {
            $lines = $this->buildBaseLines($outputProductId, $batchQty);
        } catch (\InvalidArgumentException $e) {
            return [
                'applies' => false,
                'lines' => [],
                'total_cost' => 0.0,
                'all_sufficient' => false,
                'message' => $e->getMessage(),
            ];
        } catch (\RuntimeException $e) {
            return [
                'applies' => false,
                'lines' => [],
                'total_cost' => 0.0,
                'all_sufficient' => false,
                'message' => $e->getMessage(),
            ];
        }

        if ($overrides !== null && $overrides !== []) {
            $lines = $this->applyOverrides($lines, $overrides);
        }

        $totalCost = 0.0;
        $allSufficient = true;
        foreach ($lines as &$line) {
            $totalCost += (float) $line['line_cost'];
            if (! $line['sufficient']) {
                $allSufficient = false;
            }
        }
        unset($line);

        return [
            'applies' => true,
            'lines' => $lines,
            'total_cost' => round($totalCost, 4),
            'all_sufficient' => $allSufficient,
            'message' => null,
        ];
    }

    /**
     * @param  list<array{bom_item_id:int, resolved_category_id:int, quantity:float|int|string}>|null  $overrides
     * @return list<array<string, mixed>>
     */
    public function resolveForExecution(int $outputProductId, float $batchQty, ?array $overrides = null): array
    {
        $lines = $this->buildBaseLines($outputProductId, $batchQty);

        if ($overrides !== null && $overrides !== []) {
            $lines = $this->applyOverrides($lines, $overrides, strict: true);
        }

        foreach ($lines as $line) {
            if ((float) $line['quantity'] > (float) $line['available_quantity'] + 0.00001) {
                throw new \RuntimeException(
                    'رصيد غير كافٍ للمادة «' . $line['item_name'] . '». المطلوب: '
                    . $line['quantity'] . ' — المتاح: ' . $line['available_quantity']
                );
            }
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function applyConsumption(ConfirmedManfucture $confirmed, array $lines, ?string $performedBy = null): float
    {
        $performedBy = $performedBy ?? (auth()->user()->name ?? null);
        $totalRawMaterialCost = 0.0;

        foreach ($lines as $line) {
            $consumedQty = (float) $line['quantity'];
            if ($consumedQty <= 0.0000001) {
                continue;
            }

            $category = Category::query()->lockForUpdate()->find((int) $line['resolved_category_id']);
            if (! $category) {
                continue;
            }

            if ((float) $category->quantity + 0.00001 < $consumedQty) {
                throw new \RuntimeException(
                    'رصيد غير كافٍ للمادة «' . ($line['item_name'] ?? $category->category_name) . '».'
                );
            }

            $unitCost = (float) $line['unit_cost'];
            $lineCost = round($unitCost * $consumedQty, 4);
            $totalRawMaterialCost += $lineCost;

            $warehouseRatings = DB::table('warehouse_ratings')->where('category_id', $category->id)->get();
            $total_price = (float) $category->total_price;
            $neededQuantity = $consumedQty;

            foreach ($warehouseRatings as $product) {
                if ((float) $product->quantity == 0) {
                    continue;
                }
                $availableQuantity = (float) $product->quantity - $neededQuantity;
                if ($availableQuantity <= 0) {
                    $neededQuantity -= (float) $product->quantity;
                    DB::table('warehouse_ratings')->where('id', $product->id)->update(['quantity' => 0]);
                    $total_price -= (float) $product->quantity * (float) $product->price;
                } else {
                    $total_price -= $neededQuantity * (float) $product->price;
                    DB::table('warehouse_ratings')->where('id', $product->id)->increment('quantity', -$neededQuantity);
                    break;
                }
            }

            $category->total_price = $total_price;
            $balanceBefore = (float) $category->quantity;

            DB::table('categories_balance')->insert([
                'invoice_number' => $confirmed->id,
                'category_id' => $category->id,
                'type' => 'تصنيع',
                'quantity' => $consumedQty,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore - $consumedQty,
                'price' => $unitCost,
                'total_price' => $lineCost,
                'unit_cost' => $unitCost,
                'cost_total' => $lineCost,
                'by' => $performedBy,
                'created_at' => now(),
            ]);

            $category->quantity = $balanceBefore - $consumedQty;
            $category->save();
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $category->id);

            $unitMov = $consumedQty > 0 ? ($lineCost / $consumedQty) : 0.0;
            app(InventoryMovementLedgerService::class)->appendOutboundMovement(
                $category->fresh(),
                InventoryMovementType::ProductionRawConsume,
                $consumedQty,
                $unitMov,
                $lineCost,
                'confirmed_manufacture',
                $confirmed->id,
                'استهلاك مواد خام — تصنيع',
                null,
                $performedBy
            );
        }

        return $totalRawMaterialCost;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildBaseLines(int $outputProductId, float $batchQty): array
    {
        $outputItem = Item::query()->findOrFail($outputProductId);
        $productionColorId = $outputItem->color_id ? (int) $outputItem->color_id : null;
        $anchorId = ManufacturingConsumptionResolver::outputAnchorId($outputItem);

        $manufacture = Manufacture::where('product_id', $anchorId)->first()
            ?: Manufacture::where('product_id', $outputProductId)->first();

        if (! $manufacture) {
            throw new \RuntimeException('لا توجد وصفة تصنيع لهذا الصنف.');
        }

        $manproducts = ManufactureProduct::where('manufacture_id', $manufacture->id)->get();
        $recipe = Recipe::where('output_item_id', $anchorId)->first();
        $recipeIngredientMap = [];

        if ($recipe) {
            foreach (RecipeIngredient::where('recipe_id', $recipe->id)->get() as $ri) {
                $recipeIngredientMap[(int) $ri->item_id] = [
                    'quantity' => (float) $ri->quantity,
                    'unit_cost' => (float) ($ri->unit_cost ?? 0),
                ];
            }
        }

        $lines = [];

        foreach ($manproducts as $manproduct) {
            $bomLineItem = Item::find($manproduct->product_id);
            if (! $bomLineItem) {
                continue;
            }

            $resolvedItem = $this->resolver->resolveForProduction($bomLineItem, $productionColorId);
            $category = Category::find($resolvedItem->id);
            if (! $category) {
                continue;
            }

            $recipeLine = $recipeIngredientMap[(int) $manproduct->product_id] ?? null;
            if ($recipeLine !== null && $recipeLine['quantity'] > 0) {
                $bomQty = $recipeLine['quantity'];
                $unitCost = $recipeLine['unit_cost'] > 0
                    ? $recipeLine['unit_cost']
                    : ((float) $manproduct->quantity > 0
                        ? (float) $manproduct->total_price / (float) $manproduct->quantity
                        : 0.0);
            } else {
                $bomQty = (float) $manproduct->quantity;
                $unitCost = $bomQty > 0
                    ? (float) $manproduct->total_price / $bomQty
                    : 0.0;
            }

            if ($bomQty <= 0) {
                continue;
            }

            $defaultQty = round($bomQty * $batchQty, 6);
            $available = (float) ($category->quantity ?? 0);

            $lines[] = [
                'line_key' => (int) $manproduct->product_id . '-' . (int) $resolvedItem->id,
                'bom_item_id' => (int) $manproduct->product_id,
                'resolved_category_id' => (int) $resolvedItem->id,
                'default_resolved_category_id' => (int) $resolvedItem->id,
                'default_item_name' => (string) ($category->category_name ?? $resolvedItem->category_name),
                'item_name' => (string) ($category->category_name ?? $resolvedItem->category_name),
                'warehouse' => (string) ($category->warehouse ?? ''),
                'bom_unit_qty' => round($bomQty, 6),
                'default_quantity' => $defaultQty,
                'quantity' => $defaultQty,
                'unit_cost' => round($unitCost, 6),
                'line_cost' => round($unitCost * $defaultQty, 4),
                'available_quantity' => $available,
                'sufficient' => $available + 0.00001 >= $defaultQty,
                'is_customized' => false,
                'is_substituted' => false,
            ];
        }

        if ($lines === []) {
            throw new \RuntimeException('وصفة التصنيع لا تحتوي على مواد خام.');
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array{bom_item_id:int, resolved_category_id:int, quantity:float|int|string}>  $overrides
     * @return list<array<string, mixed>>
     */
    private function applyOverrides(array $lines, array $overrides, bool $strict = false): array
    {
        $indexedByBom = [];
        foreach ($lines as $idx => $line) {
            $indexedByBom[(int) $line['bom_item_id']] = $idx;
        }

        $seenBomIds = [];
        foreach ($overrides as $override) {
            $bomId = (int) ($override['bom_item_id'] ?? 0);
            $catId = (int) ($override['resolved_category_id'] ?? 0);
            $qty = (float) ($override['quantity'] ?? 0);

            if ($bomId <= 0 || $catId <= 0) {
                if ($strict) {
                    throw new \InvalidArgumentException('سطر استهلاك غير صالح.');
                }

                continue;
            }

            if ($qty < 0) {
                throw new \InvalidArgumentException('كمية الاستهلاك لا يمكن أن تكون سالبة.');
            }

            if (! isset($indexedByBom[$bomId])) {
                if ($strict) {
                    throw new \InvalidArgumentException('مادة غير موجودة في وصفة التصنيع.');
                }

                continue;
            }

            $idx = $indexedByBom[$bomId];
            $line = $lines[$idx];
            $defaultResolvedId = (int) ($line['default_resolved_category_id'] ?? $line['resolved_category_id']);

            if ($catId !== $defaultResolvedId) {
                $line = $this->applyMaterialSubstitution($line, $catId);
            }

            $line['quantity'] = round($qty, 6);
            $line['line_cost'] = round((float) $line['unit_cost'] * $qty, 4);
            $line['sufficient'] = (float) $line['available_quantity'] + 0.00001 >= $qty;
            $line['is_substituted'] = $catId !== $defaultResolvedId;
            $line['is_customized'] = $line['is_substituted']
                || abs($qty - (float) $line['default_quantity']) > 0.000001;

            $lines[$idx] = $line;
            $seenBomIds[$bomId] = true;
        }

        if ($strict && count($seenBomIds) !== count($indexedByBom)) {
            throw new \InvalidArgumentException('يجب إرسال جميع مواد الاستهلاك عند التعديل.');
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function applyMaterialSubstitution(array $line, int $substituteCategoryId): array
    {
        $category = Category::query()->find($substituteCategoryId);
        if (! $category) {
            throw new \InvalidArgumentException('الصنف البديل غير موجود.');
        }

        $item = Item::query()->find($substituteCategoryId);
        if ($item && $item->resolvedProductType() === ProductType::Finished) {
            throw new \InvalidArgumentException(
                'لا يمكن استبدال المادة بمنتج تام: ' . ($category->category_name ?? '')
            );
        }

        $line['resolved_category_id'] = $substituteCategoryId;
        $line['item_name'] = (string) ($category->category_name ?? '');
        $line['warehouse'] = (string) ($category->warehouse ?? '');
        $line['available_quantity'] = (float) ($category->quantity ?? 0);
        $line['unit_cost'] = round($this->unitCostForCategory($category), 6);

        return $line;
    }

    private function unitCostForCategory(Category $category): float
    {
        $qty = (float) ($category->quantity ?? 0);
        $total = (float) ($category->total_price ?? 0);
        if ($qty > 0.0000001) {
            return $total / $qty;
        }

        return (float) ($category->unit_price ?? 0);
    }
}
