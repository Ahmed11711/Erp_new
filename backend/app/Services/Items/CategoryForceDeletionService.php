<?php

namespace App\Services\Items;

use App\Models\Category;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\OrderProduct;
use App\Models\RecipeIngredient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CategoryForceDeletionService
{
    /** @var array<string, int> */
    private array $removed = [];

    /**
     * @param  list<int>  $categoryIds
     * @return array{
     *   categories: int,
     *   category_ids: list<int>,
     *   links: array<string, int>,
     *   total_links: int,
     *   warnings: list<string>
     * }
     */
    public function preview(array $categoryIds): array
    {
        $ids = $this->normalizeIds($categoryIds);
        if ($ids === []) {
            return [
                'categories' => 0,
                'category_ids' => [],
                'links' => [],
                'total_links' => 0,
                'warnings' => ['لا توجد أصناف.'],
            ];
        }

        $links = $this->countAllLinks($ids);

        return [
            'categories' => count($ids),
            'category_ids' => $ids,
            'links' => $links,
            'total_links' => array_sum($links),
            'warnings' => $this->buildWarnings($links),
        ];
    }

    /**
     * @param  list<int>  $categoryIds
     * @return array{categories_deleted: int, links_removed: array<string, int>}
     */
    public function deleteMany(array $categoryIds): array
    {
        $ids = $this->normalizeIds($categoryIds);
        if ($ids === []) {
            throw new \InvalidArgumentException('لا توجد أصناف للحذف.');
        }

        $this->removed = [];

        DB::transaction(function () use ($ids) {
            $this->purgeAllLinks($ids);
            $this->deleteCategories($ids);
        });

        return [
            'categories_deleted' => count($ids),
            'links_removed' => $this->removed,
        ];
    }

    /**
     * @return list<int>
     */
    public function categoryIdsForWarehouse(string $warehouse): array
    {
        $warehouse = trim($warehouse);
        if ($warehouse === '') {
            return [];
        }

        return Category::query()
            ->where('warehouse', $warehouse)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    private function normalizeIds(array $categoryIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $categoryIds), fn (int $id) => $id > 0)));
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, int>
     */
    private function countAllLinks(array $ids): array
    {
        $counts = [];

        $counts['order_products'] = OrderProduct::query()->whereIn('category_id', $ids)->count();
        $counts['manufacture_products'] = ManufactureProduct::query()->whereIn('product_id', $ids)->count();
        $counts['manufactures'] = Manufacture::query()->whereIn('product_id', $ids)->count();
        $counts['recipe_ingredients'] = RecipeIngredient::query()->whereIn('item_id', $ids)->count();

        if (Schema::hasTable('recipes') && Schema::hasColumn('recipes', 'output_item_id')) {
            $counts['recipes'] = (int) DB::table('recipes')->whereIn('output_item_id', $ids)->count();
        }

        foreach ($this->linkedTables() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column => $mode) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $key = $table.'.'.$column;
                $counts[$key] = (int) DB::table($table)->whereIn($column, $ids)->count();
            }
        }

        return array_filter($counts, fn (int $n) => $n > 0);
    }

    /**
     * @param  array<string, int>  $links
     * @return list<string>
     */
    private function buildWarnings(array $links): array
    {
        $warnings = [
            'سيتم حذف الأصناف نهائياً ولا يمكن التراجع.',
            'أرصدة المخزون على مستوى هذه الأصناف ستُزال؛ قد يبقى فرق في حساب المخزون العام في الشجرة المحاسبية.',
        ];

        if (($links['order_products'] ?? 0) > 0 || ($links['order_product_archives.category_id'] ?? 0) > 0) {
            $warnings[] = 'طلبات المبيعات: سيتم حذف بنود الطلبات المرتبطة بهذه الأصناف.';
        }
        if (($links['manufactures'] ?? 0) > 0 || ($links['manufacture_products'] ?? 0) > 0 || ($links['confirmed_manfuctures.product_id'] ?? 0) > 0) {
            $warnings[] = 'التصنيع: سيتم حذف أوامر/تفاصيل التصنيع المرتبطة.';
        }
        if (($links['recipe_ingredients'] ?? 0) > 0 || ($links['recipes'] ?? 0) > 0) {
            $warnings[] = 'الوصفات: سيتم حذف مكوّنات الوصفات والوصفات التي يُنتج هذا الصنف ناتجها.';
        }
        if (($links['stock_movements.category_id'] ?? 0) > 0 || ($links['inventory_movements.category_id'] ?? 0) > 0) {
            $warnings[] = 'المخزون: سيتم حذف سجل حركات المخزون والأرصدة التفصيلية.';
        }
        if (($links['invoice_categories.category_id'] ?? 0) > 0) {
            $warnings[] = 'المشتريات: سيتم حذف بنود فواتير الشراء المرتبطة.';
        }
        if ($this->hasProcessingLinks($links)) {
            $warnings[] = 'التشغيل الخارجي: سيتم حذف سطور وأرصدة التشغيل المرتبطة.';
        }
        if (($links['shopify_products.category_id'] ?? 0) > 0 || ($links['shopify_product_mappings.category_id'] ?? 0) > 0) {
            $warnings[] = 'Shopify: سيتم حذف ربط المنتجات.';
        }

        return $warnings;
    }

    /**
     * @param  array<string, int>  $links
     */
    private function hasProcessingLinks(array $links): bool
    {
        foreach ($links as $key => $count) {
            if ($count > 0 && str_starts_with($key, 'processing_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $ids
     */
    private function purgeAllLinks(array $ids): void
    {
        $this->purgeProcessingModule($ids);
        $this->purgeRecipes($ids);
        $this->purgeManufacturing($ids);
        $this->purgeOrderLines($ids);
        $this->purgeStockAndInventory($ids);
        $this->purgePurchases($ids);
        $this->purgeShopify($ids);
        $this->purgeSimpleLinkedRows($ids);
        $this->nullifyCategorySelfReferences($ids);
    }

    /**
     * @param  list<int>  $ids
     */
    private function purgeProcessingModule(array $ids): void
    {
        $tables = [
            'processing_material_balances',
            'processing_dispatch_lines',
            'processing_receipt_lines',
            'processing_invoice_lines',
            'processing_order_lines',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (['category_id', 'at_vendor_category_id', 'destination_category_id', 'rejection_return_category_id'] as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $this->countDelete(
                    "{$table}.{$column}",
                    (int) DB::table($table)->whereIn($column, $ids)->delete()
                );
            }
        }

        foreach ($this->linkedTables() as $table => $columns) {
            if (! str_starts_with($table, 'processing_') || ! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column => $mode) {
                if ($mode !== 'nullify' || ! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $this->countDelete(
                    "{$table}.{$column} (null)",
                    (int) DB::table($table)->whereIn($column, $ids)->update([$column => null])
                );
            }
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function purgeRecipes(array $ids): void
    {
        if (Schema::hasTable('recipes') && Schema::hasColumn('recipes', 'output_item_id')) {
            $recipeIds = DB::table('recipes')->whereIn('output_item_id', $ids)->pluck('id');
            if ($recipeIds->isNotEmpty()) {
                if (Schema::hasTable('recipe_ingredients')) {
                    $this->countDelete(
                        'recipe_ingredients (by recipe)',
                        (int) DB::table('recipe_ingredients')->whereIn('recipe_id', $recipeIds)->delete()
                    );
                }
                if (Schema::hasColumn('categories', 'recipe_id')) {
                    Category::query()
                        ->whereIn('recipe_id', $recipeIds)
                        ->whereNotIn('id', $ids)
                        ->update(['recipe_id' => null]);
                }
                $this->countDelete('recipes', (int) DB::table('recipes')->whereIn('id', $recipeIds)->delete());
            }
        }

        if (Schema::hasTable('recipe_ingredients')) {
            $this->countDelete(
                'recipe_ingredients',
                (int) RecipeIngredient::query()->whereIn('item_id', $ids)->delete()
            );
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function purgeManufacturing(array $ids): void
    {
        $manufactureIds = Manufacture::query()->whereIn('product_id', $ids)->pluck('id');
        if ($manufactureIds->isNotEmpty()) {
            $this->countDelete(
                'manufacture_products (by manufacture)',
                (int) ManufactureProduct::query()->whereIn('manufacture_id', $manufactureIds)->delete()
            );
            $this->countDelete(
                'manufactures',
                (int) Manufacture::query()->whereIn('id', $manufactureIds)->delete()
            );
        }

        $this->countDelete(
            'manufacture_products',
            (int) ManufactureProduct::query()->whereIn('product_id', $ids)->delete()
        );

        if (Schema::hasTable('confirmed_manfuctures') && Schema::hasColumn('confirmed_manfuctures', 'product_id')) {
            $this->countDelete(
                'confirmed_manfuctures',
                (int) DB::table('confirmed_manfuctures')->whereIn('product_id', $ids)->delete()
            );
        }

        if (Schema::hasTable('production_orders') && Schema::hasColumn('production_orders', 'output_product_id')) {
            $this->countDelete(
                'production_orders',
                (int) DB::table('production_orders')->whereIn('output_product_id', $ids)->delete()
            );
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function purgeOrderLines(array $ids): void
    {
        $this->countDelete(
            'order_products',
            (int) OrderProduct::query()->whereIn('category_id', $ids)->delete()
        );

        if (Schema::hasTable('order_product_archives') && Schema::hasColumn('order_product_archives', 'category_id')) {
            $this->countDelete(
                'order_product_archives',
                (int) DB::table('order_product_archives')->whereIn('category_id', $ids)->delete()
            );
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function purgeStockAndInventory(array $ids): void
    {
        $tables = [
            'stock_movements',
            'inventory_movements',
            'categories_balance',
            'category_monthly_inventories',
            'warehouse_ratings',
            'inventory_balances',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'category_id')) {
                continue;
            }
            $this->countDelete($table, (int) DB::table($table)->whereIn('category_id', $ids)->delete());
        }

        if (Schema::hasTable('stock_transaction_items')) {
            foreach (['product_id', 'to_product_id'] as $column) {
                if (! Schema::hasColumn('stock_transaction_items', $column)) {
                    continue;
                }
                $this->countDelete(
                    "stock_transaction_items.{$column}",
                    (int) DB::table('stock_transaction_items')->whereIn($column, $ids)->delete()
                );
            }
        }

        if (Schema::hasTable('category_descriptions') && Schema::hasColumn('category_descriptions', 'category_id')) {
            $this->countDelete(
                'category_descriptions',
                (int) DB::table('category_descriptions')->whereIn('category_id', $ids)->delete()
            );
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function purgePurchases(array $ids): void
    {
        if (Schema::hasTable('invoice_categories') && Schema::hasColumn('invoice_categories', 'category_id')) {
            $this->countDelete(
                'invoice_categories',
                (int) DB::table('invoice_categories')->whereIn('category_id', $ids)->delete()
            );
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function purgeShopify(array $ids): void
    {
        if (Schema::hasTable('shopify_product_mappings') && Schema::hasColumn('shopify_product_mappings', 'category_id')) {
            $this->countDelete(
                'shopify_product_mappings',
                (int) DB::table('shopify_product_mappings')->whereIn('category_id', $ids)->delete()
            );
        }
        if (Schema::hasTable('shopify_products') && Schema::hasColumn('shopify_products', 'category_id')) {
            $this->countDelete(
                'shopify_products',
                (int) DB::table('shopify_products')->whereIn('category_id', $ids)->delete()
            );
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function purgeSimpleLinkedRows(array $ids): void
    {
        foreach ($this->linkedTables() as $table => $columns) {
            if (str_starts_with($table, 'processing_') || ! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column => $mode) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                if ($mode === 'nullify') {
                    $this->countDelete(
                        "{$table}.{$column} (null)",
                        (int) DB::table($table)->whereIn($column, $ids)->update([$column => null])
                    );
                } else {
                    $this->countDelete(
                        "{$table}.{$column}",
                        (int) DB::table($table)->whereIn($column, $ids)->delete()
                    );
                }
            }
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function nullifyCategorySelfReferences(array $ids): void
    {
        $columns = ['parent_item_id', 'lineage_root_id', 'replaces_item_id', 'replaced_by_item_id'];
        foreach ($columns as $column) {
            if (! Schema::hasColumn('categories', $column)) {
                continue;
            }
            Category::query()
                ->whereIn($column, $ids)
                ->update([$column => null]);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function deleteCategories(array $ids): void
    {
        if (Schema::hasTable('category_color') && Schema::hasColumn('category_color', 'category_id')) {
            $this->countDelete(
                'category_color',
                (int) DB::table('category_color')->whereIn('category_id', $ids)->delete()
            );
        }

        $this->countDelete('categories', (int) Category::query()->whereIn('id', $ids)->delete());
    }

    /**
     * @return array<string, array<string, string>> table => [column => 'delete'|'nullify']
     */
    private function linkedTables(): array
    {
        return [
            'order_product_archives' => ['category_id' => 'delete'],
            'confirmed_manfuctures' => ['product_id' => 'delete'],
            'categories_balance' => ['category_id' => 'delete'],
            'category_monthly_inventories' => ['category_id' => 'delete'],
            'warehouse_ratings' => ['category_id' => 'delete'],
            'stock_movements' => ['category_id' => 'delete'],
            'inventory_movements' => ['category_id' => 'delete'],
            'stock_transaction_items' => ['product_id' => 'delete', 'to_product_id' => 'nullify'],
            'invoice_categories' => ['category_id' => 'delete'],
            'inventory_count_import_rows' => ['matched_category_id' => 'nullify'],
            'production_orders' => ['output_product_id' => 'delete'],
            'recipes' => ['output_item_id' => 'delete'],
            'shopify_products' => ['category_id' => 'delete'],
            'shopify_product_mappings' => ['category_id' => 'delete'],
            'processing_order_lines' => [
                'category_id' => 'delete',
                'destination_category_id' => 'nullify',
                'at_vendor_category_id' => 'nullify',
            ],
            'processing_dispatch_lines' => ['category_id' => 'delete', 'at_vendor_category_id' => 'delete'],
            'processing_receipt_lines' => [
                'category_id' => 'delete',
                'at_vendor_category_id' => 'delete',
                'destination_category_id' => 'delete',
                'rejection_return_category_id' => 'nullify',
            ],
            'processing_material_balances' => ['category_id' => 'delete', 'at_vendor_category_id' => 'delete'],
        ];
    }

    private function countDelete(string $key, int $count): void
    {
        if ($count <= 0) {
            return;
        }
        $this->removed[$key] = ($this->removed[$key] ?? 0) + $count;
    }
}
