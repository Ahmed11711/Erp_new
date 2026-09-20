<?php

namespace App\Services\Items;

use App\Enums\InventoryMovementType;
use App\Models\Item;
use App\Models\ItemClassification;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\RecipeExtraCost;
use App\Models\RecipeIngredient;
use App\Models\Stock;
use App\Models\TreeAccount;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\Inventory\InventoryMovementLedgerService;
use App\Services\Manufacturing\RecipeVariantBootstrapService;
use App\Services\Manufacturing\SupportsColorEstimator;
use App\Support\ArabicTextNormalizer;
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
    /** Max columns/rows to read from flat price-list workbooks (styled sheets blow up memory). */
    private const FLAT_SHEET_MAX_COL = 25;
    private const FLAT_SHEET_MAX_ROW = 25000;

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
        private RecipeVariantBootstrapService $recipeVariants,
    ) {
    }

    /**
     * Read & structure worksheet(s) into recipe blocks.
     *
     * When $sheetName is omitted, every worksheet with the required columns is parsed
     * and recipes are merged in tab order.
     */
    public function parse(string $absolutePath, ?string $sheetName = null): ParsedRecipeSheet
    {
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException('File not readable: '.$absolutePath);
        }

        $spreadsheet = IOFactory::load($absolutePath);

        /** @var array<int, array<string,mixed>> $recipes */
        $recipes = [];
        /** @var array<int, string> $parsedSheetNames */
        $parsedSheetNames = [];

        if ($sheetName !== null && trim($sheetName) !== '') {
            $sheet = $spreadsheet->getSheetByName(trim($sheetName));
            if ($sheet === null) {
                throw new \InvalidArgumentException('لم يُعثر على ورقة «'.trim($sheetName).'» في ملف Excel.');
            }

            $sheetRecipes = $this->parseSingleSheet($sheet, strict: true);
            $recipes = $sheetRecipes;
            $parsedSheetNames = [$sheet->getTitle()];
        } else {
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $sheetRecipes = $this->parseSingleSheet($sheet, strict: false);
                if ($sheetRecipes === null || $sheetRecipes === []) {
                    continue;
                }

                $parsedSheetNames[] = $sheet->getTitle();
                $recipes = $this->mergeRecipesFromSheets($recipes, $sheetRecipes);
            }
        }

        if ($recipes === []) {
            throw new \InvalidArgumentException('لم يتم العثور على وصفات صالحة داخل الملف.');
        }

        foreach ($recipes as $index => &$recipe) {
            $recipe['order'] = $index + 1;
        }
        unset($recipe);

        return $this->enrichWithExistence($recipes, $parsedSheetNames);
    }

    /**
     * Parse one worksheet into recipe blocks.
     *
     * @return array<int, array<string,mixed>>|null  null when required columns are missing (non-strict only)
     */
    private function parseSingleSheet($sheet, bool $strict = false): ?array
    {
        $headers = $this->readHeaders($sheet);
        $col = $this->resolveColumns($headers);

        if ($col['recipe_name'] === null || $col['ingredient_name'] === null || $col['quantity'] === null) {
            if ($strict) {
                throw new \InvalidArgumentException(
                    'ملف الإكسيل لا يحتوي على الأعمدة المطلوبة (اسم الصنف / الخامات / الكمية).'
                );
            }

            return null;
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

            // --- New recipe block starts when اسم الصنف is filled ---
            if ($recipeName !== '') {
                $order++;
                $currentRecipe = [
                    'order' => $order,
                    'recipe_name' => $recipeName,
                    'normalized_name' => $this->normalizeName($recipeName),
                    'sell_price' => $this->parseOptionalDecimal($sellPriceRaw),
                    'total_direct_cost' => null,
                    'finish_colors' => [],
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
                'color' => null,
                'supports_color' => SupportsColorEstimator::guessFromLabel($ingredientName),
                'source_row' => $row,
            ];
            $currentRecipe = $recipes[$currentRecipe['order']];
        }

        $recipes = array_values(array_filter($recipes, fn ($r) => ! empty($r['ingredients'])));

        if ($recipes === []) {
            if ($strict) {
                throw new \InvalidArgumentException('لم يتم العثور على وصفات صالحة داخل الملف.');
            }

            return [];
        }

        return $recipes;
    }

    /**
     * @param  array<int, array<string,mixed>>  $accumulated
     * @param  array<int, array<string,mixed>>  $incoming
     * @return array<int, array<string,mixed>>
     */
    private function mergeRecipesFromSheets(array $accumulated, array $incoming): array
    {
        $indexByNormalized = [];
        foreach ($accumulated as $index => $recipe) {
            $indexByNormalized[$recipe['normalized_name']] = $index;
        }

        foreach ($incoming as $recipe) {
            $key = $recipe['normalized_name'];
            if (isset($indexByNormalized[$key])) {
                $accumulated[$indexByNormalized[$key]] = $this->mergeDuplicateRecipes(
                    $accumulated[$indexByNormalized[$key]],
                    $recipe,
                );
            } else {
                $indexByNormalized[$key] = count($accumulated);
                $accumulated[] = $recipe;
            }
        }

        return $accumulated;
    }

    /**
     * @param  array<string,mixed>  $base
     * @param  array<string,mixed>  $incoming
     * @return array<string,mixed>
     */
    private function mergeDuplicateRecipes(array $base, array $incoming): array
    {
        $base['ingredients'] = array_merge($base['ingredients'] ?? [], $incoming['ingredients'] ?? []);
        $base['extra_costs'] = array_merge($base['extra_costs'] ?? [], $incoming['extra_costs'] ?? []);

        if (($base['sell_price'] ?? null) === null && ($incoming['sell_price'] ?? null) !== null) {
            $base['sell_price'] = $incoming['sell_price'];
        }
        if (($base['total_direct_cost'] ?? null) === null && ($incoming['total_direct_cost'] ?? null) !== null) {
            $base['total_direct_cost'] = $incoming['total_direct_cost'];
        }

        return $base;
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
                    $compositeKey = $this->ingredientMaterializationKey($ing);
                    $itemId = $itemIdByNormalized[$compositeKey] ?? null;
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

                $finishColors = $recipe['finish_colors'] ?? [];

                $product = $this->ensureFinishedGoodForRecipe(
                    recipe: $recipeModel,
                    cost: $recipeTotalCost,
                    sellPrice: $effectiveSellPrice,
                    result: $result,
                );

                if ($product !== null) {
                    $recipeModel->output_item_id = $product->id;
                    $recipeModel->save();

                    $this->recipeVariants->syncRecipeVariants(
                        $recipeModel->fresh(['ingredients.item']),
                        $product,
                        is_array($finishColors) ? $finishColors : [],
                    );
                }

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
    ): ?Item {
        $linked = Item::query()
            ->where('recipe_id', $recipe->id)
            ->where('warehouse', self::WAREHOUSE_FINISHED)
            ->first();
        if ($linked) {
            if ($linked->stock_id === null) {
                $linked->stock_id = $this->resolveStockIdForWarehouse(self::WAREHOUSE_FINISHED);
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
            color: null,
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
            $existing->total = round($totalCost, 2);
            $existing->save();
            $manufacture = $existing;
            $result->manufacturesUpdated++;
            Log::info('[recipe-import] manufacture already exists, refreshing BOM lines', [
                'manufacture_id' => $existing->id,
                'product_id' => $product->id,
            ]);
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
            $compositeKey = $this->ingredientMaterializationKey($ing);
            $itemId = $itemIdByNormalized[$compositeKey] ?? null;
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

    /**
     * Import / update category rows from a recipe-style Excel sheet without creating recipes.
     * All items are placed in the warehouse chosen by the user.
     *
     * @param  bool  $includeProducts  Recipe header names (اسم الصنف)
     * @param  bool  $includeMaterials  Ingredient rows (الخامات)
     */
    public function importItemsOnly(
        string $absolutePath,
        string $warehouse,
        ?string $sheetName = null,
        bool $includeProducts = true,
        bool $includeMaterials = true,
    ): RecipeSheetItemsImportResult {
        $warehouse = trim($warehouse);
        if ($warehouse === '') {
            throw new \InvalidArgumentException('يجب تحديد المخزن.');
        }

        $parsed = $this->parse($absolutePath, $sheetName);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $warnings = [];

        DB::beginTransaction();
        try {
            if ($includeProducts) {
                foreach ($parsed->recipes as $recipe) {
                    $sellPrice = isset($recipe['sell_price']) ? (float) $recipe['sell_price'] : 0.0;
                    $cost = isset($recipe['total_direct_cost']) ? (float) $recipe['total_direct_cost'] : $sellPrice;
                    if ($cost <= 0 && $sellPrice > 0) {
                        $cost = $sellPrice;
                    }
                    if ($sellPrice <= 0 && $cost > 0) {
                        $sellPrice = $cost;
                    }

                    $outcome = $this->upsertSheetItemInWarehouse(
                        name: (string) $recipe['recipe_name'],
                        normalizedName: (string) $recipe['normalized_name'],
                        warehouse: $warehouse,
                        unitText: null,
                        cost: $cost,
                        sellPrice: $sellPrice,
                        color: null,
                        supportsColor: false,
                    );
                    $this->tallyImportOutcome($outcome, $created, $updated, $skipped);
                }
            }

            if ($includeMaterials) {
                $seen = [];
                foreach ($parsed->recipes as $recipe) {
                    foreach ($recipe['ingredients'] as $ing) {
                        $key = $this->ingredientMaterializationKey($ing);
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;

                        $color = ! empty($ing['supports_color']) ? null : ($ing['color'] ?? null);
                        $price = isset($ing['unit_cost']) ? (float) $ing['unit_cost'] : 0.0;

                        $outcome = $this->upsertSheetItemInWarehouse(
                            name: (string) $ing['item_name'],
                            normalizedName: (string) $ing['normalized_name'],
                            warehouse: $warehouse,
                            unitText: $ing['unit'] ?? null,
                            cost: $price,
                            sellPrice: $price,
                            color: $color,
                            supportsColor: ! empty($ing['supports_color']),
                        );
                        $this->tallyImportOutcome($outcome, $created, $updated, $skipped);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return new RecipeSheetItemsImportResult($created, $updated, $skipped, $warnings);
    }

    /**
     * Import a flat raw-materials price list (e.g. «اسعار خامات» sheet):
     * one row per item — الخامات، الوحده، الكميه، السعر، اكواد كجالس …
     *
     * When $importQuantities is true and a «الكميه» value is present, the quantity
     * is posted as an opening-balance inventory movement + balanced GL entry.
     */
    public function importFlatMaterialsList(
        string $absolutePath,
        string $warehouse,
        ?string $sheetName = null,
        bool $importQuantities = true,
        ?string $performer = null,
        ?int $userId = null,
    ): RecipeSheetItemsImportResult {
        $warehouse = trim($warehouse);
        if ($warehouse === '') {
            throw new \InvalidArgumentException('يجب تحديد المخزن.');
        }

        $rows = $this->parseFlatMaterialsList($absolutePath, $sheetName);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $quantityLines = 0;
        $warnings = [];

        $ledger = $importQuantities ? app(InventoryMovementLedgerService::class) : null;
        $gl = $importQuantities ? app(InventoryGlPostingService::class) : null;

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $price = (float) ($row['unit_cost'] ?? 0);
                $itemCode = $row['item_code'] ?? null;
                $normalizedName = (string) $row['normalized_name'];

                $defaults = config('items_import.new_item', []);
                $productionId = $this->resolveProductionIdFromDepartment(
                    $warehouse,
                    $row['department'] ?? null,
                    $defaults,
                );

                $outcome = $this->upsertSheetItemInWarehouse(
                    name: (string) $row['name'],
                    normalizedName: $normalizedName,
                    warehouse: $warehouse,
                    unitText: $row['unit'] ?? null,
                    cost: $price,
                    sellPrice: $price,
                    color: null,
                    supportsColor: false,
                    itemCode: $itemCode,
                    productionId: $productionId,
                    itemClassification: $row['classification'] ?? null,
                );
                $this->tallyImportOutcome($outcome, $created, $updated, $skipped);

                $qty = isset($row['quantity']) ? (float) $row['quantity'] : 0.0;
                if ($importQuantities && $ledger !== null && abs($qty) > 0.0000001) {
                    $item = $this->locateWarehouseItem($warehouse, $itemCode, $normalizedName);
                    if ($item !== null) {
                        $ledger->recordInbound(
                            $item,
                            InventoryMovementType::OpeningBalance,
                            $qty,
                            $price,
                            $qty * $price,
                            true,
                            'excel_materials_list',
                            (int) $item->id,
                            'رصيد افتتاحي — استيراد شيت الخامات',
                            null,
                            $performer,
                        );

                        $inv = TreeAccount::resolveInventoryAccountForCategoryId((int) $item->id);
                        if ($gl !== null) {
                            $gl->postOpeningInventory(
                                $qty * $price,
                                'افتتاحي شيت الخامات — '.$item->category_name,
                                $userId,
                                $inv,
                            );
                        }
                        $quantityLines++;
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($quantityLines > 0) {
            $warnings[] = 'تم ترحيل كميات افتتاحية لعدد '.$quantityLines.' صنف.';
        }

        return new RecipeSheetItemsImportResult($created, $updated, $skipped, $warnings);
    }

    /**
     * Fetch a warehouse item by item_code first, then by normalized name.
     */
    private function locateWarehouseItem(string $warehouse, ?string $itemCode, string $normalizedName): ?Item
    {
        if ($itemCode !== null && $itemCode !== '') {
            $byCode = Item::query()
                ->where('warehouse', $warehouse)
                ->where('item_code', $itemCode)
                ->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        return $this->findExistingItemInWarehouse($warehouse, $normalizedName, null, false);
    }

    /**
     * @return list<array{name:string,normalized_name:string,unit:?string,unit_cost:?float,item_code:?string,quantity:?float}>
     */
    private function parseFlatMaterialsList(string $absolutePath, ?string $sheetName = null): array
    {
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException('File not readable: '.$absolutePath);
        }

        $spreadsheet = $this->loadFlatMaterialsSpreadsheet($absolutePath, $sheetName);
        $sheet = $spreadsheet->getSheet(0);

        [$headerRow, $headers] = $this->locateFlatHeaderRow($sheet);
        $col = [
            'name' => $this->matchColumn($headers, [
                'الخامات', 'الخامة', 'الخامه', 'خامة', 'خامه', 'مادة', 'المادة',
                'اسم الصنف', 'اسم_الصنف', 'الصنف', 'البيانات', 'الاصناف',
                'name', 'category_name', 'item_name', 'product_name',
            ]),
            'unit_cost' => $this->matchColumn($headers, [
                'السعر', 'سعر', 'unit_cost', 'price', 'category_price',
            ]),
            'unit' => $this->matchColumn($headers, [
                'الوحده', 'الوحدة', 'unit',
            ]),
            'quantity' => $this->matchColumn($headers, [
                'الكميه', 'الكمية', 'كمية', 'quantity', 'qty',
            ]),
            'item_code' => $this->matchColumn($headers, [
                'اكواد كجالس', 'اكواد', 'اكواد_كجالس', 'codes',
                'item_code', 'product_code', 'code', 'sku',
                'كود الصنف', 'كود_الصنف', 'كود', 'الباركود', 'الكود',
            ]),
            'classification' => $this->matchColumn($headers, [
                'التصنيف', 'تصنيف', 'classification', 'item_classification',
            ]),
            'department' => $this->matchColumn($headers, [
                'القسم', 'قسم', 'department', 'خط الانتاج', 'فرع الانتاج', 'production_line',
            ]),
        ];

        if ($col['name'] === null) {
            throw new \InvalidArgumentException(
                'ملف الإكسيل لا يحتوي على عمود «الخامات» أو «اسم الصنف».'
            );
        }

        $highestRow = min(self::FLAT_SHEET_MAX_ROW, (int) $sheet->getHighestDataRow());
        $rows = [];

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            if ($this->flatRowIsEmpty($sheet, $row, $col)) {
                continue;
            }

            $name = $this->cell($sheet, $row, $col['name']);
            if ($name === '' || $this->isIgnoreLabel($name) || $this->isFooterLabel($name)) {
                continue;
            }

            $unitCostRaw = $this->cell($sheet, $row, $col['unit_cost']);
            $unitRaw = $this->cell($sheet, $row, $col['unit']);
            $codeRaw = $this->cell($sheet, $row, $col['item_code']);
            $qtyRaw = $this->cell($sheet, $row, $col['quantity']);
            $classRaw = $this->cell($sheet, $row, $col['classification']);
            $deptRaw = $this->cell($sheet, $row, $col['department']);
            $unitCost = $this->parseOptionalDecimal($unitCostRaw);
            $qty = $this->parseOptionalDecimal($qtyRaw);

            $rows[] = [
                'name' => $name,
                'normalized_name' => $this->normalizeName($name),
                'unit' => $unitRaw !== '' ? $unitRaw : null,
                'unit_cost' => $unitCost,
                'item_code' => $codeRaw !== '' ? $codeRaw : null,
                'quantity' => $qty,
                'classification' => $classRaw !== '' ? $classRaw : null,
                'department' => $deptRaw !== '' ? $deptRaw : null,
            ];
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('لم يتم العثور على أصناف صالحة داخل الورقة المحددة.');
        }

        return $rows;
    }

    /**
     * Load only the needed sheet with a bounded read filter (prevents 2GB+ memory use).
     */
    private function loadFlatMaterialsSpreadsheet(string $absolutePath, ?string $sheetName): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $this->applyLightweightReaderOptions($reader);

        $target = trim((string) ($sheetName ?? ''));
        if ($target !== '') {
            if (method_exists($reader, 'listWorksheetNames')) {
                $available = $reader->listWorksheetNames($absolutePath);
                if ($available !== [] && ! in_array($target, $available, true)) {
                    throw new \InvalidArgumentException(
                        'لم يُعثر على ورقة «'.$target.'» في ملف Excel. الأوراق المتاحة: '.implode('، ', $available)
                    );
                }
            }
            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$target]);
            }

            return $reader->load($absolutePath);
        }

        $nameAliases = [
            'الخامات', 'الخامة', 'الخامه', 'اسم الصنف', 'الصنف', 'البيانات', 'الاصناف',
            'name', 'category_name', 'item_name', 'product_name',
        ];

        if (method_exists($reader, 'listWorksheetNames')) {
            foreach ($reader->listWorksheetNames($absolutePath) as $name) {
                $try = IOFactory::createReaderForFile($absolutePath);
                $this->applyLightweightReaderOptions($try);
                if (method_exists($try, 'setLoadSheetsOnly')) {
                    $try->setLoadSheetsOnly([$name]);
                }
                $ss = $try->load($absolutePath);
                $sheet = $ss->getSheet(0);
                [, $headers] = $this->locateFlatHeaderRow($sheet);
                if ($this->matchColumn($headers, $nameAliases) !== null) {
                    return $ss;
                }
                unset($ss, $try);
            }
        }

        if (method_exists($reader, 'listWorksheetNames')) {
            $names = $reader->listWorksheetNames($absolutePath);
            if ($names !== [] && method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$names[0]]);
            }
        }

        return $reader->load($absolutePath);
    }

    private function applyLightweightReaderOptions(object $reader): void
    {
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new LimitedExcelReadFilter(
                maxRow: self::FLAT_SHEET_MAX_ROW,
                maxCol: self::FLAT_SHEET_MAX_COL,
            ));
        }
    }

    /**
     * @param  array<string, int|null>  $col
     */
    private function flatRowIsEmpty($sheet, int $row, array $col): bool
    {
        foreach ($col as $key) {
            if ($key === null) {
                continue;
            }
            $letter = Coordinate::stringFromColumnIndex($key);
            $v = $sheet->getCell($letter.$row)->getValue();
            if ($v !== null && trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Pick the sheet to read: prefer the named sheet, then scan all sheets for one
     * whose header contains a recognizable «الخامات»/«اسم الصنف» column, else sheet 0.
     *
     * @deprecated Use loadFlatMaterialsSpreadsheet() instead.
     */
    private function resolveFlatMaterialsSheet($spreadsheet, ?string $sheetName)
    {
        if ($sheetName !== null && trim($sheetName) !== '') {
            $named = $spreadsheet->getSheetByName(trim($sheetName));
            if ($named !== null) {
                return $named;
            }
            throw new \InvalidArgumentException('لم يُعثر على ورقة «'.$sheetName.'» في ملف Excel.');
        }

        $nameAliases = [
            'الخامات', 'الخامة', 'الخامه', 'اسم الصنف', 'الصنف', 'البيانات', 'الاصناف',
            'name', 'category_name', 'item_name', 'product_name',
        ];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            [, $headers] = $this->locateFlatHeaderRow($sheet);
            if ($this->matchColumn($headers, $nameAliases) !== null) {
                return $sheet;
            }
        }

        return $spreadsheet->getSheet(0);
    }

    /**
     * Find the header row (1-based) within the first rows of a sheet. Returns the
     * row number and the header labels keyed by 1-based column index.
     *
     * @return array{0:int,1:array<int,string>}
     */
    private function locateFlatHeaderRow($sheet): array
    {
        $maxColIndex = self::FLAT_SHEET_MAX_COL;
        $maxScan = min(10, min(self::FLAT_SHEET_MAX_ROW, (int) $sheet->getHighestDataRow()));

        $nameAliases = [
            'الخامات', 'الخامة', 'الخامه', 'اسم الصنف', 'الصنف', 'البيانات', 'الاصناف',
            'name', 'category_name', 'item_name', 'product_name',
        ];

        $fallback = null;
        for ($r = 1; $r <= max(1, $maxScan); $r++) {
            $headers = [];
            $nonEmpty = 0;
            for ($c = 1; $c <= $maxColIndex; $c++) {
                $letter = Coordinate::stringFromColumnIndex($c);
                $val = trim((string) $sheet->getCell($letter.$r)->getValue());
                $headers[$c] = $val;
                if ($val !== '') {
                    $nonEmpty++;
                }
            }
            if ($nonEmpty < 2) {
                continue;
            }
            if ($fallback === null) {
                $fallback = [$r, $headers];
            }
            if ($this->matchColumn($headers, $nameAliases) !== null) {
                return [$r, $headers];
            }
        }

        return $fallback ?? [1, $this->readFlatHeaders($sheet)];
    }

    /** @return array<int,string> */
    private function readFlatHeaders($sheet): array
    {
        $headers = [];
        for ($c = 1; $c <= self::FLAT_SHEET_MAX_COL; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $headers[$c] = trim((string) $sheet->getCell($letter.'1')->getValue());
        }

        return $headers;
    }

    /**
     * @return 'created'|'updated'|'skipped'
     */
    private function upsertSheetItemInWarehouse(
        string $name,
        string $normalizedName,
        string $warehouse,
        ?string $unitText,
        float $cost,
        float $sellPrice,
        ?string $color,
        bool $supportsColor,
        ?string $itemCode = null,
        ?int $productionId = null,
        ?string $itemClassification = null,
    ): string {
        $existing = null;
        if ($itemCode !== null && $itemCode !== '') {
            $existing = Item::query()
                ->where('warehouse', $warehouse)
                ->where('item_code', $itemCode)
                ->first();
        }
        $existing ??= $this->findExistingItemInWarehouse($warehouse, $normalizedName, $color, $supportsColor);

        if ($existing !== null) {
            $updates = [];
            if ($cost > 0 && (float) $existing->unit_price !== $cost) {
                $updates['unit_price'] = $cost;
            }
            if ($sellPrice > 0 && (float) $existing->category_price !== $sellPrice) {
                $updates['category_price'] = $sellPrice;
            }
            if ($color !== null && $color !== '' && empty($existing->color)) {
                $updates['color'] = $color;
            }
            if ($supportsColor && ! $existing->supports_color) {
                $updates['supports_color'] = true;
            }
            if ($unitText !== null && $unitText !== '') {
                $measurementId = $this->resolveMeasurementIdForWarehouse($warehouse, $unitText, config('items_import.new_item', []));
                if ((int) $existing->measurement_id !== $measurementId) {
                    $updates['measurement_id'] = $measurementId;
                }
            }
            $stockId = $this->resolveStockIdForWarehouse($warehouse);
            if ((int) $existing->stock_id !== $stockId) {
                $updates['stock_id'] = $stockId;
            }
            if ($itemCode !== null && $itemCode !== '' && (string) ($existing->item_code ?? '') !== $itemCode) {
                $this->itemCodes->assertCodeAvailable($itemCode, (int) $existing->id);
                $updates['item_code'] = $itemCode;
            }
            if ($productionId !== null && (int) $existing->production_id !== $productionId) {
                $updates['production_id'] = $productionId;
            }
            if ($itemClassification !== null && $itemClassification !== '') {
                $classificationId = $this->resolveClassificationIdFromText($warehouse, $itemClassification);
                if ((int) ($existing->item_classification_id ?? 0) !== $classificationId) {
                    $updates['item_classification_id'] = $classificationId;
                }
            }

            if ($updates !== []) {
                $existing->update($updates);

                return 'updated';
            }

            return 'skipped';
        }

        if ($supportsColor) {
            $this->createItem(
                name: $name,
                warehouse: $warehouse,
                unitText: $unitText,
                price: $cost > 0 ? $cost : ($sellPrice > 0 ? $sellPrice : null),
                recipeId: null,
                color: $color,
                supportsColor: true,
                itemCode: $itemCode,
                productionId: $productionId,
                itemClassification: $itemClassification,
            );
        } elseif ($cost > 0 || $sellPrice > 0) {
            $this->createItemWithSeparatePrices(
                name: $name,
                warehouse: $warehouse,
                cost: max(0, $cost),
                sellPrice: max(0, $sellPrice > 0 ? $sellPrice : $cost),
                recipeId: null,
                color: $color,
                itemCode: $itemCode,
                unitText: $unitText,
                productionId: $productionId,
                itemClassification: $itemClassification,
            );
        } else {
            $this->createItem(
                name: $name,
                warehouse: $warehouse,
                unitText: $unitText,
                price: null,
                recipeId: null,
                color: $color,
                supportsColor: false,
                itemCode: $itemCode,
                productionId: $productionId,
                itemClassification: $itemClassification,
            );
        }

        return 'created';
    }

    private function findExistingItemInWarehouse(
        string $warehouse,
        string $normalizedName,
        ?string $color,
        bool $supportsColor,
    ): ?Item {
        if ($normalizedName === '') {
            return null;
        }

        $group = [];
        Item::query()
            ->select(['id', 'category_name', 'category_price', 'unit_price', 'color', 'supports_color', 'measurement_id', 'stock_id'])
            ->where('warehouse', $warehouse)
            ->orderBy('id')
            ->chunk(2000, function ($rows) use (&$group, $normalizedName) {
                foreach ($rows as $row) {
                    if ($this->normalizeName((string) $row->category_name) !== $normalizedName) {
                        continue;
                    }
                    $group[] = [
                        'id' => (int) $row->id,
                        'category_name' => (string) $row->category_name,
                        'category_price' => $row->category_price !== null ? (float) $row->category_price : null,
                        'unit_price' => $row->unit_price !== null ? (float) $row->unit_price : null,
                        'color' => $row->color !== null ? (string) $row->color : null,
                        'normalized_color' => $this->normalizeName((string) ($row->color ?? '')),
                        'color_claimed' => trim((string) ($row->color ?? '')) !== '',
                        'supports_color' => (bool) $row->supports_color,
                        'parent_item_id' => null,
                        'color_id' => null,
                        'measurement_id' => (int) $row->measurement_id,
                        'stock_id' => (int) $row->stock_id,
                    ];
                }
            });

        if ($group === []) {
            return null;
        }

        $reserved = [];
        if ($supportsColor) {
            $matched = $this->matchSupportsColorBaseItem($group);
        } else {
            $matched = $this->matchItemFromColorGroup($group, $color, $reserved);
        }

        if ($matched === null) {
            return null;
        }

        return Item::query()->find($matched['id']);
    }

    private function tallyImportOutcome(string $outcome, int &$created, int &$updated, int &$skipped): void
    {
        if ($outcome === 'created') {
            $created++;
        } elseif ($outcome === 'updated') {
            $updated++;
        } else {
            $skipped++;
        }
    }

    // ==========================================================================
    // Internals
    // ==========================================================================

    /**
     * Build a ParsedRecipeSheet from an array of structured recipe blocks.
     * Annotates each recipe with `exists=true/false` and produces a global
     * list of `missingItems`.
     *
     * Uses color-aware matching: "سوسته 10 مللي (Black)" and "سوسته 10 مللي
     * (Maroon)" are treated as distinct items.
     *
     * @param  array<int, array<string,mixed>>  $recipes
     */
    private function enrichWithExistence(array $recipes, array $parsedSheetNames = []): ParsedRecipeSheet
    {
        $allNormalizedNames = [];
        foreach ($recipes as $recipe) {
            foreach ($recipe['ingredients'] as $ing) {
                $allNormalizedNames[$ing['normalized_name']] = true;
            }
        }

        $existingByNameAndColor = $this->findExistingItemsByNormalizedNameAndColor(array_keys($allNormalizedNames));

        // Track which colorless DB items have been "reserved" for a specific
        // color during preview so the same colorless item is not promised to
        // two different colors.
        // Key = item DB id, value = true (already reserved).
        $reservedColorlessIds = [];

        // First pass: collect all unique composite keys and determine missing.
        $missingItems = [];
        $seenComposites = [];
        // Also store matched item ids per composite key for the second pass.
        $matchByComposite = [];

        foreach ($recipes as $recipe) {
            foreach ($recipe['ingredients'] as $ing) {
                $compositeKey = $this->ingredientMaterializationKey($ing);
                if (isset($seenComposites[$compositeKey])) {
                    continue;
                }
                $seenComposites[$compositeKey] = true;

                $group = $existingByNameAndColor[$ing['normalized_name']] ?? [];
                if (! empty($ing['supports_color'])) {
                    $matched = $this->matchSupportsColorBaseItem($group);
                } else {
                    $matched = $this->matchItemFromColorGroup(
                        $group,
                        $ing['color'] ?? null,
                        $reservedColorlessIds,
                    );
                }
                $matchByComposite[$compositeKey] = $matched;

                if ($matched === null) {
                    $label = $ing['item_name'];
                    if (! empty($ing['supports_color'])) {
                        $label .= ' [قاعدة خام قابلة للتلوين]';
                    } elseif (! empty($ing['color'])) {
                        $label .= ' (' . $ing['color'] . ')';
                    }
                    $missingItems[] = $label;
                }
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
                $compositeKey = $this->ingredientMaterializationKey($ing);
                $match = $matchByComposite[$compositeKey] ?? null;
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
            parsedSheetNames: $parsedSheetNames,
        );
    }

    /**
     * Given a list of DB rows with the same normalized name but potentially
     * different colors, find the best match for the requested color.
     *
     * Priority:
     *  1) Exact color match (item already has this color)
     *  2) If requested color is non-empty and a colorless item exists that has
     *     NOT been reserved for another color → reserve it (will be updated
     *     with this color during commit)
     *  3) If requested color is empty → return the first available item
     *  4) Otherwise → null (item is missing, needs to be created)
     *
     * @param  list<array{id:int, category_name:string, category_price:float|null, color:?string, normalized_color:string, color_claimed:bool}>  $group
     * @param  array<int,bool>  &$reservedColorlessIds  tracks colorless items already promised to a color
     * @return array{id:int, category_name:string, category_price:float|null}|null
     */
    private function matchItemFromColorGroup(array $group, ?string $requestedColor, array &$reservedColorlessIds = []): ?array
    {
        if (empty($group)) {
            return null;
        }

        $normalizedRequested = ($requestedColor !== null && $requestedColor !== '')
            ? $this->normalizeName($requestedColor)
            : '';

        if ($normalizedRequested !== '') {
            // 1) Exact color match — always wins
            foreach ($group as $row) {
                if ($row['normalized_color'] === $normalizedRequested) {
                    return $row;
                }
            }
            // 2) Colorless item not yet reserved → adopt it for this color
            foreach ($group as $row) {
                if ($row['normalized_color'] === '' && ! isset($reservedColorlessIds[$row['id']])) {
                    $reservedColorlessIds[$row['id']] = true;
                    return $row;
                }
            }
            // 3) No match at all → missing
            return null;
        }

        // No color requested → any item with same name
        return $group[0] ?? null;
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
     * Like findExistingItemsByNormalizedName but groups results by normalized
     * name and includes the color field so that the caller can distinguish
     * "سوسته 10 مللي (Black)" from "سوسته 10 مللي (Maroon)".
     *
     * @param  array<int,string>  $normalizedNames
     * @return array<string, list<array{id:int, category_name:string, category_price:float|null, color:?string, normalized_color:string, color_claimed:bool, supports_color:bool, parent_item_id:?int, color_id:?int}>>
     */
    private function findExistingItemsByNormalizedNameAndColor(array $normalizedNames): array
    {
        if (empty($normalizedNames)) {
            return [];
        }

        $needed = array_flip($normalizedNames);
        /** @var array<string, list<array>> $map */
        $map = [];

        Item::query()
            ->select(['id', 'category_name', 'category_price', 'color', 'supports_color', 'parent_item_id', 'color_id'])
            ->whereNotNull('category_name')
            ->orderBy('id')
            ->chunk(2000, function ($rows) use (&$map, $needed) {
                foreach ($rows as $row) {
                    $key = $this->normalizeName((string) $row->category_name);
                    if ($key === '' || ! isset($needed[$key])) {
                        continue;
                    }
                    $color = $row->color !== null ? trim((string) $row->color) : null;
                    $normalizedColor = ($color !== null && $color !== '')
                        ? $this->normalizeName($color)
                        : '';

                    $map[$key][] = [
                        'id' => (int) $row->id,
                        'category_name' => (string) $row->category_name,
                        'category_price' => $row->category_price !== null ? (float) $row->category_price : null,
                        'color' => $color ?: null,
                        'normalized_color' => $normalizedColor,
                        'color_claimed' => false,
                        'supports_color' => (bool) ($row->supports_color ?? false),
                        'parent_item_id' => $row->parent_item_id !== null ? (int) $row->parent_item_id : null,
                        'color_id' => $row->color_id !== null ? (int) $row->color_id : null,
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
     * Returns a map of composite_key => item_id for every ingredient in the sheet,
     * creating missing items in مخزن مواد خام on the fly when allowed by the user.
     *
     * Composite key = normalized_name + "|" + normalized_color so that the same
     * raw material in different colors (e.g. "سوسته 10 مللي Black" vs "Maroon")
     * is stored as separate items while reusing an existing colourless item when
     * its color field is still empty.
     *
     * @return array<string,int>  composite_key => item_id
     */
    private function materializeItems(ParsedRecipeSheet $parsed, bool $createMissing, RecipeImportCommitResult $result): array
    {
        /** @var array<string, array{name:string, normalized_name:string, unit:?string, price:?float, color:?string, supports_sc:bool}> $candidates */
        $candidates = [];
        foreach ($parsed->recipes as $recipe) {
            foreach ($recipe['ingredients'] as $ing) {
                $key = $this->ingredientMaterializationKey($ing);
                if (! isset($candidates[$key])) {
                    $candidates[$key] = [
                        'name' => $ing['item_name'],
                        'normalized_name' => $ing['normalized_name'],
                        'unit' => $ing['unit'] ?? null,
                        'price' => $ing['unit_cost'] ?? null,
                        'color' => ! empty($ing['supports_color']) ? null : ($ing['color'] ?? null),
                        'supports_sc' => ! empty($ing['supports_color']),
                    ];

                    continue;
                }
                if ($candidates[$key]['unit'] === null && ! empty($ing['unit'])) {
                    $candidates[$key]['unit'] = $ing['unit'];
                }
                if (($candidates[$key]['price'] === null || $candidates[$key]['price'] <= 0)
                    && ($ing['unit_cost'] ?? null) !== null
                    && $ing['unit_cost'] > 0
                ) {
                    $candidates[$key]['price'] = (float) $ing['unit_cost'];
                }
            }
        }

        $allNormalizedNames = array_values(array_unique(
            array_column($candidates, 'normalized_name')
        ));
        $existing = $this->findExistingItemsByNormalizedNameAndColor($allNormalizedNames);

        $map = [];

        foreach ($candidates as $compositeKey => $meta) {
            $color = $meta['color'];
            $normalizedColor = $color !== null ? $this->normalizeName($color) : '';
            $nameKey = $meta['normalized_name'];
            $isSc = ! empty($meta['supports_sc']);

            $matchedItemId = null;

            if ($isSc && isset($existing[$nameKey])) {
                $pick = $this->matchSupportsColorBaseItem($existing[$nameKey]);
                if ($pick !== null) {
                    $matchedItemId = (int) $pick['id'];
                }
            }

            if ($matchedItemId === null && isset($existing[$nameKey])) {
                if ($normalizedColor !== '') {
                    foreach ($existing[$nameKey] as $row) {
                        if ($row['normalized_color'] === $normalizedColor) {
                            $matchedItemId = (int) $row['id'];
                            break;
                        }
                    }
                }

                if ($matchedItemId === null) {
                    $first = $existing[$nameKey][0] ?? null;
                    if ($first) {
                        $matchedItemId = (int) $first['id'];
                    }
                }
            }

            if ($matchedItemId !== null) {
                $map[$compositeKey] = $matchedItemId;
                Log::info('[recipe-import] ingredient matched existing item', [
                    'name' => $meta['name'],
                    'color' => $color,
                    'supports_sc' => $isSc,
                    'item_id' => $matchedItemId,
                ]);

                continue;
            }

            if (! $createMissing) {
                $label = $meta['name'];
                if ($isSc) {
                    $label .= ' [قاعدة خام قابلة للتلوين]';
                }
                $label .= $color ? ' ('.$color.')' : '';
                throw new \InvalidArgumentException('الخامة "'.$label.'" غير موجودة وتم رفض إنشاء الأصناف.');
            }

            $item = $this->createItem(
                name: $meta['name'],
                warehouse: self::WAREHOUSE_RAW,
                unitText: $meta['unit'],
                price: $meta['price'],
                color: $color,
                supportsColor: $isSc,
            );
            $result->itemsCreated++;
            $map[$compositeKey] = (int) $item->id;

            if (! isset($existing[$nameKey])) {
                $existing[$nameKey] = [];
            }
            $newNormColor = ($color !== null && $color !== '') ? $this->normalizeName($color) : '';
            $existing[$nameKey][] = [
                'id' => (int) $item->id,
                'category_name' => $item->category_name,
                'category_price' => (float) $item->category_price,
                'color' => $color,
                'normalized_color' => $newNormColor,
                'color_claimed' => true,
                'supports_color' => $isSc,
                'parent_item_id' => null,
                'color_id' => null,
            ];

            Log::info('[recipe-import] raw material item created', [
                'id' => $item->id,
                'name' => $item->category_name,
                'color' => $color,
                'supports_sc' => $isSc,
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
     * Build a composite key for an ingredient: normalized_name + "|" + normalized_color.
     * Two rows with the same name but different colors produce different keys.
     */
    private function ingredientCompositeKey(string $normalizedName, ?string $color): string
    {
        $colorPart = ($color !== null && $color !== '')
            ? $this->normalizeName($color)
            : '';

        return $normalizedName . '|' . $colorPart;
    }

    /** One logical BOM line for materials that track production color dynamically. */
    private function ingredientMaterializationKey(array $ing): string
    {
        if (! empty($ing['supports_color'])) {
            return $ing['normalized_name'] . '|__SC__';
        }

        return $this->ingredientCompositeKey($ing['normalized_name'], $ing['color'] ?? null);
    }

    /**
     * @param  list<array{id:int, supports_color?:bool, parent_item_id?:?int, normalized_color:string}>  $group
     */
    private function matchSupportsColorBaseItem(array $group): ?array
    {
        $candidates = [];
        foreach ($group as $row) {
            if (($row['parent_item_id'] ?? null) !== null) {
                continue;
            }
            if (($row['normalized_color'] ?? '') !== '') {
                continue;
            }
            $candidates[] = $row;
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $a, array $b): int {
            $sa = ($a['supports_color'] ?? false) ? 1 : 0;
            $sb = ($b['supports_color'] ?? false) ? 1 : 0;
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
        });

        return $candidates[0] ?? null;
    }

    /**
     * عناصر شكلية بين الصنف وباقي الألوان (مثل «جديد») — ليست خامات.
     */
    private function isManufacturingRecipeMetaLabel(string $raw): bool
    {
        $n = $this->normalizeName($raw);
        $metas = [
            'جديد', 'جديده', 'جديدة',
            'new',
            '-',
            '—',
            '--',
            '***',
        ];
        foreach ($metas as $m) {
            if ($n === $this->normalizeName($m)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep ingredient color only when the sheet declares that shade for the finished product.
     * Otherwise match/create raw materials without a color (same name, empty color field).
     */
    private function sanitizeIngredientColorsForRecipe(array &$recipe, bool $sheetHasColorColumn): void
    {
        $allowed = $this->normalizedColorSet($recipe['finish_colors'] ?? []);

        foreach ($recipe['ingredients'] as &$ing) {
            if (! empty($ing['supports_color'])) {
                $ing['color'] = null;

                continue;
            }

            if (! $sheetHasColorColumn || $allowed === []) {
                $ing['color'] = null;

                continue;
            }

            $raw = trim((string) ($ing['color'] ?? ''));
            if ($raw === '') {
                continue;
            }

            if (! isset($allowed[$this->normalizeName($raw)])) {
                $ing['color'] = null;
            }
        }
        unset($ing);
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<string, true>
     */
    private function normalizedColorSet(array $labels): array
    {
        $set = [];
        foreach ($labels as $label) {
            $n = $this->normalizeName((string) $label);
            if ($n !== '') {
                $set[$n] = true;
            }
        }

        return $set;
    }

    /** Colors available for the finished good (header column «ألوانه» / variants). */
    private function splitRecipeHeaderColors(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\n\r،,؛;\/|\t]+/u', $raw) ?: [];

        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
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
        bool $supportsColor = false,
        ?string $itemCode = null,
        ?int $productionId = null,
        ?string $itemClassification = null,
    ): Item {
        $defaults = config('items_import.new_item', []);

        $effectivePrice = $price !== null && $price >= 0
            ? (float) $price
            : (float) ($defaults['category_price'] ?? 0);

        if ($itemCode !== null && $itemCode !== '') {
            $this->itemCodes->assertCodeAvailable($itemCode);
        }

        $attrs = [
            'category_name' => $name,
            'category_price' => $effectivePrice,
            'unit_price' => $effectivePrice,
            'initial_balance' => (float) ($defaults['initial_balance'] ?? 0),
            'minimum_quantity' => (float) ($defaults['minimum_quantity'] ?? 0),
            'warehouse' => $warehouse,
            'production_id' => $productionId ?? $this->resolveProductionIdForWarehouse($warehouse, $defaults),
            'measurement_id' => $this->resolveMeasurementIdForWarehouse($warehouse, $unitText, $defaults),
            'stock_id' => $this->resolveStockIdForWarehouse($warehouse),
            'category_image' => (string) ($defaults['category_image'] ?? ''),
            'item_code' => ($itemCode !== null && $itemCode !== '') ? $itemCode : null,
            'item_classification_id' => ($itemClassification !== null && $itemClassification !== '')
                ? $this->resolveClassificationIdFromText($warehouse, $itemClassification)
                : null,
            'color' => $color,
            'recipe_id' => $recipeId,
            'supports_color' => $supportsColor,
            'parent_item_id' => null,
            'color_id' => null,
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
        ?string $itemCode = null,
        ?string $unitText = null,
        ?int $productionId = null,
        ?string $itemClassification = null,
    ): Item {
        $defaults = config('items_import.new_item', []);

        if ($itemCode !== null && $itemCode !== '') {
            $this->itemCodes->assertCodeAvailable($itemCode);
        }

        $attrs = [
            'category_name' => $name,
            'category_price' => $sellPrice,
            'unit_price' => $cost,
            'initial_balance' => (float) ($defaults['initial_balance'] ?? 0),
            'minimum_quantity' => (float) ($defaults['minimum_quantity'] ?? 0),
            'warehouse' => $warehouse,
            'production_id' => $productionId ?? $this->resolveProductionIdForWarehouse($warehouse, $defaults),
            'measurement_id' => $this->resolveMeasurementIdForWarehouse($warehouse, $unitText, $defaults),
            'stock_id' => $this->resolveStockIdForWarehouse($warehouse),
            'category_image' => (string) ($defaults['category_image'] ?? ''),
            'item_code' => ($itemCode !== null && $itemCode !== '') ? $itemCode : null,
            'item_classification_id' => ($itemClassification !== null && $itemClassification !== '')
                ? $this->resolveClassificationIdFromText($warehouse, $itemClassification)
                : null,
            'color' => $color,
            'recipe_id' => $recipeId,
            'supports_color' => false,
            'parent_item_id' => null,
            'color_id' => null,
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

    private function resolveProductionIdFromDepartment(string $warehouse, ?string $departmentText, array $defaults): int
    {
        $departmentText = trim((string) ($departmentText ?? ''));
        if ($departmentText === '') {
            return $this->resolveProductionIdForWarehouse($warehouse, $defaults);
        }

        $normalized = $this->normalizeName($departmentText);
        $lines = Production::query()
            ->where('warehouse', $warehouse)
            ->get(['id', 'production_line']);

        foreach ($lines as $line) {
            if ($this->normalizeName((string) $line->production_line) === $normalized) {
                return (int) $line->id;
            }
        }

        foreach ($lines as $line) {
            $lineNorm = $this->normalizeName((string) $line->production_line);
            if ($lineNorm !== '' && (str_contains($normalized, $lineNorm) || str_contains($lineNorm, $normalized))) {
                return (int) $line->id;
            }
        }

        $created = Production::query()->create([
            'warehouse' => $warehouse,
            'production_line' => $departmentText,
        ]);

        return (int) $created->id;
    }

    private function resolveClassificationIdFromText(string $warehouse, ?string $classificationText): int
    {
        $classificationText = trim((string) ($classificationText ?? ''));
        if ($classificationText === '') {
            throw new \InvalidArgumentException('Classification text is required.');
        }

        $normalized = $this->normalizeName($classificationText);
        $rows = ItemClassification::query()
            ->where('warehouse', $warehouse)
            ->get(['id', 'classification_name']);

        foreach ($rows as $row) {
            if ($this->normalizeName((string) $row->classification_name) === $normalized) {
                return (int) $row->id;
            }
        }

        foreach ($rows as $row) {
            $rowNorm = $this->normalizeName((string) $row->classification_name);
            if ($rowNorm !== '' && (str_contains($normalized, $rowNorm) || str_contains($rowNorm, $normalized))) {
                return (int) $row->id;
            }
        }

        $created = ItemClassification::query()->create([
            'warehouse' => $warehouse,
            'classification_name' => $classificationText,
        ]);

        return (int) $created->id;
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
        return ArabicTextNormalizer::normalize($raw);
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
            // ملفات العملاء غالباً تستخدم «ألوانه / الوانه» وليس «اللون» فقط.
            'color' => $this->matchColumn($headers, [
                'color', 'colour', 'shade', 'colourway',
                'اللون', 'الون', 'لون',
                'ألوانه', 'الوانه', 'ألوانها', 'الوانها',
                'ألوان', 'الوان', 'الألوان', 'الالوان',
                'لون الخامة', 'لون الخامه', 'لون الصنف',
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
