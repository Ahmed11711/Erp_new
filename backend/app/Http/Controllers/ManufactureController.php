<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\ConfirmedManfucture;
use App\Models\Item;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\Stock;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CategoryInventoryCostService;
use App\Enums\InventoryMovementType;
use App\Services\Inventory\InventoryMovementLedgerService;
use App\Exceptions\Manufacturing\ProductAlreadyCompletedException;
use App\Services\Manufacturing\ItemsWithoutRecipeReportService;
use App\Services\Manufacturing\ManufactureRecipeSyncService;
use App\Services\Manufacturing\ManufacturingConsumptionPlannerService;
use App\Services\Manufacturing\ManufacturingConsumptionResolver;
use App\Services\Manufacturing\ManufacturingOrderReversalService;
use App\Services\Manufacturing\ManufacturingRecipeFromConsumptionService;
use App\Services\Manufacturing\WipToFinishedPromotionService;

class ManufactureController extends Controller
{
    public function __construct(
        private ManufactureRecipeSyncService $manufactureRecipeSync,
        private ItemsWithoutRecipeReportService $itemsWithoutRecipeReport,
    ) {
    }

    /**
     * تقرير الأصناف (منتج تام / تحت التشغيل) التي لا تملك وصفة تصنيع.
     */
    public function itemsWithoutRecipes(Request $request)
    {
        $request->validate([
            'warehouse' => 'nullable|string|max:255',
            'product_type' => 'nullable|string|in:finished,semi_finished',
            'search' => 'nullable|string|max:200',
        ]);

        $result = $this->itemsWithoutRecipeReport->report(
            $request->input('warehouse'),
            $request->input('product_type'),
            $request->filled('search') ? trim((string) $request->search) : null,
        );

        return response()->json($result, 200);
    }

    /**
     * صنف واحد لنموذج إضافة الوصفة (بدون الاعتماد على صلاحيات categories).
     */
    public function recipeProduct(int $id)
    {
        $item = Item::query()->find($id);
        if (! $item) {
            return response()->json(['message' => 'الصنف غير موجود.'], 404);
        }

        return response()->json([
            'id' => (int) $item->id,
            'category_name' => (string) ($item->category_name ?? ''),
            'item_code' => $item->item_code,
            'warehouse' => (string) ($item->warehouse ?? ''),
            'color' => $item->color,
            'category_price' => $item->category_price,
            'unit_price' => $item->unit_price,
            'product_type' => $item->product_type,
        ], 200);
    }

    public function index()
    {
        $manufactures = Manufacture::with('product')->get();
        return response()->json($manufactures, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:categories,id',
            'total' => 'required|numeric',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|integer|exists:categories,id',
            'products.*.quantity' => 'required|numeric|min:0.000001',
            'products.*.total_price' => 'required|numeric',
            'extra_costs' => ['nullable', 'array'],
            'extra_costs.*.name' => ['required', 'string', 'max:255'],
            'extra_costs.*.type' => ['required', 'in:fixed,percentage'],
            'extra_costs.*.value' => ['required', 'numeric', 'min:0'],
        ]);

        $productId = (int) $request->product_id;
        $outputItem = Item::query()->findOrFail($productId);
        $anchorId = ManufacturingConsumptionResolver::outputAnchorId($outputItem);
        if (Manufacture::where('product_id', $anchorId)->exists()) {
            return response()->json([
                'message' => 'يوجد بالفعل وصفة لهذا المنتج. لا يمكن تسجيل وصفة مكرّرة لنفس الصنف.',
            ], 422);
        }

        $ingredientIds = collect($request->products)->map(fn ($row) => (int) ($row['id'] ?? 0));
        if ($ingredientIds->count() !== $ingredientIds->unique()->count()) {
            return response()->json([
                'message' => 'لا يمكن تكرار نفس المادة أكثر من مرة في الوصفة.',
            ], 422);
        }

        $extraCosts = $request->input('extra_costs', []);
        if (! is_array($extraCosts)) {
            $extraCosts = [];
        }

