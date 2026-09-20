<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class RecipeExcelImportResult
{
    public function __construct(
        public int $rowsProcessed,
        public int $itemsCreated,
        public int $itemsUpdated,
        public int $recipesCreated,
        public int $recipesUpdated,
        public int $ingredientsUpserted,
        public array $warnings = [],
    ) {
    }
}

class RecipeExcelImportService
{
    public function __construct(
        private ItemCodeService $itemCodes,
    ) {
    }

    /**
     * Expected columns (row 1), any subset; match is case-insensitive on trimmed labels.
     *
     * - recipe_name (required for rows that define a BOM or link a product to a recipe)
     * - recipe_description (optional)
     * - product_name OR item_name — finished product / item row (required)
     * - item_code / product_code — optional; used to match or set the product code
     * - color — optional
     * - ingredient_name — optional; when set, quantity is required
     * - ingredient_item_code — optional; match ingredient by code first
     * - quantity — ingredient quantity when ingredient_name is set
     * - unit_cost — optional ingredient unit cost
     */
    public function import(string $absolutePath, ?string $sheetName = null, bool $stopOnFirstError = true): RecipeExcelImportResult
    {
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException('File not readable: '.$absolutePath);
        }

        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $sheetName !== null && $sheetName !== ''
            ? ($spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getSheet(0))
            : $spreadsheet->getSheet(0);

        $headers = $this->readHeaders($sheet);
        $col = $this->resolveColumns($headers);

        if ($col['product_name'] === null) {
            throw new \InvalidArgumentException('Missing required column: product_name (or item_name / finished_item_name).');
        }

        $highestRow = (int) $sheet->getHighestDataRow();
        $result = new RecipeExcelImportResult(0, 0, 0, 0, 0, 0, []);

