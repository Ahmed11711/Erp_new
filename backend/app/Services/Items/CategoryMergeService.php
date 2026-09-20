<?php

namespace App\Services\Items;

use App\Models\Category;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\OrderProduct;
use App\Models\RecipeIngredient;
use App\Services\CategoryInventoryCostService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CategoryMergeService
{
    /**
     * @return array{valid: bool, errors: list<string>, source: ?array, target: ?array, links: array<string, int>}
     */
    public function preview(int $sourceId, int $targetId): array
    {
        $errors = $this->validatePair($sourceId, $targetId);
        if ($errors !== []) {
            return [
                'valid' => false,
                'errors' => $errors,
                'source' => null,
                'target' => null,
                'links' => [],
            ];
        }

        $source = Category::query()->findOrFail($sourceId);
        $target = Category::query()->findOrFail($targetId);

        return [
            'valid' => true,
            'errors' => [],
            'source' => $this->categorySummary($source),
            'target' => $this->categorySummary($target),
            'links' => $this->countLinks($sourceId),
            'merged_quantity' => round((float) ($source->quantity ?? 0) + (float) ($target->quantity ?? 0), 6),
            'merged_total_price' => round((float) ($source->total_price ?? 0) + (float) ($target->total_price ?? 0), 4),
        ];
    }

    /**
     * @return array{source_id: int, target_id: int, links_moved: array<string, int>, merged_quantity: float}
     *
     * @throws \InvalidArgumentException
     */
    public function merge(int $sourceId, int $targetId): array
    {
        $errors = $this->validatePair($sourceId, $targetId);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        return DB::transaction(function () use ($sourceId, $targetId) {
            $source = Category::query()->whereKey($sourceId)->lockForUpdate()->firstOrFail();
            $target = Category::query()->whereKey($targetId)->lockForUpdate()->firstOrFail();

            $errors = $this->validatePair((int) $source->id, (int) $target->id);
            if ($errors !== []) {
                throw new \InvalidArgumentException(implode(' ', $errors));
            }

            $linksMoved = $this->reassignAllLinks($sourceId, $targetId);
            $this->mergeInventoryBalances($sourceId, $targetId);
            $this->mergeCategoryQuantities($source, $target);
            $this->repointCategoryVariants($sourceId, $targetId);
            $this->clearCategorySelfReferences($sourceId, $targetId);

            $source->delete();

            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($targetId);

            $target->refresh();

            return [
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'links_moved' => $linksMoved,
                'merged_quantity' => (float) ($target->quantity ?? 0),
                'merged_total_price' => (float) ($target->total_price ?? 0),
                'target' => $this->categorySummary($target),
            ];
        });
    }

    /**
     * Merge several duplicate categories into one canonical target in a single transaction.
     *
     * @param  list<int>  $sourceIds
     * @return array{target_id: int, merged_count: int, merged_source_ids: list<int>, merged_quantity: float, target: array}
     *
     * @throws \InvalidArgumentException
     */
    public function mergeMany(int $targetId, array $sourceIds): array
    {
        $sourceIds = array_values(array_unique(array_map('intval', $sourceIds)));
        $sourceIds = array_values(array_filter($sourceIds, fn (int $id) => $id > 0 && $id !== $targetId));

        if ($sourceIds === []) {
            throw new \InvalidArgumentException('لا توجد أصناف مصدر صالحة للدمج.');
        }

        foreach ($sourceIds as $sourceId) {
            $errors = $this->validatePair($sourceId, $targetId);
            if ($errors !== []) {
                throw new \InvalidArgumentException(implode(' ', $errors));
            }
        }

        return DB::transaction(function () use ($targetId, $sourceIds) {
            foreach ($sourceIds as $sourceId) {
                $this->merge($sourceId, $targetId);
            }

            $target = Category::query()->findOrFail($targetId);

            return [
                'target_id' => $targetId,
                'merged_count' => count($sourceIds),
                'merged_source_ids' => $sourceIds,
                'merged_quantity' => (float) ($target->quantity ?? 0),
                'target' => $this->categorySummary($target),
            ];
        });
    }

    /**
     * Find groups of duplicate categories (same normalized name within the same warehouse).
     *
     * @return list<array{canonical_id: int, name: string, warehouse: ?string, stock_id: ?int, members: list<array>}>
     */
    public function duplicateGroups(?int $stockId = null): array
    {
        $query = Category::query()->orderBy('id');
        if ($stockId !== null && $stockId > 0) {
            $query->where('stock_id', $stockId);
        }

        $byKey = [];
        foreach ($query->get() as $category) {
            $key = ((int) ($category->stock_id ?? 0)).'|'.$this->normalizeName((string) $category->category_name);
            $byKey[$key][] = $category;
        }

        $groups = [];
        foreach ($byKey as $members) {
            if (count($members) < 2) {
                continue;
            }

            $canonical = $this->pickCanonical($members);

            $groups[] = [
                'canonical_id' => (int) $canonical->id,
                'name' => (string) $canonical->category_name,
                'warehouse' => $canonical->warehouse,
                'stock_id' => $canonical->stock_id !== null ? (int) $canonical->stock_id : null,
                'members' => array_map(function (Category $member) {
                    return [
                        'id' => (int) $member->id,
                        'category_name' => (string) $member->category_name,
                        'item_code' => $member->item_code,
                        'warehouse' => $member->warehouse,
                        'stock_id' => $member->stock_id !== null ? (int) $member->stock_id : null,
                        'quantity' => (float) ($member->quantity ?? 0),
                        'links' => array_sum($this->countLinks((int) $member->id)),
                    ];
                }, array_values($members)),
            ];
        }

        return $groups;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return mb_strtolower($name);
    }

    /**
     * Prefer the row with item_code, then highest quantity, then lowest id.
     *
     * @param  list<Category>  $group
     */
    private function pickCanonical(array $group): Category
    {
        usort($group, function (Category $a, Category $b) {
            $aCode = $a->item_code ? 1 : 0;
            $bCode = $b->item_code ? 1 : 0;
            if ($aCode !== $bCode) {
                return $bCode <=> $aCode;
            }

            $qtyCmp = ((float) ($b->quantity ?? 0)) <=> ((float) ($a->quantity ?? 0));
            if ($qtyCmp !== 0) {
                return $qtyCmp;
            }

            return ((int) $a->id) <=> ((int) $b->id);
        });

        return $group[0];
    }

    /**
     * @return list<string>
     */
    public function validatePair(int $sourceId, int $targetId): array
    {
        if ($sourceId <= 0 || $targetId <= 0) {
            return ['معرّفات الأصناف غير صالحة.'];
        }

        if ($sourceId === $targetId) {
            return ['لا يمكن دمج الصنف مع نفسه.'];
        }

        $source = Category::query()->find($sourceId);
        $target = Category::query()->find($targetId);

        if (! $source) {
            return ['الصنف المصدر غير موجود.'];
        }
        if (! $target) {
            return ['الصنف المستهدف غير موجود.'];
        }

        if ((int) $source->stock_id !== (int) $target->stock_id) {
            return ['يجب أن يكون الصنفان في نفس المخزن لدمجهما.'];
        }

        return [];
    }

    /**
     * @return array<string, int>
     */
    public function countLinks(int $categoryId): array
    {
        $counts = [];

        $counts['order_products'] = OrderProduct::query()->where('category_id', $categoryId)->count();
        $counts['manufacture_products'] = ManufactureProduct::query()->where('product_id', $categoryId)->count();
        $counts['manufactures'] = Manufacture::query()->where('product_id', $categoryId)->count();
        $counts['recipe_ingredients'] = RecipeIngredient::query()->where('item_id', $categoryId)->count();

        foreach ($this->simpleReassignments() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $key = $table.'.'.$column;
                $counts[$key] = (int) DB::table($table)->where($column, $categoryId)->count();
            }
        }

        return array_filter($counts, fn (int $n) => $n > 0);
    }

    /**
     * @return array<string, int>
     */
    private function reassignAllLinks(int $sourceId, int $targetId): array
    {
        $moved = [];

        $moved['order_products'] = OrderProduct::query()
            ->where('category_id', $sourceId)
            ->update(['category_id' => $targetId]);

        $moved['manufacture_products'] = ManufactureProduct::query()
            ->where('product_id', $sourceId)
            ->update(['product_id' => $targetId]);

        $moved['manufactures'] = Manufacture::query()
            ->where('product_id', $sourceId)
            ->update(['product_id' => $targetId]);

        $moved['recipe_ingredients'] = $this->mergeRecipeIngredients($sourceId, $targetId);

        foreach ($this->simpleReassignments() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $key = $table.'.'.$column;
                $moved[$key] = (int) DB::table($table)
                    ->where($column, $sourceId)
                    ->update([$column => $targetId]);
            }
        }

        $this->mergeCategoryColors($sourceId, $targetId);
        $this->mergeShopifyMappings($sourceId, $targetId);

        return array_filter($moved, fn (int $n) => $n > 0);
    }

    /**
     * @return array<string, list<string>>
     */
    private function simpleReassignments(): array
    {
        return [
            'order_product_archives' => ['category_id'],
            'confirmed_manfuctures' => ['product_id'],
            'categories_balance' => ['category_id'],
            'category_monthly_inventories' => ['category_id'],
            'warehouse_ratings' => ['category_id'],
            'stock_movements' => ['category_id'],
            'inventory_movements' => ['category_id'],
            'stock_transaction_items' => ['product_id', 'to_product_id'],
            'invoice_categories' => ['category_id'],
            'inventory_count_import_rows' => ['matched_category_id'],
            'production_orders' => ['output_product_id'],
            'recipes' => ['output_item_id'],
            'shopify_products' => ['category_id'],
            'processing_order_lines' => ['category_id', 'destination_category_id', 'at_vendor_category_id'],
            'processing_dispatch_lines' => ['category_id', 'at_vendor_category_id'],
            'processing_receipt_lines' => [
                'category_id',
                'at_vendor_category_id',
                'destination_category_id',
                'rejection_return_category_id',
            ],
            'processing_material_balances' => ['category_id', 'at_vendor_category_id'],
        ];
    }

    private function mergeRecipeIngredients(int $sourceId, int $targetId): int
    {
        if (! Schema::hasTable('recipe_ingredients')) {
            return 0;
        }

        $moved = 0;
        $rows = DB::table('recipe_ingredients')->where('item_id', $sourceId)->get();

        foreach ($rows as $row) {
            $existing = DB::table('recipe_ingredients')
                ->where('recipe_id', $row->recipe_id)
                ->where('item_id', $targetId)
                ->first();

            if ($existing) {
                DB::table('recipe_ingredients')
                    ->where('id', $existing->id)
                    ->update([
                        'quantity' => (float) $existing->quantity + (float) $row->quantity,
                    ]);
                DB::table('recipe_ingredients')->where('id', $row->id)->delete();
            } else {
                DB::table('recipe_ingredients')
                    ->where('id', $row->id)
                    ->update(['item_id' => $targetId]);
            }
            $moved++;
        }

        return $moved;
    }

    private function mergeCategoryColors(int $sourceId, int $targetId): void
    {
        if (! Schema::hasTable('category_color')) {
            return;
        }

        $sourceColors = DB::table('category_color')->where('category_id', $sourceId)->get();
        foreach ($sourceColors as $row) {
            $exists = DB::table('category_color')
                ->where('category_id', $targetId)
                ->where('color_id', $row->color_id)
                ->exists();

            if ($exists) {
                DB::table('category_color')->where('id', $row->id)->delete();
            } else {
                DB::table('category_color')->where('id', $row->id)->update(['category_id' => $targetId]);
            }
        }
    }

    private function mergeShopifyMappings(int $sourceId, int $targetId): void
    {
        if (Schema::hasTable('shopify_product_mappings')) {
            $targetHas = DB::table('shopify_product_mappings')->where('category_id', $targetId)->exists();
            if ($targetHas) {
                DB::table('shopify_product_mappings')->where('category_id', $sourceId)->delete();
            } else {
                DB::table('shopify_product_mappings')
                    ->where('category_id', $sourceId)
                    ->update(['category_id' => $targetId]);
            }
        }

        if (Schema::hasTable('shopify_products')) {
            DB::table('shopify_products')
                ->where('category_id', $sourceId)
                ->update(['category_id' => $targetId]);
        }
    }

    private function mergeInventoryBalances(int $sourceId, int $targetId): void
    {
        if (! Schema::hasTable('inventory_balances')) {
            return;
        }

        $sourceBal = DB::table('inventory_balances')->where('category_id', $sourceId)->first();
        if (! $sourceBal) {
            return;
        }

        $targetBal = DB::table('inventory_balances')->where('category_id', $targetId)->first();
        if ($targetBal) {
            DB::table('inventory_balances')->where('id', $targetBal->id)->update([
                'quantity' => (float) $targetBal->quantity + (float) $sourceBal->quantity,
                'cost_value' => (float) $targetBal->cost_value + (float) $sourceBal->cost_value,
            ]);
            DB::table('inventory_balances')->where('id', $sourceBal->id)->delete();
        } else {
            DB::table('inventory_balances')
                ->where('id', $sourceBal->id)
                ->update(['category_id' => $targetId]);
        }
    }

    private function mergeCategoryQuantities(Category $source, Category $target): void
    {
        $srcQty = (float) ($source->quantity ?? 0);
        $tgtQty = (float) ($target->quantity ?? 0);
        $srcTp = (float) ($source->total_price ?? 0);
        $tgtTp = (float) ($target->total_price ?? 0);
        $srcSell = (float) ($source->sell_total_price ?? 0);
        $tgtSell = (float) ($target->sell_total_price ?? 0);

        $mergedQty = $srcQty + $tgtQty;
        $mergedTp = $srcTp + $tgtTp;
        $mergedSell = $srcSell + $tgtSell;

        $updates = [
            'quantity' => $mergedQty,
            'total_price' => $mergedTp,
            'sell_total_price' => $mergedSell,
        ];

        if (! $target->item_code && $source->item_code) {
            $updates['item_code'] = $source->item_code;
        }

        $target->update($updates);
    }

    private function repointCategoryVariants(int $sourceId, int $targetId): void
    {
        Category::query()
            ->where('parent_item_id', $sourceId)
            ->update(['parent_item_id' => $targetId]);
    }

    private function clearCategorySelfReferences(int $sourceId, int $targetId): void
    {
        $columns = ['parent_item_id', 'lineage_root_id', 'replaces_item_id', 'replaced_by_item_id'];

        foreach ($columns as $column) {
            if (! Schema::hasColumn('categories', $column)) {
                continue;
            }

            Category::query()
                ->where($column, $sourceId)
                ->update([$column => $targetId]);

            Category::query()
                ->where('id', $targetId)
                ->where($column, $sourceId)
                ->update([$column => null]);
        }
    }

    /**
     * @return array{id: int, category_name: string, item_code: ?string, warehouse: ?string, stock_id: ?int, quantity: float, total_price: float}
     */
    private function categorySummary(Category $category): array
    {
        return [
            'id' => (int) $category->id,
            'category_name' => (string) $category->category_name,
            'item_code' => $category->item_code,
            'warehouse' => $category->warehouse,
            'stock_id' => $category->stock_id !== null ? (int) $category->stock_id : null,
            'quantity' => (float) ($category->quantity ?? 0),
            'total_price' => (float) ($category->total_price ?? 0),
        ];
    }
}
