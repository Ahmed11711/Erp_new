<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\RecipeExtraCost;
use App\Models\RecipeIngredient;
use App\Models\Stock;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Parses the Arabic ERP recipe sheet (see UI screenshot) and prepares data for the
 * interactive import flow used by the Angular "Import from Excel" feature.
 *
 * Flow:
 *  1) parse($path) — read the file, group rows into recipes, return a ParsedSheet
 *     that the controller can inspect for missing items and existing-recipe conflicts.
 *  2) commit(ParsedSheet, decisions) — actually persist items / recipes / ingredients.
 */
class RecipeSheetImportService
{
    /** Footer / meta labels that must never be treated as ingredients. */
    private const FOOTER_LABELS = [
        'سعر المكن',
        'سعر القص',
        'الاجمالي',
        'الإجمالي',
        'نسبه هالك',
        'نسبة هالك',
        'اجمالي التكاليف المباشرة',
        'إجمالي التكاليف المباشرة',
    ];

    /**
     * Labels that represent extra cost rows (not raw materials).
     * Matched against Arabic-normalized ingredient name.
     * If the row name contains ANY of these keywords, it's an extra cost, not a material.
     */
    private const EXTRA_COST_KEYWORDS = [
        'سعر المكن',
        'سعر القص',
        'تكلفة المكن',
        'تكلفة القص',
        'عمالة',
        'عماله',
        'تكلفة العمالة',
        'تكلفة العماله',
        'كهرباء',
        'تكلفة الكهرباء',
        'هالك',
        'نسبه هالك',
        'نسبة هالك',
        'نسبة الهالك',
        'مصاريف إضافية',
        'مصاريف اضافيه',
        'overhead',
        'machine',
        'labor',
        'labour',
        'electricity',
        'waste',
    ];

    /** Labels that are pure totals → skip entirely (not ingredients, not extra costs). */
    private const IGNORE_LABELS = [
        'الاجمالي',
        'الإجمالي',
        'اجمالي التكاليف المباشرة',
        'إجمالي التكاليف المباشرة',
        'اجمالي التكاليف',
        'إجمالي التكاليف',
        'total',
        'final total',
        'grand total',
    ];

    /** Standard warehouse for ingredients/raw materials. */
    public const WAREHOUSE_RAW = 'مخزن مواد خام';
    /** Standard warehouse for finished goods (recipe products). */
    public const WAREHOUSE_FINISHED = 'مخزن منتج تام';

    public function __construct(
        private ItemCodeService $itemCodes,
    ) {
    }

    /**
     * Read & structure the sheet into recipe blocks.
     */
    public function parse(string $absolutePath, ?string $sheetName = null): ParsedRecipeSheet
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

        if ($col['recipe_name'] === null || $col['ingredient_name'] === null || $col['quantity'] === null) {
            throw new \InvalidArgumentException(
                'ملف الإكسيل لا يحتوي على الأعمدة المطلوبة (اسم الصنف / الخامات / الكمية).'
            );
        }

        $highestRow = (int) $sheet->getHighestDataRow();

        /** @var array<int, array<string,mixed>> $recipes */
        $recipes = [];
        $currentRecipe = null;
        $order = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            if ($this->rowIsEmpty($sheet, $row, $headers)) {
                continue;
            }

            $recipeName = $this->cell($sheet, $row, $col['recipe_name']);
            $ingredientName = $this->cell($sheet, $row, $col['ingredient_name']);
            $qtyRaw = $this->cell($sheet, $row, $col['quantity']);
            $unitRaw = $this->cell($sheet, $row, $col['unit']);
            $unitCostRaw = $this->cell($sheet, $row, $col['unit_cost']);
            $lineCostRaw = $this->cell($sheet, $row, $col['line_cost']);
            $sellPriceRaw = $this->cell($sheet, $row, $col['sell_price']);
            $colorRaw = $this->cell($sheet, $row, $col['color']);

            // --- New recipe block starts when اسم الصنف is filled ---
            if ($recipeName !== '') {
                $order++;
                $currentRecipe = [
                    'order' => $order,
                    'recipe_name' => $recipeName,
                    'normalized_name' => $this->normalizeName($recipeName),
                    'sell_price' => $this->parseOptionalDecimal($sellPriceRaw),
                    'total_direct_cost' => null,
                    'color' => $colorRaw !== '' ? $colorRaw : null,
                    'ingredients' => [],
                    'extra_costs' => [],
                    'source_row' => $row,
                ];
                $recipes[$order] = $currentRecipe;
            }

            // capture sell price on later rows if not set yet
            if ($currentRecipe !== null && ($recipes[$currentRecipe['order']]['sell_price'] ?? null) === null) {
                $maybe = $this->parseOptionalDecimal($sellPriceRaw);
                if ($maybe !== null && $maybe > 0) {
                    $recipes[$currentRecipe['order']]['sell_price'] = $maybe;
                    $currentRecipe = $recipes[$currentRecipe['order']];
                }
            }

            // --- Footer rows: capture اجمالي التكاليف المباشرة as definitive total ---
            if ($ingredientName !== '' && $currentRecipe !== null && $this->isTotalDirectCostLabel($ingredientName)) {
                $footerTotal = $this->parseOptionalDecimal($lineCostRaw);
                if ($footerTotal === null) {
                    $footerTotal = $this->parseOptionalDecimal($unitCostRaw);
                }
                if ($footerTotal !== null && $footerTotal > 0) {
                    $recipes[$currentRecipe['order']]['total_direct_cost'] = $footerTotal;
                    $currentRecipe = $recipes[$currentRecipe['order']];
                }
                continue;
            }

