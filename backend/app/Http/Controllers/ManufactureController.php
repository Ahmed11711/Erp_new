<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ConfirmedManfucture;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CategoryInventoryCostService;

class ManufactureController extends Controller
{
    public function index()
    {
        $manufactures = Manufacture::with('product')->get();
        return response()->json($manufactures, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'total' => 'required'
        ]);
        $manfuture = Manufacture::create([
            'product_id' => $request->product_id,
            'total' => $request->total
        ]);
        foreach ($request->products as $product) {
            ManufactureProduct::create([
                'manufacture_id' => $manfuture->id,
                'product_id' => $product['id'],
                'quantity' => $product['quantity'],
                'total_price' => $product['total_price']
            ]);
        }
        return response()->json('success', 201);
    }

    public function manfucture_by_warhouse(Request $request)
    {
        $request->validate([
            'warehouse' => 'required'
        ]);
        $manufactures = Manufacture::with('product')->get();
        $p = [];
        foreach ($manufactures as $manufacture) {
            if ($manufacture->product->warehouse == $request->warehouse) {
                $data = (object) [
                    'id' => $manufacture->product->id,
                    'category_name' => $manufacture->product->category_name,
                    'cost' => $manufacture->total,
                ];
                array_push($p, $data);
            }
        }
        return response()->json($p, 200);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'quantity' => 'required',
            'status' => 'required',
            'total' => 'required',
            'date' => 'required',
            'product_id' => 'required'
        ]);

        DB::beginTransaction();
        try {
            $confirmed = ConfirmedManfucture::create([
                'quantity' => $request->quantity,
                'status' => $request->status,
                'total' => $request->total,
                'date' => $request->date,
                'user_id' => auth()->user()->id,
                'product_id' => $request->product_id
            ]);
            $manfucture = Manufacture::where('product_id', $confirmed->product_id)->first();
            $manproducts = ManufactureProduct::where('manufacture_id', $manfucture->id)->get();

            $totalRawMaterialCost = 0;

            foreach ($manproducts as $manproduct) {
                $category = Category::find($manproduct->product_id);
                $neededQuantity = $manproduct->quantity * $request->quantity;
                $warehouseRatings = DB::table('warehouse_ratings')->where('category_id', $manproduct->product_id)->get();
                $total_price = $category->total_price;

                foreach ($warehouseRatings as $product) {
                    if ($product->quantity == 0) {
                        continue;
                    }
                    $availableQuantity = $product->quantity - $neededQuantity;
                    if ($availableQuantity <= 0) {
                        $neededQuantity = $neededQuantity - $product->quantity;
                        DB::table('warehouse_ratings')->where('id', $product->id)->update(['quantity' => 0]);
                        $total_price -= $product->quantity * $product->price;
                    } else {
                        $total_price -= $neededQuantity * $product->price;
                        DB::table('warehouse_ratings')->where('id', $product->id)->increment('quantity', -$neededQuantity);
                        $category->total_price = $total_price;
                        break;
                    }
                }

                $category->total_price = $total_price;

                $consumedQty = $manproduct['quantity'] * $confirmed['quantity'];
                $unitCost = ($manproduct['quantity'] ?? 0) > 0 ? $manproduct['total_price'] / $manproduct['quantity'] : 0;
                $lineCost = $unitCost * $consumedQty;
                $totalRawMaterialCost += $lineCost;

                DB::table('categories_balance')->insert([
                    'invoice_number' => $confirmed->id,
                    'category_id' => $category->id,
                    'type' => 'تصنيع',
                    'quantity' => $consumedQty,
                    'balance_before' => $category->quantity,
                    'balance_after' => $category->quantity - $consumedQty,
                    'price' => $unitCost,
                    'total_price' => $manproduct['total_price'],
                    'unit_cost' => $unitCost,
                    'cost_total' => $manproduct['total_price'],
                    'by' => auth()->user()->name,
                    'created_at' => now()
                ]);

                $category->quantity = $category->quantity - $consumedQty;
                $category->save();
                CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $category->id);
            }

            // GL: Dr WIP (or Finished Goods) / Cr Raw Materials Inventory
            if ($totalRawMaterialCost > 0.00001) {
                $this->postManufacturingConsumptionGl(
                    $totalRawMaterialCost,
                    'استهلاك مواد خام — أمر تصنيع #' . $confirmed->id,
                    $confirmed->id
                );
            }

            if ($confirmed->status == 'تم الانتهاء') {
                $this->postProductionCompletionInventory($confirmed, $request);
            }

            DB::commit();
            return response()->json($confirmed, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function confirmed()
    {
        $confirmed = ConfirmedManfucture::with([
            'user' => function ($query) {
                $query->select('id', 'name');
            },
            'product' => function ($query) {
                $query->select('id', 'category_name');
            }
        ])->get();
        return response()->json($confirmed, 200);
    }

    public function done($id)
    {
        DB::beginTransaction();
        try {
            $confirmed = ConfirmedManfucture::find($id);
            $confirmed->status = 'تم الانتهاء';
            $confirmed->save();

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

            // GL: Dr Finished Goods Inventory / Cr WIP
            $completionCost = (float) $confirmed->total;
            if ($completionCost > 0.00001) {
                $this->postProductionCompletionGl(
                    $completionCost,
                    'إتمام تصنيع — أمر #' . $confirmed->id,
                    $confirmed->id
                );
            }

            DB::commit();
            return response()->json('success', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GL: Dr WIP (or COGS) / Cr Raw Materials Inventory
     * Assumption: raw material consumption reduces inventory and increases WIP.
     */
    private function postManufacturingConsumptionGl(float $amount, string $description, int $refId): void
    {
        $inventoryAcc = TreeAccount::resolveInventoryAccount();
        $cogsAcc = TreeAccount::resolveCogsAccount();

        if (!$inventoryAcc || !$cogsAcc) {
            Log::warning('ManufactureController: GL not posted — missing inventory or COGS account', [
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
            'account_id' => $cogsAcc->id,
            'debit' => $amount,
            'credit' => 0,
            'notes' => 'تكلفة مواد خام مستهلكة في التصنيع',
        ]);
        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $inventoryAcc->id,
            'debit' => 0,
            'credit' => $amount,
            'notes' => 'نقص مخزون مواد خام',
        ]);

        AccountEntry::create([
            'tree_account_id' => $cogsAcc->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => $description,
            'daily_entry_id' => $dailyEntry->id,
        ]);
        AccountEntry::create([
            'tree_account_id' => $inventoryAcc->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => $description,
            'daily_entry_id' => $dailyEntry->id,
        ]);

        $accService = app(AccountingService::class);
        $accService->updateAccountHierarchyBalances($cogsAcc->id);
        $accService->updateAccountHierarchyBalances($inventoryAcc->id);
    }

    /**
     * GL: Dr Finished Goods Inventory / Cr COGS (reversal of raw material cost
     * capitalized into finished goods at production cost).
     */
    private function postProductionCompletionGl(float $amount, string $description, int $refId): void
    {
        $inventoryAcc = TreeAccount::resolveInventoryAccount();
        $cogsAcc = TreeAccount::resolveCogsAccount();

        if (!$inventoryAcc || !$cogsAcc) {
            Log::warning('ManufactureController: GL not posted for completion — missing accounts', [
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
            'account_id' => $inventoryAcc->id,
            'debit' => $amount,
            'credit' => 0,
            'notes' => 'إضافة منتج تام للمخزون',
        ]);
        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $cogsAcc->id,
            'debit' => 0,
            'credit' => $amount,
            'notes' => 'رسملة تكلفة التصنيع',
        ]);

        AccountEntry::create([
            'tree_account_id' => $inventoryAcc->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => $description,
            'daily_entry_id' => $dailyEntry->id,
        ]);
        AccountEntry::create([
            'tree_account_id' => $cogsAcc->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => $description,
            'daily_entry_id' => $dailyEntry->id,
        ]);

        $accService = app(AccountingService::class);
        $accService->updateAccountHierarchyBalances($inventoryAcc->id);
        $accService->updateAccountHierarchyBalances($cogsAcc->id);
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

        $completionCost = (float) $confirmed->total;
        if ($completionCost > 0.00001) {
            $this->postProductionCompletionGl(
                $completionCost,
                'إتمام تصنيع — أمر #' . $confirmed->id,
                $confirmed->id
            );
        }
    }
}
