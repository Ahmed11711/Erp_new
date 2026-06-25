<?php

namespace App\Services\Manufacturing;

use App\Enums\ProductType;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lists finished / semi-finished base items that have no recipe (Recipe, Manufacture, or recipe_id link).
 */
final class ItemsWithoutRecipeReportService
{
    private const FINISHED_WAREHOUSE = 'مخزن منتج تام';

    private const WIP_WAREHOUSE = 'مخزن منتج تحت التشغيل';

    /**
     * @return array{data: list<array<string, mixed>>, totals: array{items_count: int}}
     */
    public function report(?string $warehouse = null, ?string $productType = null, ?string $search = null): array
    {
        $query = $this->baseQuery($warehouse, $productType, $search);

        $rows = $query
            ->orderBy('category_name')
            ->get([
                'id',
                'category_name',
                'item_code',
                'warehouse',
                'product_type',
                'quantity',
                'category_price',
                'unit_price',
                'color',
            ]);

        $data = $rows->map(fn (Item $item) => $this->serializeRow($item))->values()->all();

        return [
            'data' => $data,
            'totals' => [
                'items_count' => count($data),
            ],
        ];
    }

    private function baseQuery(?string $warehouse, ?string $productType, ?string $search): Builder
    {
        $query = Item::query()
            ->whereNull('parent_item_id')
            ->whereNull('replaced_by_item_id')
            ->whereNull('recipe_id')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('recipes')
                    ->whereColumn('recipes.output_item_id', 'categories.id');
            })
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('manufactures')
                    ->whereColumn('manufactures.product_id', 'categories.id');
            });

        $this->applyManufacturableScope($query);

        if ($warehouse !== null && $warehouse !== '') {
            $query->where('warehouse', $warehouse);
        }

        if ($productType === ProductType::Finished->value) {
            $query->where(function (Builder $q) {
                $q->where('product_type', ProductType::Finished->value)
                    ->orWhere(function (Builder $q2) {
                        $q2->whereNull('product_type')
                            ->where('warehouse', self::FINISHED_WAREHOUSE);
                    });
            });
        } elseif ($productType === ProductType::SemiFinished->value) {
            $query->where(function (Builder $q) {
                $q->where('product_type', ProductType::SemiFinished->value)
                    ->orWhere(function (Builder $q2) {
                        $q2->whereNull('product_type')
                            ->where('warehouse', self::WIP_WAREHOUSE);
                    });
            });
        }

        if ($search !== null && $search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('category_name', 'like', $term)
                    ->orWhere('item_code', 'like', $term);
            });
        }

        return $query;
    }

    private function applyManufacturableScope(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereIn('product_type', [
                ProductType::Finished->value,
                ProductType::SemiFinished->value,
            ])->orWhere(function (Builder $q2) {
                $q2->whereNull('product_type')
                    ->whereIn('warehouse', [self::FINISHED_WAREHOUSE, self::WIP_WAREHOUSE]);
            });
        })->where(function (Builder $q) {
            $q->whereNull('product_type')
                ->orWhere('product_type', '!=', ProductType::RawMaterial->value);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(Item $item): array
    {
        $type = $item->resolvedProductType();

        return [
            'id' => (int) $item->id,
            'category_name' => (string) ($item->category_name ?? ''),
            'item_code' => $item->item_code,
            'warehouse' => (string) ($item->warehouse ?? ''),
            'product_type' => $type->value,
            'product_type_label' => $this->productTypeLabel($type),
            'color' => $item->color,
            'quantity' => $item->quantity,
            'category_price' => $item->category_price,
            'unit_price' => $item->unit_price,
        ];
    }

    private function productTypeLabel(ProductType $type): string
    {
        return match ($type) {
            ProductType::Finished => 'منتج تام',
            ProductType::SemiFinished => 'تحت التشغيل',
            ProductType::RawMaterial => 'مادة خام',
        };
    }
}