            // --- Pure totals → ignore completely ---
            if ($ingredientName === '' || $this->isIgnoreLabel($ingredientName)) {
                continue;
            }

            // --- Extra cost rows: سعر المكن, سعر القص, هالك, عمالة, كهرباء etc. ---
            if ($currentRecipe !== null && $this->isExtraCostLabel($ingredientName)) {
                $parsed = $this->parseExtraCostRow($ingredientName, $unitCostRaw, $lineCostRaw, $qtyRaw);
                if ($parsed !== null) {
                    $recipes[$currentRecipe['order']]['extra_costs'][] = $parsed;
                    $currentRecipe = $recipes[$currentRecipe['order']];
                }
                continue;
            }

            // --- Legacy footer labels (not captured as extra costs) → skip ---
            if ($this->isFooterLabel($ingredientName)) {
                continue;
            }

            if ($currentRecipe === null) {
                continue;
            }

            $qty = $this->parseOptionalDecimal($qtyRaw);
            if ($qty === null || $qty <= 0) {
                continue;
            }

            // Derive unit cost: prefer سعر (per-unit); else compute from التكلفة / الكمية
            $unitCost = $this->parseOptionalDecimal($unitCostRaw);
            $lineCost = $this->parseOptionalDecimal($lineCostRaw);
            if (($unitCost === null || $unitCost <= 0) && $lineCost !== null && $lineCost > 0 && $qty > 0) {
                $unitCost = round($lineCost / $qty, 4);
            }

