<?php

namespace App\Services\Items;

use App\Models\Category;
use App\Models\Item;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\Recipe;
use App\Services\CategoryInventoryCostService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ItemRecipeRevisionService
{
    public function __construct(
        private RecipeCloningService $recipeCloning,
        private ItemCodeService $itemCodes,
    ) {
    }

    /**
     * إنشاء صنف جديد (إصدار أحدث) بعد تغيير الوصفة، مع تصفير مخزون النسخة القديمة
     * وربط الأكواد برقم إصدار. النسخة الجديدة تبدأ برصيد صفر للسحب من المخزون لاحقاً.
     *
     * @param  array{new_item_code?: string, clone_recipe?: bool, duplicate_manufacture?: bool}  $options
     */
    public function rollForward(Item $oldItem, array $options = []): Item
    {
        $cloneRecipe = $options['clone_recipe'] ?? true;
        $duplicateManufacture = $options['duplicate_manufacture'] ?? true;
        $explicitCode = isset($options['new_item_code']) ? trim((string) $options['new_item_code']) : null;

        if ($oldItem->replaced_by_item_id) {
            throw new \InvalidArgumentException('هذا الصنف مستبدل مسبقاً بالصنف #'.$oldItem->replaced_by_item_id);
        }

        return DB::transaction(function () use ($oldItem, $cloneRecipe, $duplicateManufacture, $explicitCode) {
            $lockedOld = $oldItem->newQuery()->whereKey($oldItem->id)->lockForUpdate()->firstOrFail();

            if ($lockedOld->lineage_root_id === null) {
                $lockedOld->lineage_root_id = $lockedOld->id;
                $lockedOld->save();
            }

            $nextRevision = (int) $lockedOld->item_revision + 1;
            $lineageRoot = $lockedOld->lineage_root_id ?? $lockedOld->id;

            $newItem = $lockedOld->replicate([
                'quantity',
                'total_price',
                'sell_total_price',
                'item_code',
                'recipe_id',
                'item_revision',
                'lineage_root_id',
                'replaces_item_id',
                'replaced_by_item_id',
            ]);

            $newItem->quantity = 0;
            $newItem->total_price = 0;
            $newItem->sell_total_price = 0;
            $newItem->item_revision = $nextRevision;
            $newItem->lineage_root_id = $lineageRoot;
            $newItem->replaces_item_id = $lockedOld->id;
            $newItem->replaced_by_item_id = null;

            if ($cloneRecipe && $lockedOld->recipe_id) {
                $src = Recipe::query()->with('ingredients')->find($lockedOld->recipe_id);
                if ($src) {
                    $cloned = $this->recipeCloning->duplicate(
                        $src,
                        $src->recipe_name.' — إصدار '.$nextRevision
                    );
                    $newItem->recipe_id = $cloned->id;
                }
            } else {
                $newItem->recipe_id = $lockedOld->recipe_id;
            }

            if ($explicitCode !== null && $explicitCode !== '') {
                $this->itemCodes->assertCodeAvailable($explicitCode, null);
                $newItem->item_code = $explicitCode;
            } else {
                $newItem->item_code = $this->itemCodes->suggestSuccessorCode($lockedOld, $nextRevision);
            }

            $newItem->save();

            if ($duplicateManufacture) {
                $this->duplicateManufactureIfExists($lockedOld, $newItem);
            }

            $this->zeroSupersededItemStock($lockedOld, $newItem);

            $lockedOld->replaced_by_item_id = $newItem->id;
            $ref = trim((string) ($lockedOld->ref ?? ''));
            $lockedOld->ref = ($ref !== '' ? $ref.' | ' : '').'مستبدل بـ#'.$newItem->id;
            $lockedOld->save();

            return $newItem->fresh(['recipe.ingredients']);
        });
    }

    private function duplicateManufactureIfExists(Item $oldItem, Item $newItem): void
    {
        $m = Manufacture::query()->where('product_id', $oldItem->id)->with('manufacture_products')->first();
        if (! $m) {
            return;
        }

        if (Manufacture::query()->where('product_id', $newItem->id)->exists()) {
            return;
        }

        $nm = Manufacture::query()->create([
            'product_id' => $newItem->id,
            'total' => $m->total,
        ]);

        foreach ($m->manufacture_products as $line) {
            ManufactureProduct::query()->create([
                'manufacture_id' => $nm->id,
                'product_id' => $line->product_id,
                'quantity' => $line->quantity,
                'total_price' => $line->total_price,
            ]);
        }
    }

    private function zeroSupersededItemStock(Category $old, Item $newItem): void
    {
        $qtyBefore = (float) ($old->quantity ?? 0);
        if ($qtyBefore <= 0.0000001) {
            DB::table('warehouse_ratings')->where('category_id', $old->id)->update(['quantity' => 0]);
            $old->quantity = 0;
            $old->save();

            return;
        }

        $unitCost = CategoryInventoryCostService::resolveReferenceUnitCost((int) $old->id);
        $by = Auth::check() ? Auth::user()->name : 'النظام';

        DB::table('categories_balance')->insert([
            'invoice_number' => 'REV-'.$newItem->id,
            'category_id' => $old->id,
            'type' => 'استبدال إصدار صنف',
            'quantity' => -$qtyBefore,
            'balance_before' => $qtyBefore,
            'balance_after' => 0,
            'price' => $unitCost,
            'total_price' => -1 * $qtyBefore * $unitCost,
            'unit_cost' => $unitCost,
            'cost_total' => -1 * $qtyBefore * $unitCost,
            'by' => $by,
            'created_at' => now(),
        ]);

        $old->quantity = 0;
        if ($old->warehouse === 'مخزن منتج تام') {
            $old->sell_total_price = 0;
        } else {
            $old->total_price = 0;
        }
        $old->save();

        DB::table('warehouse_ratings')->where('category_id', $old->id)->update(['quantity' => 0]);

        if ($old->warehouse !== 'مخزن منتج تام') {
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $old->id);
        }
    }

    /**
     * @return list<Item>
     */
    public function lineageVersions(int $categoryId): array
    {
        $item = Item::query()->find($categoryId);
        if (! $item) {
            return [];
        }

        $rootId = (int) ($item->lineage_root_id ?? $item->id);

        return Item::query()
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('lineage_root_id', $rootId);
            })
            ->orderBy('item_revision')
            ->orderBy('id')
            ->get()
            ->all();
    }
}