        DB::beginTransaction();
        try {
            for ($row = 2; $row <= $highestRow; $row++) {
                if ($this->rowIsEmpty($sheet, $row, $headers)) {
                    continue;
                }

                $result->rowsProcessed++;

                try {
                    $this->processRow($sheet, $row, $col, $result);
                } catch (\Throwable $e) {
                    if ($stopOnFirstError) {
                        throw $e;
                    }
                    $result->warnings[] = 'Row '.$row.': '.$e->getMessage();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $result;
    }

    private function processRow($sheet, int $row, array $col, RecipeExcelImportResult $result): void
    {
        $recipeName = $this->cell($sheet, $row, $col['recipe_name']);
        $recipeDesc = $this->cell($sheet, $row, $col['recipe_description']);
        $productName = $this->cell($sheet, $row, $col['product_name']);
        $productCode = $this->cell($sheet, $row, $col['item_code']);
        $color = $this->cell($sheet, $row, $col['color']);
        $ingredientName = $this->cell($sheet, $row, $col['ingredient_name']);
        $ingredientCode = $this->cell($sheet, $row, $col['ingredient_item_code']);
        $qtyRaw = $this->cell($sheet, $row, $col['quantity']);
        $unitCostRaw = $this->cell($sheet, $row, $col['unit_cost']);

        if ($productName === '') {
            throw new \InvalidArgumentException('product_name is empty.');
        }

        $product = $this->resolveItem($productName, $productCode, $result);

        $recipe = null;
        $hadRecipeName = $recipeName !== '';
        if ($hadRecipeName) {
            $recipe = Recipe::query()->whereRaw('TRIM(recipe_name) = ?', [$recipeName])->first();
            if ($recipe === null) {
                $recipe = new Recipe(['recipe_name' => $recipeName, 'description' => $recipeDesc !== '' ? $recipeDesc : null]);
                $recipe->save();
                $result->recipesCreated++;
            } else {
                $updated = false;
                if ($recipeDesc !== '' && $recipe->description !== $recipeDesc) {
                    $recipe->description = $recipeDesc;
                    $updated = true;
                }
                if ($updated) {
                    $recipe->save();
                    $result->recipesUpdated++;
                }
            }

            if ($product->recipe_id !== $recipe->id) {
                $product->recipe_id = $recipe->id;
                $product->save();
                $result->itemsUpdated++;
            }
        }

        if ($productCode !== '' || $color !== '') {
            $dirty = false;
            if ($productCode !== '') {
                $this->itemCodes->assertCodeAvailable($productCode, (int) $product->id);
                if ($product->item_code !== $productCode) {
                    $product->item_code = $productCode;
                    $dirty = true;
                }
            }
            if ($color !== '' && $product->color !== $color) {
                $product->color = $color;
                $dirty = true;
            }
            if ($dirty) {
                $product->save();
                $result->itemsUpdated++;
            }
        }

        $reload = Item::query()->find($product->id);
        if ($reload && ($reload->item_code === null || $reload->item_code === '')) {
            $this->itemCodes->ensureCode($reload);
        }

        if ($ingredientName !== '') {
            if ($recipe === null) {
                throw new \InvalidArgumentException('ingredient_name is set but recipe_name is empty.');
            }
            $qty = $this->parseDecimal($qtyRaw, 'quantity');
            if ($qty === null || $qty <= 0) {
                throw new \InvalidArgumentException('quantity must be a positive number when ingredient_name is set.');
            }

            $ingredient = $this->resolveItem($ingredientName, $ingredientCode, $result, allowCreate: true);

            $unitCost = $this->parseOptionalDecimal($unitCostRaw);

            RecipeIngredient::query()->updateOrCreate(
                [
                    'recipe_id' => $recipe->id,
                    'item_id' => $ingredient->id,
                ],
                [
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                ]
            );
            $result->ingredientsUpserted++;
        }
    }

    private function resolveItem(string $name, string $code, RecipeExcelImportResult $result, bool $allowCreate = true): Item
    {
        $name = trim($name);
        $code = trim($code);

        if ($code !== '') {
            $byCode = Item::query()->where('item_code', $code)->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        $byName = Item::query()
            ->whereRaw('TRIM(category_name) = ?', [$name])
            ->first();

        if ($byName !== null) {
            return $byName;
        }

        if (! $allowCreate) {
            throw new \InvalidArgumentException('Item not found: '.$name.($code !== '' ? ' (code: '.$code.')' : ''));
        }

        $item = $this->createItem($name, $code !== '' ? $code : null);
        $result->itemsCreated++;

        return $item;
    }

    private function createItem(string $name, ?string $code): Item
    {
        $defaults = config('items_import.new_item', []);

        $productionId = $this->resolveProductionId($defaults);
        $measurementId = $this->resolveMeasurementId($defaults);

        $attrs = [
            'category_name' => $name,
            'category_price' => (float) ($defaults['category_price'] ?? 0),
            'unit_price' => (float) ($defaults['category_price'] ?? 0),
            'initial_balance' => (float) ($defaults['initial_balance'] ?? 0),
            'minimum_quantity' => (float) ($defaults['minimum_quantity'] ?? 0),
            'warehouse' => (string) ($defaults['warehouse'] ?? 'مخزن مواد خام'),
            'production_id' => $productionId,
            'measurement_id' => $measurementId,
            'category_image' => (string) ($defaults['category_image'] ?? ''),
            'item_code' => $code,
            'color' => null,
            'recipe_id' => null,
        ];

        $item = new Item($attrs);
        $item->save();

        if ($item->item_code === null || $item->item_code === '') {
            $this->itemCodes->ensureCode($item);
        } else {
            $this->itemCodes->assertCodeAvailable($item->item_code, (int) $item->id);
        }

        return $item->fresh();
    }

    private function resolveProductionId(array $defaults): int
    {
        if (! empty($defaults['production_id'])) {
            return (int) $defaults['production_id'];
        }

        return (int) Production::query()->orderBy('id')->value('id')
            ?? throw new \RuntimeException('No production row found. Set ITEM_IMPORT_DEFAULT_PRODUCTION_ID in .env.');
    }

    private function resolveMeasurementId(array $defaults): int
    {
        if (! empty($defaults['measurement_id'])) {
            return (int) $defaults['measurement_id'];
        }

        return (int) Measurement::query()->orderBy('id')->value('id')
            ?? throw new \RuntimeException('No measurement row found. Set ITEM_IMPORT_DEFAULT_MEASUREMENT_ID in .env.');
    }

    private function readHeaders($sheet): array
    {
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        $headers = [];
        for ($c = 1; $c <= $highestColumnIndex; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $headers[$c] = trim((string) $sheet->getCell($letter.'1')->getValue());
        }

        return $headers;
    }

    /**
     * @return array<string, int|null>
     */
    private function resolveColumns(array $headers): array
    {
        return [
            'recipe_name' => $this->matchColumn($headers, ['recipe_name', 'recipe', 'اسم الوصفة', 'الوصفة']),
            'recipe_description' => $this->matchColumn($headers, ['recipe_description', 'description', 'وصف الوصفة', 'وصف']),
            'product_name' => $this->matchColumn($headers, ['product_name', 'finished_item_name', 'item_name', 'product', 'الصنف', 'اسم الصنف', 'اسم_الصنف']),
            'item_code' => $this->matchColumn($headers, ['item_code', 'product_code', 'code', 'كود', 'كود الصنف']),
            'color' => $this->matchColumn($headers, [
                'color', 'colour', 'shade',
                'اللون', 'الون', 'لون',
                'ألوانه', 'الوانه', 'ألوانها', 'الوانها',
                'ألوان', 'الوان', 'الألوان', 'الالوان',
                'لون الخامة', 'لون الخامه',
            ]),
            'ingredient_name' => $this->matchColumn($headers, ['ingredient_name', 'ingredient', 'material', 'مادة', 'المادة', 'خامة']),
            'ingredient_item_code' => $this->matchColumn($headers, ['ingredient_item_code', 'ingredient_code', 'كود المادة', 'كود_المادة']),
            'quantity' => $this->matchColumn($headers, ['quantity', 'qty', 'كمية']),
            'unit_cost' => $this->matchColumn($headers, ['unit_cost', 'cost', 'تكلفة', 'تكلفة الوحدة']),
        ];
    }

    private function matchColumn(array $headers, array $aliases): ?int
    {
        $normalizedAliases = array_map(fn ($a) => $this->normalizeLabel((string) $a), $aliases);

        foreach ($headers as $idx => $label) {
            $n = $this->normalizeLabel((string) $label);
            if ($n === '') {
                continue;
            }
            foreach ($normalizedAliases as $a) {
                if ($a !== '' && $n === $a) {
                    return $idx;
                }
            }
        }

        foreach ($headers as $idx => $label) {
            $n = $this->normalizeLabel((string) $label);
            if ($n === '') {
                continue;
            }
            foreach ($normalizedAliases as $a) {
                if ($a !== '' && strlen($a) >= 3 && str_contains($n, $a)) {
                    return $idx;
                }
            }
        }

        return null;
    }

    private function normalizeLabel(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));

        return mb_strtolower($s);
    }

    private function cell($sheet, int $row, ?int $colIndex): string
    {
        if ($colIndex === null) {
            return '';
        }
        $letter = Coordinate::stringFromColumnIndex($colIndex);
        $v = $sheet->getCell($letter.$row)->getValue();

        if (is_numeric($v)) {
            return (string) $v;
        }

        return trim((string) $v);
    }

    private function rowIsEmpty($sheet, int $row, array $headers): bool
    {
        foreach ($headers as $c => $_) {
            if ($c === 0) {
                continue;
            }
            $letter = Coordinate::stringFromColumnIndex($c);
            $v = $sheet->getCell($letter.$row)->getValue();
            if ($v !== null && trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseDecimal(?string $raw, string $label): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $normalized = str_replace([',', ' '], ['', ''], $raw);
        if (! is_numeric($normalized)) {
            throw new \InvalidArgumentException('Invalid number for '.$label);

        }

        return (float) $normalized;
    }

    private function parseOptionalDecimal(?string $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return $this->parseDecimal($raw, 'unit_cost');
    }
}