            $recipes[$currentRecipe['order']]['ingredients'][] = [
                'item_name' => $ingredientName,
                'normalized_name' => $this->normalizeName($ingredientName),
                'quantity' => $qty,
                'unit' => $unitRaw !== '' ? $unitRaw : null,
                'unit_cost' => $unitCost,
                'line_cost' => $lineCost,
                'color' => $colorRaw !== '' ? $colorRaw : null,
                'source_row' => $row,
            ];
            $currentRecipe = $recipes[$currentRecipe['order']];
        }

        $recipes = array_values(array_filter($recipes, fn ($r) => ! empty($r['ingredients'])));

        if (count($recipes) === 0) {
            throw new \InvalidArgumentException('لم يتم العثور على وصفات صالحة داخل الملف.');
        }

        return $this->enrichWithExistence($recipes);
    }

    /**
     * Persist the parsed sheet using user decisions.
     *
     * @param  array{
     *     create_missing_items: bool,
     *     recipe_actions: array<string,string>, // normalized_name => replace|create_new|skip
     * }  $decisions
     */
    public function commit(ParsedRecipeSheet $parsed, array $decisions): RecipeImportCommitResult
    {
        $createMissing = (bool) ($decisions['create_missing_items'] ?? false);
        $recipeActions = $decisions['recipe_actions'] ?? [];

        if (! $createMissing && count($parsed->missingItems) > 0) {
            throw new \InvalidArgumentException('توجد أصناف غير موجودة ولم يتم السماح بإنشائها.');
        }

        $result = new RecipeImportCommitResult();

        DB::beginTransaction();
        try {
            Log::info('[recipe-import] commit start', [
                'recipes_in_sheet' => count($parsed->recipes),
                'missing_items' => count($parsed->missingItems),
                'create_missing_items' => $createMissing,
            ]);

            $itemIdByNormalized = $this->materializeItems($parsed, $createMissing, $result);

            foreach ($parsed->recipes as $recipe) {
                $action = $this->resolveAction($recipe, $recipeActions);
                if ($action === 'skip') {
                    $result->recipesSkipped++;
                    Log::info('[recipe-import] recipe skipped', ['name' => $recipe['recipe_name']]);
                    continue;
                }

                $recipeName = $recipe['recipe_name'];
                $existingId = $recipe['existing_recipe_id'] ?? null;
                $existing = $existingId !== null
                    ? Recipe::query()->find($existingId)
                    : $this->findExistingRecipeModelByNormalizedName($recipe['normalized_name']);

                if ($existing && $action === 'replace') {
                    RecipeIngredient::query()->where('recipe_id', $existing->id)->delete();
                    $existing->save();
                    $recipeModel = $existing;
                    $result->recipesUpdated++;
                } elseif ($existing && $action === 'create_new') {
                    $recipeName = $this->generateUniqueRecipeName($recipeName);
                    $recipeModel = Recipe::create(['recipe_name' => $recipeName]);
                    $result->recipesCreated++;
                } else {
                    $recipeModel = Recipe::create(['recipe_name' => $recipeName]);
                    $result->recipesCreated++;
                }

                $computedCost = 0.0;
                foreach ($recipe['ingredients'] as $ing) {
                    $itemId = $itemIdByNormalized[$ing['normalized_name']] ?? null;
                    if ($itemId === null) {
                        throw new \RuntimeException(
                            'تعذّر ربط الخامة "'.$ing['item_name'].'" أثناء الحفظ.'
                        );
                    }

                    RecipeIngredient::query()->updateOrCreate(
                        ['recipe_id' => $recipeModel->id, 'item_id' => $itemId],
                        [
                            'quantity' => $ing['quantity'],
                            'unit_cost' => $ing['unit_cost'],
                        ]
                    );
                    $result->ingredientsUpserted++;

                    // Sum line costs from Excel (or compute): prefer the line_cost
                    // column (التكلفة) which is qty×price pre-calculated in Excel
                    if (! empty($ing['line_cost']) && (float) $ing['line_cost'] > 0) {
                        $computedCost += (float) $ing['line_cost'];
                    } elseif ($ing['unit_cost'] !== null) {
                        $computedCost += (float) $ing['quantity'] * (float) $ing['unit_cost'];
                    }
                }

                // --- Persist extra costs from Excel footer rows ---
                $extraCosts = $recipe['extra_costs'] ?? [];
                if (! empty($extraCosts)) {
                    if ($action === 'replace') {
                        RecipeExtraCost::query()->where('recipe_id', $recipeModel->id)->delete();
                    }
                    $seenNames = [];
                    foreach ($extraCosts as $ec) {
                        $ecName = $ec['name'];
                        $normalizedEcName = $this->normalizeName($ecName);
                        if (isset($seenNames[$normalizedEcName])) {
                            continue;
                        }
                        $seenNames[$normalizedEcName] = true;

                        RecipeExtraCost::query()->updateOrCreate(
                            ['recipe_id' => $recipeModel->id, 'name' => $ecName],
                            [
                                'type'  => $ec['type'],
                                'value' => $ec['value'],
                            ]
                        );
                        $result->extraCostsCreated++;
                    }
                }

                // --- Determine cost and sell price separately ---
                //
                // التكلفة (cost):
                //   1) اجمالي التكاليف المباشرة from Excel footer (definitive)
                //   2) Σ line costs from التكلفة column
                //   3) Σ (كمية × سعر) as fallback
                //
                // سعر البيع (sell price):
                //   From the سعر البيع column on the recipe header row.
                //   If absent → 0 (NOT the cost — they're different concepts).
                //
                $totalDirectCost = $recipe['total_direct_cost'] ?? null;
                $sellPrice = $recipe['sell_price'] ?? null;

                $recipeTotalCost = $totalDirectCost !== null && $totalDirectCost > 0
                    ? (float) $totalDirectCost
                    : round($computedCost, 2);

                $effectiveSellPrice = $sellPrice !== null && $sellPrice > 0
                    ? (float) $sellPrice
                    : 0.0;

                $recipeColor = $recipe['color'] ?? null;

                $product = $this->ensureFinishedGoodForRecipe(
                    recipe: $recipeModel,
                    cost: $recipeTotalCost,
                    sellPrice: $effectiveSellPrice,
                    result: $result,
                    color: $recipeColor,
                );

                // Also write the Manufacture + ManufactureProduct rows so the
                // recipe appears on the /dashboard/manufacturing/recipes page.
                $manufactureId = null;
                if ($product !== null) {
                    $manufactureId = $this->syncManufactureBom(
                        product: $product,
                        ingredients: $recipe['ingredients'],
                        itemIdByNormalized: $itemIdByNormalized,
                        totalCost: $recipeTotalCost,
                        wasExistingRecipe: (bool) $existing,
                        action: $existing ? $action : 'create',
                        result: $result,
                    );
                }

                Log::info('[recipe-import] recipe committed', [
                    'recipe_id' => $recipeModel->id,
                    'recipe_name' => $recipeModel->recipe_name,
                    'product_item_id' => $product?->id,
                    'manufacture_id' => $manufactureId,
                    'action' => $existing ? $action : 'create',
                    'ingredients_count' => count($recipe['ingredients']),
                    'sell_price' => $effectiveSellPrice,
                    'total_direct_cost_from_excel' => $totalDirectCost,
                    'computed_cost_from_lines' => round($computedCost, 2),
                    'final_recipe_total_cost' => $recipeTotalCost,
                ]);

                $result->committedRecipes[] = [
                    'id' => $recipeModel->id,
                    'recipe_name' => $recipeModel->recipe_name,
                    'action' => $existing ? $action : 'create',
                    'ingredients_count' => count($recipe['ingredients']),
                    'product_item_id' => $product?->id,
                    'product_item_name' => $product?->category_name,
                    'product_warehouse' => $product?->warehouse,
                    'manufacture_id' => $manufactureId,
                    'total_cost' => $recipeTotalCost,
                ];
            }

            DB::commit();
            Log::info('[recipe-import] commit success', $result->toArray());
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[recipe-import] commit rolled back', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $result;
    }

    /**
     * Every Recipe is also a sellable finished-good item in مخزن منتج تام.
     *
     * @param  float  $cost       اجمالي التكاليف المباشرة (stored in unit_price)
     * @param  float  $sellPrice  سعر البيع from Excel (stored in category_price); 0 if absent
     */
    private function ensureFinishedGoodForRecipe(
        Recipe $recipe,
        float $cost,
        float $sellPrice,
        RecipeImportCommitResult $result,
        ?string $color = null,
    ): ?Item {
        $linked = Item::query()
            ->where('recipe_id', $recipe->id)
            ->where('warehouse', self::WAREHOUSE_FINISHED)
            ->first();
        if ($linked) {
            $dirty = false;
            if ($sellPrice > 0 && ((float) $linked->category_price) <= 0) {
                $linked->category_price = $sellPrice;
                $dirty = true;
            }
            if ($cost > 0 && ((float) $linked->unit_price) <= 0) {
                $linked->unit_price = $cost;
                $dirty = true;
            }
            if ($linked->stock_id === null) {
                $linked->stock_id = $this->resolveStockIdForWarehouse(self::WAREHOUSE_FINISHED);
                $dirty = true;
            }
            if ($color !== null && ($linked->color === null || $linked->color === '')) {
                $linked->color = $color;
                $dirty = true;
            }
            if ($dirty) {
                $linked->save();
            }

            return $linked->fresh();
        }

        // Match by name in the finished-goods warehouse (Arabic-normalized).
        $normalized = $this->normalizeName($recipe->recipe_name);
        $candidate = null;
        Item::query()
            ->select(['id', 'category_name', 'recipe_id', 'category_price', 'unit_price', 'stock_id'])
            ->where('warehouse', self::WAREHOUSE_FINISHED)
            ->orderBy('id')
            ->chunk(2000, function ($rows) use (&$candidate, $normalized) {
                foreach ($rows as $row) {
                    if ($this->normalizeName((string) $row->category_name) === $normalized) {
                        $candidate = $row;

                        return false;
                    }
                }

                return null;
            });

        if ($candidate) {
            $model = Item::query()->find($candidate->id);
            if ($model) {
                $model->recipe_id = $recipe->id;
                if ($model->stock_id === null) {
                    $model->stock_id = $this->resolveStockIdForWarehouse(self::WAREHOUSE_FINISHED);
                }
                if ($sellPrice > 0 && ((float) $model->category_price) <= 0) {
                    $model->category_price = $sellPrice;
                }
                if ($cost > 0 && ((float) $model->unit_price) <= 0) {
                    $model->unit_price = $cost;
                }
                $model->save();
                $result->productsLinked++;

                return $model->fresh();
            }
        }

        $product = $this->createItemWithSeparatePrices(
            name: $recipe->recipe_name,
            warehouse: self::WAREHOUSE_FINISHED,
            cost: $cost,
            sellPrice: $sellPrice,
            recipeId: $recipe->id,
            color: $color,
        );
        $result->productsCreated++;
        Log::info('[recipe-import] finished-good created for recipe', [
            'recipe_id' => $recipe->id,
            'product_item_id' => $product->id,
            'cost' => $cost,
            'sell_price' => $sellPrice,
        ]);

        return $product;
    }

    /**
     * Upsert the Manufacture + ManufactureProduct rows that back the
     * /dashboard/manufacturing/recipes page.
     *
     * - create / create_new recipe → insert a new Manufacture row
     * - replace                   → wipe existing ManufactureProduct lines and re-insert
     * - skip                      → not called
     *
     * Returns the final manufacture_id, or null if the sync is impossible.
     *
     * @param  array<int, array<string,mixed>>  $ingredients
     * @param  array<string, int>  $itemIdByNormalized
     */
    private function syncManufactureBom(
        Item $product,
        array $ingredients,
        array $itemIdByNormalized,
        float $totalCost,
        bool $wasExistingRecipe,
        string $action,
        RecipeImportCommitResult $result,
    ): ?int {
        $existing = Manufacture::query()->where('product_id', $product->id)->first();

        if ($existing && $action === 'replace') {
            ManufactureProduct::query()->where('manufacture_id', $existing->id)->delete();
            $existing->total = round($totalCost, 2);
            $existing->save();
            $manufacture = $existing;
            $result->manufacturesUpdated++;
        } elseif ($existing) {
            // Product already has a manufacture recipe (from a previous import or
            // manual confirmOrder). Keep it — don't duplicate or overwrite.
            Log::info('[recipe-import] manufacture already exists, reusing', [
                'manufacture_id' => $existing->id,
                'product_id' => $product->id,
            ]);

            return (int) $existing->id;
        } else {
            $manufacture = Manufacture::create([
                'product_id' => $product->id,
                'total' => round($totalCost, 2),
            ]);
            $result->manufacturesCreated++;
        }

        // De-duplicate ingredients by item_id so we don't violate the
        // "no duplicate material in same manufacture" rule the manual flow
        // enforces in ManufactureController::store.
        /** @var array<int, array{id:int, quantity:float, total_price:float}> $byItemId */
        $byItemId = [];
        foreach ($ingredients as $ing) {
            $itemId = $itemIdByNormalized[$ing['normalized_name']] ?? null;
            if ($itemId === null) {
                continue;
            }
            $qty = (float) ($ing['quantity'] ?? 0);
            $unitCost = $ing['unit_cost'] !== null ? (float) $ing['unit_cost'] : 0.0;
            $linePrice = round($qty * $unitCost, 4);

            if (! isset($byItemId[$itemId])) {
                $byItemId[$itemId] = ['id' => $itemId, 'quantity' => $qty, 'total_price' => $linePrice];
            } else {
                $byItemId[$itemId]['quantity'] += $qty;
                $byItemId[$itemId]['total_price'] = round($byItemId[$itemId]['total_price'] + $linePrice, 4);
            }
        }

        foreach ($byItemId as $line) {
            ManufactureProduct::query()->updateOrCreate(
                [
                    'manufacture_id' => $manufacture->id,
                    'product_id' => $line['id'],
                ],
                [
                    'quantity' => $line['quantity'],
                    'total_price' => $line['total_price'],
                ]
            );
        }

        return (int) $manufacture->id;
    }

    // ==========================================================================
    // Internals
    // ==========================================================================

    /**
     * Build a ParsedRecipeSheet from an array of structured recipe blocks.
     * Annotates each recipe with `exists=true/false` and produces a global
     * list of `missingItems`.
     *
     * @param  array<int, array<string,mixed>>  $recipes
     */
    private function enrichWithExistence(array $recipes): ParsedRecipeSheet
    {
        $allIngredientNames = [];
        foreach ($recipes as $recipe) {
            foreach ($recipe['ingredients'] as $ing) {
                $allIngredientNames[$ing['normalized_name']] = $ing['item_name'];
            }
        }

        $existingItemsMap = $this->findExistingItemsByNormalizedName(array_keys($allIngredientNames));

        $missingItems = [];
        foreach ($allIngredientNames as $normalized => $display) {
            if (! isset($existingItemsMap[$normalized])) {
                $missingItems[] = $display;
            }
        }

        $recipeNamesNormalized = array_values(array_unique(array_map(fn ($r) => $r['normalized_name'], $recipes)));
        $existingRecipesMap = $this->findExistingRecipesByNormalizedName($recipeNamesNormalized);

        foreach ($recipes as &$recipe) {
            $existingRecipe = $existingRecipesMap[$recipe['normalized_name']] ?? null;
            $recipe['exists'] = $existingRecipe !== null;
            $recipe['existing_recipe_id'] = $existingRecipe['id'] ?? null;
            $recipe['existing_recipe_name'] = $existingRecipe['recipe_name'] ?? null;

            foreach ($recipe['ingredients'] as &$ing) {
                $match = $existingItemsMap[$ing['normalized_name']] ?? null;
                $ing['item_exists'] = $match !== null;
                $ing['existing_item_id'] = $match['id'] ?? null;
                $ing['existing_item_name'] = $match['category_name'] ?? null;
                $ing['existing_item_price'] = $match['category_price'] ?? null;
            }
            unset($ing);
        }
        unset($recipe);

        $existingRecipeNames = array_values(array_map(fn ($r) => $r['recipe_name'], $existingRecipesMap));

        return new ParsedRecipeSheet(
            recipes: $recipes,
            missingItems: array_values(array_unique($missingItems)),
            existingRecipes: $existingRecipeNames,
        );
    }

    /**
     * Scan the items table in chunks and match against a set of normalized names
     * using the SAME Arabic-aware normalizer applied to every DB row.
     *
     * This is the correct place to do matching because MySQL's TRIM(LOWER(X))
     * cannot normalize Arabic diacritics, alef/yeh/teh-marbuta variants,
     * non-breaking spaces, or Arabic-Indic digits.
     *
     * @param  array<int,string>  $normalizedNames
     * @return array<string, array{id:int, category_name:string, category_price:float|null}>
     */
    private function findExistingItemsByNormalizedName(array $normalizedNames): array
    {
        if (empty($normalizedNames)) {
            return [];
        }

        $needed = array_flip($normalizedNames);
        $map = [];

        Item::query()
            ->select(['id', 'category_name', 'category_price'])
            ->whereNotNull('category_name')
            ->orderBy('id')
            ->chunk(2000, function ($rows) use (&$map, $needed) {
                foreach ($rows as $row) {
                    $key = $this->normalizeName((string) $row->category_name);
                    if ($key === '' || ! isset($needed[$key]) || isset($map[$key])) {
                        continue;
                    }
                    $map[$key] = [
                        'id' => (int) $row->id,
                        'category_name' => (string) $row->category_name,
                        'category_price' => $row->category_price !== null ? (float) $row->category_price : null,
                    ];
                }
            });

        return $map;
    }

    /**
     * @param  array<int,string>  $normalizedNames
     * @return array<string, array{id:int, recipe_name:string}>
     */
    private function findExistingRecipesByNormalizedName(array $normalizedNames): array
    {
        if (empty($normalizedNames)) {
            return [];
        }

        $needed = array_flip($normalizedNames);
        $map = [];

        Recipe::query()
            ->select(['id', 'recipe_name'])
            ->orderBy('id')
            ->chunk(2000, function ($rows) use (&$map, $needed) {
                foreach ($rows as $row) {
                    $key = $this->normalizeName((string) $row->recipe_name);
                    if ($key === '' || ! isset($needed[$key]) || isset($map[$key])) {
                        continue;
                    }
                    $map[$key] = [
                        'id' => (int) $row->id,
                        'recipe_name' => (string) $row->recipe_name,
                    ];
                }
            });

        return $map;
    }

    /** Is any existing recipe (case-insensitive, Arabic-normalized) already using this name? */
    private function recipeNameExists(string $name): bool
    {
        $normalized = $this->normalizeName($name);
        $map = $this->findExistingRecipesByNormalizedName([$normalized]);

        return isset($map[$normalized]);
    }

    /**
     * Returns a map of normalized_name => item_id for every ingredient in the sheet,
     * creating missing items in مخزن مواد خام on the fly when allowed by the user.
     *
     * New items inherit the unit (measurement) + unit_cost from the first Excel row
     * where they appear, so they show up in the items list with the correct data.
     *
     * @return array<string,int>
     */
    private function materializeItems(ParsedRecipeSheet $parsed, bool $createMissing, RecipeImportCommitResult $result): array
    {
        // Collect the best-known unit + price for each ingredient across all recipes.
        /** @var array<string, array{name:string, unit:?string, price:?float}> $candidates */
        $candidates = [];
        foreach ($parsed->recipes as $recipe) {
            foreach ($recipe['ingredients'] as $ing) {
                $key = $ing['normalized_name'];
                if (! isset($candidates[$key])) {
                    $candidates[$key] = [
                        'name' => $ing['item_name'],
                        'unit' => $ing['unit'] ?? null,
                        'price' => $ing['unit_cost'] ?? null,
                        'color' => $ing['color'] ?? null,
                    ];
                    continue;
                }
                if ($candidates[$key]['unit'] === null && ! empty($ing['unit'])) {
                    $candidates[$key]['unit'] = $ing['unit'];
                }
                if ($candidates[$key]['color'] === null && ! empty($ing['color'])) {
                    $candidates[$key]['color'] = $ing['color'];
                }
                if (($candidates[$key]['price'] === null || $candidates[$key]['price'] <= 0)
                    && ($ing['unit_cost'] ?? null) !== null
                    && $ing['unit_cost'] > 0
                ) {
                    $candidates[$key]['price'] = (float) $ing['unit_cost'];
                }
            }
        }

        $existing = $this->findExistingItemsByNormalizedName(array_keys($candidates));

        $map = [];
        foreach ($existing as $k => $row) {
            $map[$k] = (int) $row['id'];
        }

        foreach ($candidates as $normalized => $meta) {
            if (isset($map[$normalized])) {
                Log::info('[recipe-import] ingredient matched existing item', [
                    'name' => $meta['name'],
                    'item_id' => $map[$normalized],
                ]);
                continue;
            }
            if (! $createMissing) {
                throw new \InvalidArgumentException('الخامة "'.$meta['name'].'" غير موجودة وتم رفض إنشاء الأصناف.');
            }

            $item = $this->createItem(
                name: $meta['name'],
                warehouse: self::WAREHOUSE_RAW,
                unitText: $meta['unit'],
                price: $meta['price'],
                color: $meta['color'] ?? null,
            );
            $result->itemsCreated++;
            $map[$normalized] = (int) $item->id;

            Log::info('[recipe-import] raw material item created', [
                'id' => $item->id,
                'name' => $item->category_name,
                'warehouse' => $item->warehouse,
                'stock_id' => $item->stock_id,
                'measurement_id' => $item->measurement_id,
                'production_id' => $item->production_id,
                'price' => (float) $item->category_price,
            ]);
        }

        return $map;
    }

    /**
     * Creates a new item (categories row) correctly linked to:
     *  - a warehouse (string column)
     *  - the matching stocks row (stock_id)
     *  - a production line + measurement (scoped to the warehouse when possible)
     *  - a price (category_price + unit_price)
     *
     * This is the single source of truth for ALL item creation during recipe import,
     * so both raw materials (مخزن مواد خام) and finished-goods (مخزن منتج تام) use
     * the exact same pathway as CategoriesController::store.
     */
    private function createItem(
        string $name,
        string $warehouse = self::WAREHOUSE_RAW,
        ?string $unitText = null,
        ?float $price = null,
        ?int $recipeId = null,
        ?string $color = null,
    ): Item {
        $defaults = config('items_import.new_item', []);

        $effectivePrice = $price !== null && $price >= 0
            ? (float) $price
            : (float) ($defaults['category_price'] ?? 0);

        $attrs = [
            'category_name' => $name,
            'category_price' => $effectivePrice,
            'unit_price' => $effectivePrice,
            'initial_balance' => (float) ($defaults['initial_balance'] ?? 0),
            'minimum_quantity' => (float) ($defaults['minimum_quantity'] ?? 0),
            'warehouse' => $warehouse,
            'production_id' => $this->resolveProductionIdForWarehouse($warehouse, $defaults),
            'measurement_id' => $this->resolveMeasurementIdForWarehouse($warehouse, $unitText, $defaults),
            'stock_id' => $this->resolveStockIdForWarehouse($warehouse),
            'category_image' => (string) ($defaults['category_image'] ?? ''),
            'item_code' => null,
            'color' => $color,
            'recipe_id' => $recipeId,
        ];

        $item = new Item($attrs);
        $item->save();

        $this->itemCodes->ensureCode($item);

        return $item->fresh();
    }

    /**
     * Like createItem() but allows setting cost (unit_price) and sell price
     * (category_price) independently — used for finished-goods where the two
     * values come from different Excel columns.
     */
    private function createItemWithSeparatePrices(
        string $name,
        string $warehouse,
        float $cost,
        float $sellPrice,
        ?int $recipeId = null,
        ?string $color = null,
    ): Item {
        $defaults = config('items_import.new_item', []);

        $attrs = [
            'category_name' => $name,
            'category_price' => $sellPrice,
            'unit_price' => $cost,
            'initial_balance' => (float) ($defaults['initial_balance'] ?? 0),
            'minimum_quantity' => (float) ($defaults['minimum_quantity'] ?? 0),
            'warehouse' => $warehouse,
            'production_id' => $this->resolveProductionIdForWarehouse($warehouse, $defaults),
            'measurement_id' => $this->resolveMeasurementIdForWarehouse($warehouse, null, $defaults),
            'stock_id' => $this->resolveStockIdForWarehouse($warehouse),
            'category_image' => (string) ($defaults['category_image'] ?? ''),
            'item_code' => null,
            'color' => $color,
            'recipe_id' => $recipeId,
        ];

        $item = new Item($attrs);
        $item->save();

        $this->itemCodes->ensureCode($item);

        return $item->fresh();
    }

    /**
     * Mirror of CategoriesController::ensureStockRowForStandardWarehouse:
     * finds or creates the stocks row for one of the standard warehouse names.
     */
    private function resolveStockIdForWarehouse(string $warehouse): int
    {
        $existing = Stock::query()->where('name', $warehouse)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $assetId = (int) (Stock::query()->value('asset_id')
            ?? TreeAccount::query()->min('id')
            ?? 1);

        $row = Stock::firstOrCreate(
            ['name' => $warehouse],
            [
                'balance' => 0,
                'asset_id' => $assetId,
                'active' => true,
            ]
        );

        return (int) $row->id;
    }

    /**
     * Prefer a production_line belonging to $warehouse. Falls back to the env
     * default, then to the first row in the table. Ensures the FK constraint
     * on categories.production_id is always satisfied.
     */
    private function resolveProductionIdForWarehouse(string $warehouse, array $defaults): int
    {
        $scoped = (int) Production::query()->where('warehouse', $warehouse)->orderBy('id')->value('id');
        if ($scoped > 0) {
            return $scoped;
        }

        if (! empty($defaults['production_id'])) {
            return (int) $defaults['production_id'];
        }

        $any = (int) Production::query()->orderBy('id')->value('id');
        if ($any === 0) {
            throw new \RuntimeException('لا يوجد سجل "خط إنتاج" مُعرَّف في النظام. أضِف واحداً على الأقل ثم أعد المحاولة.');
        }

        return $any;
    }

    /**
     * Try to resolve the measurement from the sheet's unit text (متر / وحدة / كجم)
     * scoped to the warehouse. Falls back to default / first available.
     */
    private function resolveMeasurementIdForWarehouse(string $warehouse, ?string $unitText, array $defaults): int
    {
        $unitText = $unitText !== null ? trim($unitText) : '';

        if ($unitText !== '') {
            $normalized = $this->normalizeName($unitText);
            // exact (normalized) match within same warehouse first
            $scoped = Measurement::query()
                ->where('warehouse', $warehouse)
                ->get(['id', 'unit']);
            foreach ($scoped as $m) {
                if ($this->normalizeName((string) $m->unit) === $normalized) {
                    return (int) $m->id;
                }
            }
            // global match
            $global = Measurement::query()->get(['id', 'unit']);
            foreach ($global as $m) {
                if ($this->normalizeName((string) $m->unit) === $normalized) {
                    return (int) $m->id;
                }
            }
        }

        $scoped = (int) Measurement::query()->where('warehouse', $warehouse)->orderBy('id')->value('id');
        if ($scoped > 0) {
            return $scoped;
        }

        if (! empty($defaults['measurement_id'])) {
            return (int) $defaults['measurement_id'];
        }

        $any = (int) Measurement::query()->orderBy('id')->value('id');
        if ($any === 0) {
            throw new \RuntimeException('لا يوجد سجل "وحدة قياس" مُعرَّف في النظام. أضِف واحداً على الأقل ثم أعد المحاولة.');
        }

        return $any;
    }

    private function resolveAction(array $recipe, array $recipeActions): string
    {
        if (! $recipe['exists']) {
            return 'create';
        }
        $action = $recipeActions[$recipe['normalized_name']] ?? null;
        if (! in_array($action, ['replace', 'create_new', 'skip'], true)) {
            return 'skip';
        }

        return $action;
    }

    private function generateUniqueRecipeName(string $base): string
    {
        $base = trim($base);
        $candidate = $base.' (نسخة)';
        $n = 1;
        while ($this->recipeNameExists($candidate)) {
            $n++;
            $candidate = $base.' ('.$n.')';
            if ($n > 999) {
                throw new \RuntimeException('تعذّر توليد اسم وصفة فريد بعد 999 محاولة.');
            }
        }

        return $candidate;
    }

    private function findExistingRecipeModelByNormalizedName(string $normalized): ?Recipe
    {
        $map = $this->findExistingRecipesByNormalizedName([$normalized]);
        $id = $map[$normalized]['id'] ?? null;

        return $id !== null ? Recipe::query()->find($id) : null;
    }

    /**
     * Robust Arabic/Latin normalization used for ALL item & recipe matching.
     *
     * Handles the common reasons a name "looks identical" but fails to match:
     *   - Tashkeel/diacritics (ـَ ـِ ـُ ـْ ـّ ـً ـٍ ـٌ …)
     *   - Tatweel / Kashida (ـ U+0640)
     *   - Alef variants  (أ إ آ ٱ) → ا
     *   - Yeh variants   (ى ئ ي)  → ي
     *   - Teh marbuta    (ة)     → ه
     *   - Arabic / Persian digits (٠-٩ / ۰-۹) → 0-9
     *   - Non-breaking / zero-width spaces / BOM
     *   - Double spaces, leading/trailing whitespace, mixed case
     */
    private function normalizeName(string $raw): string
    {
        $s = $raw;

        // 1) strip tashkeel (combining marks) + Arabic presentation forms & tatweel
        $s = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $s) ?? $s;
        $s = str_replace("\u{0640}", '', $s); // tatweel

        // 2) alef variants
        $s = preg_replace('/[\x{0622}\x{0623}\x{0625}\x{0671}]/u', "\u{0627}", $s) ?? $s;

        // 3) yeh / alef-maksura / yeh-hamza → yeh
        $s = str_replace(["\u{0649}", "\u{0626}"], "\u{064A}", $s);

        // 4) teh marbuta → heh (both are often interchangeable in product names)
        $s = str_replace("\u{0629}", "\u{0647}", $s);

        // 5) Arabic-Indic digits
        $s = strtr($s, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        // 6) normalize every possible "invisible" whitespace to a regular space
        $s = preg_replace(
            '/[\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200D}\x{202F}\x{205F}\x{2060}\x{3000}\x{FEFF}]/u',
            ' ',
            $s
        ) ?? $s;

        // 7) collapse whitespace + trim + lowercase
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = trim($s);

        return mb_strtolower($s, 'UTF-8');
    }

    private function isFooterLabel(string $raw): bool
    {
        $n = $this->normalizeName($raw);
        $allLabels = array_merge(self::FOOTER_LABELS, self::TOTAL_COST_LABELS);
        foreach ($allLabels as $label) {
            if ($n === $this->normalizeName($label)) {
                return true;
            }
        }

        return false;
    }

    /** Does this label represent a pure total/summary row to ignore entirely? */
    private function isIgnoreLabel(string $raw): bool
    {
        $n = $this->normalizeName($raw);
        foreach (self::IGNORE_LABELS as $label) {
            if ($n === $this->normalizeName($label)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this label match an extra-cost keyword?
     * Uses substring matching: if ANY keyword appears within the label, it's an extra cost.
     */
    private function isExtraCostLabel(string $raw): bool
    {
        $n = $this->normalizeName($raw);
        foreach (self::EXTRA_COST_KEYWORDS as $keyword) {
            $nk = $this->normalizeName($keyword);
            if ($nk !== '' && str_contains($n, $nk)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse a footer row into an extra-cost entry.
     *
     * Detects type:
     *  - If the original text contains "%" or the keyword "نسب" → percentage
     *  - Otherwise → fixed
     *
     * Value resolution:
     *  1) line_cost column (التكلفة)
     *  2) unit_cost column (سعر)
     *  3) quantity column (sometimes the value is placed there)
     *
     * @return array{name:string, type:string, value:float}|null
     */
    private function parseExtraCostRow(
        string $ingredientName,
        ?string $unitCostRaw,
        ?string $lineCostRaw,
        ?string $qtyRaw,
    ): ?array {
        $value = $this->parseOptionalDecimal($lineCostRaw);
        if ($value === null || $value <= 0) {
            $value = $this->parseOptionalDecimal($unitCostRaw);
        }
        if ($value === null || $value <= 0) {
            $value = $this->parseOptionalDecimal($qtyRaw);
        }
        if ($value === null || $value <= 0) {
            return null;
        }

        $type = 'fixed';
        $lower = mb_strtolower($ingredientName, 'UTF-8');
        $norm = $this->normalizeName($ingredientName);

        if (str_contains($lower, '%') || str_contains($norm, 'نسب') || str_contains($norm, 'هالك')) {
            $type = 'percentage';
            $cleanedVal = (string) $value;
            $cleanedVal = str_replace(['%', '٪'], '', $cleanedVal);
            $pct = $this->parseOptionalDecimal($cleanedVal);
            if ($pct !== null && $pct > 0 && $pct <= 100) {
                $value = $pct;
            }
        }

        $name = trim($ingredientName);

        return [
            'name'  => $name,
            'type'  => $type,
            'value' => round($value, 4),
        ];
    }

    /** Labels that indicate the definitive total cost of a recipe block. */
    private const TOTAL_COST_LABELS = [
        'اجمالي التكاليف المباشرة',
        'إجمالي التكاليف المباشرة',
        'اجمالي التكاليف',
        'إجمالي التكاليف',
    ];

    private function isTotalDirectCostLabel(string $raw): bool
    {
        $n = $this->normalizeName($raw);
        foreach (self::TOTAL_COST_LABELS as $label) {
            if ($n === $this->normalizeName($label)) {
                return true;
            }
        }

        return false;
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
            'recipe_name' => $this->matchColumn($headers, [
                'recipe_name', 'recipe', 'product_name', 'finished_item_name', 'item_name',
                'اسم الصنف', 'اسم_الصنف', 'الصنف', 'اسم المنتج',
            ]),
            'item_code' => $this->matchColumn($headers, [
                'item_code', 'product_code', 'code',
                'كود الصنف', 'كود_الصنف', 'كود',
            ]),
            'ingredient_name' => $this->matchColumn($headers, [
                'ingredient_name', 'ingredient', 'material', 'item_name',
                'الخامات', 'الخامة', 'الخامه', 'خامة', 'خامه', 'مادة', 'المادة',
            ]),
            'quantity' => $this->matchColumn($headers, [
                'quantity', 'qty',
                'الكمية', 'الكميه',
            ]),
            'unit' => $this->matchColumn($headers, [
                'unit',
                'الوحدة', 'الوحده', 'الفئة', 'الفئه',
            ]),
            // سعر (unit cost per piece) — must NOT match التكلفة (line total)
            'unit_cost' => $this->matchColumn($headers, [
                'unit_cost',
                'سعر',
            ]),
            // التكلفة (line total = qty × unit price)
            'line_cost' => $this->matchColumn($headers, [
                'line_cost', 'cost', 'total_cost',
                'التكلفة', 'التكلفه', 'تكلفة', 'تكلفه',
            ]),
            'sell_price' => $this->matchColumn($headers, [
                'sell_price', 'selling_price', 'price_sell',
                'سعر البيع', 'سعر_البيع', 'سعر بيع',
            ]),
            'color' => $this->matchColumn($headers, [
                'color', 'colour',
                'اللون', 'الون', 'لون',
            ]),
        ];
    }

    private function matchColumn(array $headers, array $aliases): ?int
    {
        $normalizedAliases = array_map(fn ($a) => $this->normalizeName((string) $a), $aliases);

        foreach ($headers as $idx => $label) {
            $n = $this->normalizeName((string) $label);
            if ($n === '') {
                continue;
            }
            if (in_array($n, $normalizedAliases, true)) {
                return $idx;
            }
        }

        foreach ($headers as $idx => $label) {
            $n = $this->normalizeName((string) $label);
            if ($n === '') {
                continue;
            }
            foreach ($normalizedAliases as $a) {
                if ($a !== '' && mb_strlen($a) >= 3 && str_contains($n, $a)) {
                    return $idx;
                }
            }
        }

        return null;
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

    private function parseOptionalDecimal(?string $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $normalized = str_replace([',', ' ', '%'], ['', '', ''], $raw);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}