        try {
            return DB::transaction(function () use ($request, $anchorId, $extraCosts) {
                $manfuture = Manufacture::create([
                    'product_id' => $anchorId,
                    'total' => $request->total,
                ]);
                foreach ($request->products as $product) {
                    ManufactureProduct::create([
                        'manufacture_id' => $manfuture->id,
                        'product_id' => $product['id'],
                        'quantity' => $product['quantity'],
                        'total_price' => $product['total_price'],
                    ]);
                }

                $this->manufactureRecipeSync->sync($anchorId, $request->products, $extraCosts);

                return response()->json('success', 201);
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function manfucture_by_warhouse(Request $request)
    {
        $request->validate([
            'warehouse' => 'required|string',
            'scope' => 'nullable|string|in:manufacture_only,all_categories',
        ]);
        $warehouse = (string) $request->input('warehouse');
        $scope = (string) $request->input('scope', 'manufacture_only');

        /**
         * manufacture_only: أصناف لها صف Manufacture ومخرجاتها في هذا المخزن (سلوك قديم).
         * all_categories: كل الأصناف في المخزن — مطلوب لدمج WIP→تام نحو صنف تام أُنشئ من ترقية/تجزئة دون Manufacture يشير إليه.
         */
        if ($scope === 'all_categories') {
            $p = [];
            $rows = Category::query()
                ->where('warehouse', $warehouse)
                ->orderBy('category_name')
                ->get(['id', 'category_name', 'quantity', 'category_price', 'unit_price']);
            foreach ($rows as $row) {
                $p[] = (object) [
                    'id' => $row->id,
                    'category_name' => $row->category_name,
                    'cost' => (float) ($row->unit_price ?: $row->category_price ?: 0),
                    'quantity' => $row->quantity,
                ];
            }

            return response()->json($p, 200);
        }

        $filteredManufactures = Manufacture::with('product')
            ->get()
            ->filter(function ($manufacture) use ($warehouse) {
                return $manufacture->product
                    && trim((string) $manufacture->product->warehouse) === trim($warehouse);
            });

        /** @var list<int> $anchorIds */
        $anchorIds = $filteredManufactures
            ->map(fn ($manufacture) => (int) $manufacture->product_id)
            ->unique()
            ->values()
            ->all();

        /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Item>> $variantsByAnchor */
        $variantsByAnchor = collect();
        if ($anchorIds !== []) {
            $variantsByAnchor = Item::query()
                ->whereIn('parent_item_id', $anchorIds)
                ->where('warehouse', $warehouse)
                ->orderBy('id')
                ->get()
                ->groupBy('parent_item_id');
        }

        $p = [];
        $seenIds = [];

        foreach ($filteredManufactures as $manufacture) {
            $base = $manufacture->product;
            if (! $base) {
                continue;
            }

            $rows = collect([$base])->concat(
                $variantsByAnchor->get((int) $base->id, collect())->all()
            );

            foreach ($rows as $row) {
                $id = (int) $row->id;
                if (isset($seenIds[$id])) {
                    continue;
                }
                $seenIds[$id] = true;

                $data = (object) [
                    'id' => $id,
                    'category_name' => $row->category_name,
                    'cost' => (float) $manufacture->total,
                    'quantity' => $row->quantity,
                ];
                array_push($p, $data);
            }
        }

        return response()->json($p, 200);
    }

    public function previewConsumption(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:categories,id',
            'quantity' => 'required|numeric|gt:0',
            'status' => 'nullable|string',
            'wip_keep_under_processing' => 'nullable|boolean',
            'consumption_lines' => 'nullable|array',
            'consumption_lines.*.bom_item_id' => 'required_with:consumption_lines|integer',
            'consumption_lines.*.resolved_category_id' => 'required_with:consumption_lines|integer|exists:categories,id',
            'consumption_lines.*.quantity' => 'required_with:consumption_lines|numeric|min:0',
        ]);

        $productItem = Item::query()->findOrFail((int) $request->product_id);
        $status = (string) $request->input('status', 'تم الانتهاء');
        $stayWipOnly = $request->boolean('wip_keep_under_processing');

        /** @var ManufacturingConsumptionPlannerService $planner */
        $planner = app(ManufacturingConsumptionPlannerService::class);

        if (! $planner->shouldConsumeRawMaterials($productItem, $status, $stayWipOnly)) {
            return response()->json([
                'applies' => false,
                'lines' => [],
                'total_cost' => 0,
                'all_sufficient' => true,
                'message' => 'لا يُستهلك مخزون خام في هذه الحالة (يُخصم عند الإتمام أو لا ينطبق على هذا المسار).',
            ]);
        }

        try {
            $preview = $planner->preview(
                (int) $request->product_id,
                (float) $request->quantity,
                $request->input('consumption_lines')
            );

            return response()->json($preview);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * تحديث الوصفة (Recipe + Manufacture) من مواد الاستهلاك الحالية في شاشة التأكيد.
     */
    public function updateRecipeFromConsumption(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:categories,id',
            'quantity' => 'required|numeric|gt:0',
            'consumption_lines' => 'required|array|min:1',
            'consumption_lines.*.bom_item_id' => 'required|integer',
            'consumption_lines.*.resolved_category_id' => 'required|integer|exists:categories,id',
            'consumption_lines.*.quantity' => 'required|numeric|min:0',
        ]);

        /** @var ManufacturingConsumptionPlannerService $planner */
        $planner = app(ManufacturingConsumptionPlannerService::class);
        /** @var ManufacturingRecipeFromConsumptionService $recipeUpdater */
        $recipeUpdater = app(ManufacturingRecipeFromConsumptionService::class);

        try {
            $lines = $planner->resolveForExecution(
                (int) $request->product_id,
                (float) $request->quantity,
                $request->input('consumption_lines')
            );

            DB::transaction(function () use ($recipeUpdater, $request, $lines) {
                $recipeUpdater->updateFromConsumptionLines(
                    (int) $request->product_id,
                    (float) $request->quantity,
                    $lines
                );
            });

            return response()->json([
                'message' => 'تم تحديث الوصفة بنجاح.',
            ]);
        } catch (ProductAlreadyCompletedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'quantity' => 'required',
            'status' => 'required',
            'total' => 'required',
            'date' => 'required',
            'product_id' => 'required|integer|exists:categories,id',
            'wip_mode' => 'nullable|string|in:auto,full_same,partial_new,partial_merge',
            'wip_target_product_id' => 'nullable|integer|exists:categories,id',
            'wip_keep_under_processing' => 'nullable|boolean',
            'consumption_lines' => 'nullable|array',
            'consumption_lines.*.bom_item_id' => 'required_with:consumption_lines|integer',
            'consumption_lines.*.resolved_category_id' => 'required_with:consumption_lines|integer|exists:categories,id',
            'consumption_lines.*.quantity' => 'required_with:consumption_lines|numeric|min:0',
            'update_recipe' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        $wipResult = null;
        try {
            $productItem = Item::query()->findOrFail((int) $request->product_id);
            $isWipSemiFinished = $productItem->resolvedProductType() === ProductType::SemiFinished;
            $stayWipOnly = $request->boolean('wip_keep_under_processing');

            $needsManufactureDefinition = ! $isWipSemiFinished
                || ($isWipSemiFinished && $stayWipOnly && $request->status === 'تم الانتهاء');

            $manufactureAnchorProductId = ManufacturingConsumptionResolver::outputAnchorId($productItem);

            if ($needsManufactureDefinition) {
                $manfuctureExists = Manufacture::where('product_id', $manufactureAnchorProductId)->exists();
                if (! $manfuctureExists) {
                    DB::rollBack();

                    return response()->json(['message' => 'لا توجد وصفة تصنيع (Manufacture) لهذا الصنف في النظام.'], 422);
                }
            }

            $consumptionLines = null;
            $runRawConsumption = ! $isWipSemiFinished
                || ($isWipSemiFinished && $stayWipOnly && $request->status === 'تم الانتهاء');

            if ($runRawConsumption && $request->filled('consumption_lines')) {
                /** @var ManufacturingConsumptionPlannerService $planner */
                $planner = app(ManufacturingConsumptionPlannerService::class);
                $consumptionLines = $planner->resolveForExecution(
                    (int) $request->product_id,
                    (float) $request->quantity,
                    $request->input('consumption_lines')
                );

                if ($request->boolean('update_recipe')) {
                    /** @var ManufacturingRecipeFromConsumptionService $recipeUpdater */
                    $recipeUpdater = app(ManufacturingRecipeFromConsumptionService::class);
                    $recipeUpdater->updateFromConsumptionLines(
                        (int) $request->product_id,
                        (float) $request->quantity,
                        $consumptionLines
                    );
                }
            }

            $confirmed = ConfirmedManfucture::create([
                'quantity' => $request->quantity,
                'status' => $request->status,
                'total' => $request->total,
                'date' => $request->date,
                'user_id' => auth()->user()->id,
                'product_id' => $request->product_id
            ]);

            $totalRawMaterialCost = 0;

            if ($runRawConsumption) {
                /** @var ManufacturingConsumptionPlannerService $planner */
                $planner = app(ManufacturingConsumptionPlannerService::class);
                if ($consumptionLines === null) {
                    $consumptionLines = $planner->resolveForExecution(
                        (int) $request->product_id,
                        (float) $confirmed->quantity,
                        $request->input('consumption_lines')
                    );
                }

                $totalRawMaterialCost = $planner->applyConsumption(
                    $confirmed,
                    $consumptionLines,
                    auth()->user()->name ?? null
                );

                $existingMeta = is_array($confirmed->completion_meta) ? $confirmed->completion_meta : [];
                $existingMeta['consumption_lines'] = $consumptionLines;
                $existingMeta['total_raw_material_cost'] = $totalRawMaterialCost;
                $confirmed->completion_meta = $existingMeta;
                $confirmed->save();

                if ($totalRawMaterialCost > 0.00001) {
                    $this->postManufacturingConsumptionGl(
                        $totalRawMaterialCost,
                        'استهلاك مواد خام — أمر تصنيع #' . $confirmed->id,
                        $confirmed->id
                    );
                }
            }

            if ($confirmed->status == 'تم الانتهاء') {
                if ($isWipSemiFinished && $stayWipOnly) {
                    $this->postProductionCompletionStayWip($confirmed, $request);
                } elseif ($isWipSemiFinished && ! $stayWipOnly) {
                    $wipMode = strtolower(trim((string) $request->input('wip_mode', 'auto')));
                    $mergeTarget = $request->filled('wip_target_product_id')
                        ? (int) $request->input('wip_target_product_id')
                        : null;
                    $wipResult = app(WipToFinishedPromotionService::class)->transferForManufactureConfirm(
                        (int) $request->product_id,
                        (float) $request->quantity,
                        $wipMode,
                        $mergeTarget,
                        (int) $confirmed->id,
                        auth()->id()
                    );
                } else {
                    $this->postProductionCompletionInventory($confirmed, $request);
                }
            }

            DB::commit();

            $payload = $confirmed->toArray();
            if ($isWipSemiFinished && $stayWipOnly && $confirmed->status === 'تم الانتهاء') {
                $payload['wip_stayed_under_processing'] = true;
            }
            if ($wipResult !== null) {
                $existingMeta = is_array($confirmed->completion_meta) ? $confirmed->completion_meta : [];
                $confirmed->completion_meta = array_merge($existingMeta, [
                    'strategy' => $wipResult['strategy'],
                    'result_category_id' => $wipResult['result_category']->id,
                    'new_category_id' => $wipResult['new_category']?->id,
                    'daily_entry_id' => $wipResult['daily_entry_id'] ?? null,
                ]);
                $confirmed->save();

                $payload['wip_completion'] = [
                    'strategy' => $wipResult['strategy'],
                    'result_category_id' => $wipResult['result_category']->id,
                    'new_category_id' => $wipResult['new_category']?->id,
                    'daily_entry_id' => $wipResult['daily_entry_id'] ?? null,
                ];
            }

            return response()->json($payload, 201);
        } catch (ProductAlreadyCompletedException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function confirmed()
    {
        $confirmed = ConfirmedManfucture::query()
            ->whereNull('deleted_at')
            ->with([
                'user' => function ($query) {
                    $query->select('id', 'name');
                },
                'product' => function ($query) {
                    $query->select('id', 'category_name');
                },
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json($confirmed, 200);
    }

    public function confirmedDeleted()
    {
        if (! has_permission('manufacturing.delete_order') && ! has_permission('system.rbac')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $deleted = ConfirmedManfucture::query()
            ->whereNotNull('deleted_at')
            ->with([
                'user' => function ($query) {
                    $query->select('id', 'name');
                },
                'deletedByUser' => function ($query) {
                    $query->select('id', 'name');
                },
                'product' => function ($query) {
                    $query->select('id', 'category_name');
                },
            ])
            ->orderByDesc('deleted_at')
            ->get();

        return response()->json($deleted, 200);
    }

    public function destroy(int $id)
    {
        if (! has_permission('manufacturing.delete_order') && ! has_permission('system.rbac')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            $result = app(ManufacturingOrderReversalService::class)->deleteAndReverse(
                $id,
                (int) auth()->id()
            );

            return response()->json($result, 200);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('ManufactureController::destroy failed', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function done($id)
    {
        DB::beginTransaction();
        try {
            $confirmed = ConfirmedManfucture::find($id);
            if (! $confirmed) {
                return response()->json(['message' => 'أمر التصنيع غير موجود'], 404);
            }

            $confirmed->status = 'تم الانتهاء';
            $confirmed->save();

            $productItem = Item::query()->findOrFail((int) $confirmed->product_id);

            if ($productItem->resolvedProductType() === ProductType::SemiFinished) {
                $wipResult = app(WipToFinishedPromotionService::class)->transferForManufactureConfirm(
                    (int) $confirmed->product_id,
                    (float) $confirmed->quantity,
                    'auto',
                    null,
                    (int) $confirmed->id,
                    auth()->id()
                );
                $confirmed->completion_meta = [
                    'strategy' => $wipResult['strategy'],
                    'result_category_id' => $wipResult['result_category']->id,
                    'new_category_id' => $wipResult['new_category']?->id,
                    'daily_entry_id' => $wipResult['daily_entry_id'] ?? null,
                ];
                $confirmed->save();
            } else {

            $category = Category::find($confirmed->product_id);

            DB::table('categories_balance')->insert([
                'invoice_number' => $confirmed->id,
                'category_id' => $category->id,
                'type' => 'تصنيع',
                'quantity' => $confirmed['quantity'],
                'balance_before' => $category->quantity,
                'balance_after' => $category->quantity + $confirmed->quantity,
                'price' => $confirmed['total'] / $confirmed['quantity'],
                'total_price' => $confirmed['total'],
                'unit_cost' => $confirmed['total'] / $confirmed['quantity'],
                'cost_total' => $confirmed['total'],
                'by' => auth()->user()->name,
                'created_at' => now()
            ]);

            $category->quantity = $category->quantity + $confirmed->quantity;
            $category->total_price = $category->total_price + $confirmed->total;
            $category->unit_price = $confirmed->total / $confirmed->quantity;
            $category->sell_total_price = $category->sell_total_price + ($category->category_price * $confirmed->quantity);
            $category->save();
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $category->id);

            $uc = $confirmed->quantity > 0 ? ($confirmed->total / $confirmed->quantity) : 0.0;
            app(InventoryMovementLedgerService::class)->appendInboundMovement(
                $category->fresh(),
                InventoryMovementType::ProductionToFinished,
                (float) $confirmed->quantity,
                $uc,
                (float) $confirmed->total,
                'manufacture_done',
                $confirmed->id,
                'إتمام تصنيع — إضافة منتج تام',
                null,
                auth()->user()->name ?? null
            );

            // GL: Dr Finished Goods Inventory / Cr WIP
            $completionCost = (float) $confirmed->total;
            if ($completionCost > 0.00001) {
                $this->postProductionCompletionGl(
                    $completionCost,
                    'إتمام تصنيع — أمر #' . $confirmed->id,
                    $confirmed->id
                );
            }

            }

            DB::commit();
            return response()->json('success', 200);
        } catch (\RuntimeException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GL: مدين مخزون تحت التشغيل / دائن مخزون مواد خام (حركة تكلفة إلى WIP دون المساس بتكلفة المبيعات).
     */
    private function postManufacturingConsumptionGl(float $amount, string $description, int $refId): void
    {
        $wipAcc = TreeAccount::resolveInventoryAccountForStock($this->stockByStandardName('مخزن منتج تحت التشغيل'));
        $rawAcc = TreeAccount::resolveInventoryAccountForStock($this->stockByStandardName('مخزن مواد خام'));

        if (! $wipAcc || ! $rawAcc) {
            Log::warning('ManufactureController: GL not posted — missing WIP or raw inventory account (check stocks.asset_id)', [
                'ref_id' => $refId,
            ]);

            return;
        }

        $dailyEntry = DailyEntry::create([
            'date' => now(),
            'entry_number' => DailyEntry::getNextEntryNumber(),
            'description' => $description,
            'user_id' => auth()->id(),
        ]);

        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $wipAcc->id,
            'debit' => $amount,
            'credit' => 0,
            'notes' => 'تكلفة مواد في التصنيع (WIP)',
        ]);
        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $rawAcc->id,
            'debit' => 0,
            'credit' => $amount,
            'notes' => 'نقص مخزون مواد خام',
        ]);

        AccountEntry::create([
            'tree_account_id' => $wipAcc->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => $description,
            'daily_entry_id' => $dailyEntry->id,
        ]);
        AccountEntry::create([
            'tree_account_id' => $rawAcc->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => $description,
            'daily_entry_id' => $dailyEntry->id,
        ]);

        $accService = app(AccountingService::class);
        $accService->updateAccountHierarchyBalances($wipAcc->id);
        $accService->updateAccountHierarchyBalances($rawAcc->id);
    }

    /**
     * GL: مدين مخزون منتج تام / دائن مخزون تحت التشغيل (إغلاق أمر تصنيع إلى بضاعة أخيرة).
     */
    private function postProductionCompletionGl(float $amount, string $description, int $refId): void
    {
        $fgAcc = TreeAccount::resolveInventoryAccountForStock($this->stockByStandardName('مخزن منتج تام'));
        $wipAcc = TreeAccount::resolveInventoryAccountForStock($this->stockByStandardName('مخزن منتج تحت التشغيل'));

        if (! $fgAcc || ! $wipAcc) {
            Log::warning('ManufactureController: GL not posted for completion — missing FG or WIP account (check stocks.asset_id)', [
                'ref_id' => $refId,
            ]);

            return;
        }

        $dailyEntry = DailyEntry::create([
            'date' => now(),
            'entry_number' => DailyEntry::getNextEntryNumber(),
            'description' => $description,
            'user_id' => auth()->id(),
        ]);

        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $fgAcc->id,
            'debit' => $amount,
            'credit' => 0,
            'notes' => 'إضافة منتج تام للمخزون',
        ]);
        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $wipAcc->id,
            'debit' => 0,
            'credit' => $amount,
            'notes' => 'إخراج من تحت التشغيل',
        ]);

        AccountEntry::create([
            'tree_account_id' => $fgAcc->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => $description,
            'daily_entry_id' => $dailyEntry->id,
        ]);
        AccountEntry::create([
            'tree_account_id' => $wipAcc->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => $description,
            'daily_entry_id' => $dailyEntry->id,
        ]);

        $accService = app(AccountingService::class);
        $accService->updateAccountHierarchyBalances($fgAcc->id);
        $accService->updateAccountHierarchyBalances($wipAcc->id);
    }

    private function stockByStandardName(string $warehouseName): ?Stock
    {
        return Stock::query()->where('name', $warehouseName)->first();
    }

    private function postProductionCompletionInventory($confirmed, $request): void
    {
        $category = Category::find($request->product_id);
        DB::table('categories_balance')->insert([
            'invoice_number' => $confirmed->id,
            'category_id' => $category->id,
            'type' => 'تصنيع',
            'quantity' => $confirmed['quantity'],
            'balance_before' => $category->quantity,
            'balance_after' => $category->quantity + $confirmed->quantity,
            'price' => $confirmed['total'] / $confirmed['quantity'],
            'total_price' => $confirmed['total'],
            'unit_cost' => $confirmed['total'] / $confirmed['quantity'],
            'cost_total' => $confirmed['total'],
            'by' => auth()->user()->name,
            'created_at' => now()
        ]);

        $category->quantity = $category->quantity + $confirmed->quantity;
        $category->total_price = $category->total_price + $confirmed->total;
        $category->unit_price = $confirmed->total / $confirmed->quantity;
        $category->sell_total_price = $category->sell_total_price + ($category->category_price * $request->quantity);
        $category->save();
        CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $category->id);

        $uc = $confirmed->quantity > 0 ? ($confirmed->total / $confirmed->quantity) : 0.0;
        app(InventoryMovementLedgerService::class)->appendInboundMovement(
            $category->fresh(),
            InventoryMovementType::ProductionToFinished,
            (float) $confirmed->quantity,
            $uc,
            (float) $confirmed->total,
            'manufacture_confirm_complete',
            (int) $confirmed->id,
            'إتمام تصنيع ضمن التأكيد',
            null,
            auth()->user()->name ?? null
        );

        $completionCost = (float) $confirmed->total;
        if ($completionCost > 0.00001) {
            $this->postProductionCompletionGl(
                $completionCost,
                'إتمام تصنيع — أمر #' . $confirmed->id,
                $confirmed->id
            );
        }
    }

    /**
     * زيادة رصيد صنف تحت التشغيل بعد استهلاك الخام — بدون قيد تحويل إلى منتج تام.
     */
    private function postProductionCompletionStayWip($confirmed, Request $request): void
    {
        $category = Category::find($request->product_id);
        DB::table('categories_balance')->insert([
            'invoice_number' => $confirmed->id,
            'category_id' => $category->id,
            'type' => 'تصنيع',
            'quantity' => $confirmed['quantity'],
            'balance_before' => $category->quantity,
            'balance_after' => $category->quantity + $confirmed->quantity,
            'price' => $confirmed['total'] / $confirmed['quantity'],
            'total_price' => $confirmed['total'],
            'unit_cost' => $confirmed['total'] / $confirmed['quantity'],
            'cost_total' => $confirmed['total'],
            'by' => auth()->user()->name,
            'created_at' => now()
        ]);

        $category->quantity = $category->quantity + $confirmed->quantity;
        $category->total_price = $category->total_price + $confirmed->total;
        $category->unit_price = $confirmed->total / $confirmed->quantity;
        $category->sell_total_price = $category->sell_total_price + ($category->category_price * $request->quantity);
        $category->save();
        CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $category->id);

        $uc = $confirmed->quantity > 0 ? ($confirmed->total / $confirmed->quantity) : 0.0;
        app(InventoryMovementLedgerService::class)->appendInboundMovement(
            $category->fresh(),
            InventoryMovementType::ProductionToWip,
            (float) $confirmed->quantity,
            $uc,
            (float) $confirmed->total,
            'manufacture_confirm_wip_only',
            (int) $confirmed->id,
            'إتمام تصنيع — إيقاف في تحت التشغيل',
            null,
            auth()->user()->name ?? null
        );
    }
}
