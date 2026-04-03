<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\AccountEntry;
use App\Models\Purchase;
use App\Models\PurchasesTracking;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Models\Approvals;
use Illuminate\Support\Facades\DB;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\CategoryInventoryCostService;
use Validator;
class PurchasesController extends Controller
{
    //

    public function index()
    {
        $purchases = Purchase::with(['supplier' => function ($query) {
            $query->select('id', 'supplier_name');
        }])->get();
        return response()->json($purchases, 200);
    }

    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = Purchase::query()->whereNull('ref');
        if($request->has('receipt_date')){
            $search->where('receipt_date', $request->receipt_date);
        }
        if ($request->filled('date_from')) {
            $search->whereDate('receipt_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $search->whereDate('receipt_date', '<=', $request->date_to);
        }
        if($request->has('invoice_type')){
            $search->where('invoice_type', $request->invoice_type);
        }
        if($request->has('supplier_id')){
            $search->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('q')) {
            $term = '%' . trim($request->q) . '%';
            $search->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhereHas('supplier', function ($s) use ($term) {
                        $s->where('supplier_name', 'like', $term);
                    })
                    ->orWhereIn('id', function ($sub) use ($term) {
                        $sub->select('purchase_id')
                            ->from('invoice_categories')
                            ->where('product_name', 'like', $term);
                    });
            });
        }
        $search->select('purchases.*');
        $search->addSelect([
            DB::raw('(SELECT ic.product_name FROM invoice_categories ic WHERE ic.purchase_id = purchases.id ORDER BY ic.id ASC LIMIT 1) as first_product_name'),
            DB::raw('(SELECT ic.product_unit FROM invoice_categories ic WHERE ic.purchase_id = purchases.id ORDER BY ic.id ASC LIMIT 1) as first_product_unit'),
            DB::raw('(SELECT COALESCE(SUM(ic.product_quantity), 0) FROM invoice_categories ic WHERE ic.purchase_id = purchases.id) as invoice_lines_qty'),
        ]);
        $search = $search->with([
            'supplier:id,supplier_name',
            'updatedPurchase'
        ])->orderBy('id', 'desc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }

    public function show($id, Request $request)
    {

        if($request->query('foredit') == 'true'){
            $invoice = Purchase::where('id', $id)->whereNull('ref')
            ->with(['bank:id,name', 'safe:id,name', 'serviceAccount:id,name', 'supplier:id,supplier_name'])
            ->first();

            if (!$invoice) {
                return response()->json(['error' => 'Invoice not found'], 404);
            }

            $latestPurchase = Purchase::where('ref', $id)
                ->with(['bank:id,name', 'safe:id,name', 'serviceAccount:id,name', 'supplier:id,supplier_name'])
                ->latest('id')
                ->first();

            if ($latestPurchase) {
                $latestPurchase->invoice_number = 'PO' . $latestPurchase->id;
                $categories = DB::table('invoice_categories')->where('purchase_id', $latestPurchase->id)->get();
                $invoice = $latestPurchase;
            } else {
                $categories = DB::table('invoice_categories')->where('purchase_id', $id)->get();
            }

            return response()->json([
                'invoice' => $invoice,
                'categories' => $categories
            ], 200);
        }

        $purchase = Purchase::where('id', $id)
            ->with(['bank:id,name', 'safe:id,name', 'supplier:id,supplier_name'])
            ->first();

        if (!$purchase) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        // Lines are stored on the latest revision row (ref -> main id); the main row often has no invoice_categories after an edit.
        $mainId = $purchase->ref ? (int) $purchase->ref : (int) $purchase->id;
        $latestRevision = Purchase::where('ref', $mainId)
            ->with(['bank:id,name', 'safe:id,name', 'supplier:id,supplier_name'])
            ->latest('id')
            ->first();

        $invoice = $latestRevision ?: $purchase;

        $categories = DB::table('invoice_categories')->where('purchase_id', $invoice->id)->get();

        $tracking = PurchasesTracking::where('invoice_id', $mainId)->with(['user:id,name'])->get();

        $data = [
            'invoice' => $invoice,
            'tracking' => $tracking,
            'categories' => $categories,
        ];

        return response()->json($data, 200);
    }



    public function store(Request $request)
    {
        // return $request;

        $rules = [
            'supplier_id' => 'required',
            'invoice_type' => 'required',
            'receipt_date' => 'required',
            'total_price' => 'required',
            'paid_amount' => 'required',
            'due_amount' => 'required',
            'transport_cost' => 'required',
            'price_edited' => 'required',
            'products' => 'required',
            'products.*.product_name' => 'required|string',
            'products.*.product_unit' => 'required|string',
            'products.*.product_quantity' => 'required|numeric',
            'products.*.product_price' => 'required|numeric',
            'products.*.total' => 'required|numeric',
            'products.*.price_edited' => 'required|boolean',
        ];
        $paymentType = $request->payment_type ?? 'bank';
        if ((float) $request->paid_amount > 0) {
            if ($paymentType === 'bank') {
                $rules['bank_id'] = 'required|exists:banks,id';
            } elseif ($paymentType === 'safe') {
                $rules['safe_id'] = 'required|exists:safes,id';
            } elseif ($paymentType === 'service_account') {
                $rules['service_account_id'] = 'required|exists:service_accounts,id';
            }
        }
        Validator::make($request->all(), $rules)->validate();

        DB::beginTransaction();
        try {
        $img_name ='';
        if($request->hasFile('invoice_image')){
            $img = $request->file('invoice_image');
            $img_name = time() . '.' . $img->extension();
            $img->move(public_path('images'), $img_name);
        }
        $purchase['invoice_image'] = $img_name;

        $old_paid_amount = 0;
        $old_due_amount = 0;
        $ref = null;
        $status = null;
        if($request->has('invoiceId')){
            $mainInvoice = Purchase::find($request->input('invoiceId'));

            $oldInvice = Purchase::where('invoice_number' , $mainInvoice->invoice_number)->latest('id')->first();

            $oldCategories = DB::table('invoice_categories')->where('purchase_id', $oldInvice->id)->get();
            foreach($oldCategories as $product){
                $qty = (float) $product->product_quantity;
                $lineTotal = (float) $product->total;
                $effectiveUnit = CategoryInventoryCostService::purchaseLineUnitCost($lineTotal, $qty, (float) $product->product_price);

                $revCatId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($product, $product->product_name);
                if (! $revCatId) {
                    throw new \Exception('تعذر ربط الصنف عند عكس التعديل: ' . $product->product_name);
                }

                DB::table('categories')->where('id', $revCatId)->increment('quantity', $qty * -1);
                DB::table('categories')->where('id', $revCatId)->increment('total_price', $lineTotal * -1);

                CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($revCatId);

                DB::table('categories_balance')->insert([
                    'invoice_number' => $oldInvice->invoice_number,
                    'category_id' => $revCatId,
                    'type' => 'تعديل فواتير مشتريات',
                    'quantity' => $qty * -1,
                    'balance_before' => DB::table('categories')->where('id', $revCatId)->value('quantity') - ($qty * -1),
                    'balance_after' => DB::table('categories')->where('id', $revCatId)->value('quantity'),
                    'price' => $effectiveUnit * -1,
                    'total_price' => $lineTotal * -1,
                    'unit_cost' => $effectiveUnit,
                    'cost_total' => $lineTotal * -1,
                    'by' => auth()->user()->name,
                    'created_at' =>now()
                ]
                );


                DB::table('warehouse_ratings')->insert([
                    'category_id' => $revCatId,
                    'price' => $effectiveUnit * -1,
                    'quantity' => $qty * -1,
                    'ref' => $oldInvice->invoice_number,
                    'invoice_id' => $oldInvice->id,
                    'fixed_quantity' => $qty * -1,
                    'created_at' =>now()
                ]);
            }
            $old_paid_amount = $oldInvice->paid_amount;
            $old_due_amount = $oldInvice->due_amount;
            $status = '0';
        }
        $purchaseData = [
            'supplier_id' => request('supplier_id'),
            'invoice_type' => request('invoice_type'),
            'receipt_date' => request('receipt_date'),
            'total_price' => request('total_price'),
            'paid_amount' => request('paid_amount'),
            'due_amount' => request('due_amount'),
            'transport_cost' => request('transport_cost'),
            'price_edited' => request('price_edited'),
            'invoice_image' => $img_name,
            'payment_type' => $paymentType,
            'status' => $status,
        ];
        if ($paymentType === 'bank') {
            $purchaseData['bank_id'] = $request->bank_id;
        } else {
            $purchaseData['bank_id'] = null;
        }
        if ($paymentType === 'safe') {
            $purchaseData['safe_id'] = $request->safe_id;
        } else {
            $purchaseData['safe_id'] = null;
        }
        if ($paymentType === 'service_account') {
            $purchaseData['service_account_id'] = $request->service_account_id;
        } else {
            $purchaseData['service_account_id'] = null;
        }
        $purchase = Purchase::create($purchaseData);
        if($request->has('invoiceId')){
            $mainInvoice->status = '0';
            $mainInvoice->edits = $mainInvoice->edits + 1;
            $mainInvoice->save();
            PurchasesTracking::create([
                'invoice_id' => $mainInvoice->id,
                'invoice_number' => $purchase->id,
                'action' => 'تعديل فاتورة',
                'user_id' => auth()->id(),
            ]);
            $purchase->ref = $mainInvoice->id;
            $purchase->invoice_number = $mainInvoice->invoice_number;
            $purchase->save();
        }
        $products = $request->products;
        $products = json_decode($products, true);
        $linesSum = 0;
        foreach($products as $product){
            $qty = (float) $product['product_quantity'];
            $lineTotal = (float) $product['total'];
            $linesSum += $lineTotal;
            $declaredUnit = (float) $product['product_price'];
            $effectiveUnit = CategoryInventoryCostService::purchaseLineUnitCost($lineTotal, $qty, $declaredUnit);

            $newCatId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($product, $product['product_name']);
            if (! $newCatId) {
                throw new \Exception('تعذر ربط الصنف بالمخزن (مخزن مواد خام): ' . $product['product_name']);
            }

            DB::table('invoice_categories')->insert([
                'purchase_id' => $purchase->id,
                'category_id' => $newCatId,
                'product_name' => $product['product_name'],
                'product_quantity' => $product['product_quantity'],
                'product_unit' => $product['product_unit'],
                'product_price' => $product['product_price'],
                'total' => $product['total'],
                'price_edited' => $product['price_edited'],
            ]);

            DB::table('categories')->where('id', $newCatId)->increment('quantity', $qty);
            DB::table('categories')->where('id', $newCatId)->increment('total_price', $lineTotal);

            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($newCatId);
            $avgUnit = CategoryInventoryCostService::resolveReferenceUnitCost($newCatId);

            DB::table('categories_balance')->insert([
                'invoice_number' => $purchase->invoice_number,
                'category_id' => $newCatId,
                'type' => 'فواتير مشتريات',
                'quantity' => $qty,
                'balance_before' => DB::table('categories')->where('id', $newCatId)->value('quantity') - $qty,
                'balance_after' => DB::table('categories')->where('id', $newCatId)->value('quantity'),
                'price' => $effectiveUnit,
                'total_price' => $lineTotal,
                'unit_cost' => $avgUnit,
                'cost_total' => $lineTotal,
                'by' => auth()->user()->name,
                'created_at' =>now()
            ]
            );


            DB::table('warehouse_ratings')->insert([
                'category_id' => $newCatId,
                'price' => $effectiveUnit,
                'quantity' => $qty,
                'ref' => $purchase->invoice_number,
                'invoice_id' => $purchase->id,
                'fixed_quantity' => $qty,
                'created_at' =>now()
            ]);
        }
        $supplier = Supplier::find($purchase->supplier_id);
        $supplier->last_balance = $supplier->balance;
        $supplier->balance += $purchase->due_amount - $old_due_amount;
        $supplier->save();


        DB::table('supplier_balance')->insert([
            'invoice_id' => $purchase->id,
            'balance_before' => $supplier->last_balance,
            'balance_after' => $supplier->balance,
            'user_id'=> auth()->user()->id
        ]);

        /** @var InventoryGlPostingService $glService */
        $glService = app(InventoryGlPostingService::class);
        $transport = (float) $request->transport_cost;
        $newReceiptAmount = $linesSum + $transport;

        if ($request->has('invoiceId')) {
            $oldSupplier = Supplier::find($oldInvice->supplier_id);
            $oldLinesSum = 0;
            foreach ($oldCategories as $oc) {
                $oldLinesSum += (float) $oc->total;
            }
            $oldReceiptAmount = $oldLinesSum + (float) $oldInvice->transport_cost;
            if ($oldReceiptAmount > 0.00001 && $oldSupplier) {
                $glService->reversePurchaseReceipt(
                    $oldReceiptAmount,
                    $oldSupplier,
                    'عكس استلام مخزون — تعديل فاتورة ' . $oldInvice->invoice_number,
                    auth()->id()
                );
            }
            if ($old_paid_amount > 0.00001 && $oldSupplier) {
                $glService->reversePurchasePaymentGl($oldInvice, $oldSupplier, (float) $old_paid_amount, auth()->id());
            }
        }

        if ($request->has('invoiceId') && $old_paid_amount > 0) {
            $this->refundPurchasePayment($oldInvice, $old_paid_amount);
        }

        if ($newReceiptAmount > 0.00001) {
            $glService->postPurchaseReceipt(
                $newReceiptAmount,
                $supplier,
                'استلام مخزون — فاتورة مشتريات ' . $purchase->invoice_number,
                auth()->id()
            );
        }

        if ((double)$request->paid_amount > 0) {
            $this->processPurchasePayment($purchase, $supplier, (double)$request->paid_amount, $paymentType, $request, $oldInvice ?? null);
        }

        if (!($request->has('invoiceId'))) {
            PurchasesTracking::create([
                'invoice_id' => $purchase->id,
                'invoice_number' => $purchase->id,
                'action' => 'فاتورة جديدة',
                'user_id' => auth()->id(),
            ]);
        }
        DB::commit();
        return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Process payment for purchase invoice: deduct from bank/safe/service_account and create accounting entries.
     */
    private function processPurchasePayment($purchase, $supplier, $amount, $paymentType, $request, $oldInvice = null)
    {
        $details = ' سداد المورد ' . $supplier->supplier_name;
        if ($oldInvice) {
            $details .= ' من تعديل فاتور رقم ' . $oldInvice->invoice_number;
        }
        $creditTreeId = null;
        $sourceName = '';

        if ($paymentType === 'safe' && $request->safe_id) {
            $safe = Safe::find($request->safe_id);
            if (!$safe || !$safe->account_id) {
                throw new \Exception('الخزينة غير مرتبطة بحساب في شجرة الحسابات');
            }
            $creditTreeId = $safe->account_id;
            $sourceName = $safe->name;
            $safe->decrement('balance', $amount);
        } elseif ($paymentType === 'service_account' && $request->service_account_id) {
            $svc = ServiceAccount::find($request->service_account_id);
            if (!$svc || !$svc->account_id) {
                throw new \Exception('الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات');
            }
            $creditTreeId = $svc->account_id;
            $sourceName = $svc->name;
            $svc->decrement('balance', $amount);
        } else {
            $bankId = $request->bank_id;
            $bank = Bank::find($bankId);
            if (!$bank) {
                throw new \Exception('البنك غير موجود');
            }
            $balanceBefore = (float) $bank->balance;
            $bank->decrement('balance', $amount);
            DB::table('bank_details')->insert([
                'bank_id' => $bankId,
                'details' => $details,
                'ref' => $purchase->invoice_number,
                'type' => 'فواتير مشتريات',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $bank->fresh()->balance,
                'date' => date('Y-m-d'),
                'created_at' => now(),
                'user_id' => auth()->user()->id,
            ]);
            if ($bank->asset_id) {
                $creditTreeId = $bank->asset_id;
                $sourceName = $bank->name;
            }
        }

        $supplierTreeId = $supplier->tree_account_id;
        if (!$supplierTreeId) {
            $account = app(\App\Services\Accounting\AccountLinkingService::class)->ensureSupplierAccount($supplier);
            $supplierTreeId = $account?->id;
        }

        if ($supplierTreeId && $creditTreeId) {
            $maxNum = DailyEntry::lockForUpdate()->max(DB::raw('CAST(entry_number AS UNSIGNED)'));
            $entryNumber = ($maxNum ?? 0) + 1;
            $dailyEntry = DailyEntry::create([
                'date' => now(),
                'entry_number' => str_pad($entryNumber, 6, '0', STR_PAD_LEFT),
                'description' => 'فاتورة مشتريات - ' . $supplier->supplier_name . ' - ' . $sourceName,
                'user_id' => auth()->id(),
            ]);
            DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $supplierTreeId,
                'debit' => $amount,
                'credit' => 0,
                'notes' => 'زيادة (فاتورة مشتريات)',
            ]);
            DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $creditTreeId,
                'debit' => 0,
                'credit' => $amount,
                'notes' => 'نقصان (صرف من ' . $sourceName . ')',
            ]);
            AccountEntry::create([
                'tree_account_id' => $supplierTreeId,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'فاتورة مشتريات - ' . $supplier->supplier_name,
                'daily_entry_id' => $dailyEntry->id,
            ]);
            AccountEntry::create([
                'tree_account_id' => $creditTreeId,
                'debit' => 0,
                'credit' => $amount,
                'description' => 'فاتورة مشتريات - صرف من ' . $sourceName,
                'daily_entry_id' => $dailyEntry->id,
            ]);
            // تحديث الحساب والحسابات الأب في الشجرة
            $accService = app(\App\Services\Accounting\AccountingService::class);
            $accService->updateAccountHierarchyBalances($supplierTreeId);
            $accService->updateAccountHierarchyBalances($creditTreeId);
        }
    }

    /**
     * Refund payment: add amount back to bank/safe/service_account (used when editing or deleting).
     */
    private function refundPurchasePayment($purchase, $amount)
    {
        $pt = $purchase->payment_type ?? 'bank';
        if ($pt === 'safe' && $purchase->safe_id) {
            Safe::where('id', $purchase->safe_id)->increment('balance', $amount);
        } elseif ($pt === 'service_account' && $purchase->service_account_id) {
            ServiceAccount::where('id', $purchase->service_account_id)->increment('balance', $amount);
        } elseif ($purchase->bank_id) {
            $bank = Bank::find($purchase->bank_id);
            if ($bank) {
                $balanceBefore = (float) $bank->balance;
                $bank->increment('balance', $amount);
                DB::table('bank_details')->insert([
                    'bank_id' => $purchase->bank_id,
                    'details' => ' مرتجع فاتورة مشتريات رقم ' . $purchase->invoice_number,
                    'ref' => $purchase->invoice_number,
                    'type' => 'مرتجع',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $bank->fresh()->balance,
                    'date' => date('Y-m-d'),
                    'created_at' => now(),
                    'user_id' => auth()->user()->id,
                ]);
            }
        }
    }






    public function destroy($id)
    {
        $mainInvoice = Purchase::find($id);
        $purchase = Purchase::where('invoice_number' , $mainInvoice->invoice_number)->with(['bank:id,name', 'safe:id,name', 'serviceAccount:id,name', 'supplier:id,supplier_name'])->latest('id')->first();
        if (auth()->user()->department != 'Admin') {
            $isExist = Approvals::where('column_values' , $purchase)->first();
            if($isExist){
                return response()->json($isExist, 422);
            }
            $appData = [
                'type' => 'delete',
                'table_name' => 'purchases',
                'column_values' => $purchase,
                'details' => $purchase,
                'user_id' => auth()->user()->id,
            ];
            $approval = Approvals::create($appData);
            return response()->json($approval, 201);
        }
        DB::beginTransaction();
        try {
        $oldCategories = DB::table('invoice_categories')->where('purchase_id', $purchase->id)->get();
        $linesSumDelete = 0;
        foreach($oldCategories as $product){
            $qty = (float) $product->product_quantity;
            $lineTotal = (float) $product->total;
            $linesSumDelete += $lineTotal;
            $effectiveUnit = CategoryInventoryCostService::purchaseLineUnitCost($lineTotal, $qty, (float) $product->product_price);

            $delCatId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($product, $product->product_name);
            if (! $delCatId) {
                throw new \Exception('تعذر ربط الصنف عند الحذف: ' . $product->product_name);
            }

            DB::table('categories')->where('id', $delCatId)->increment('quantity', $qty * -1);
            DB::table('categories')->where('id', $delCatId)->increment('total_price', $lineTotal * -1);

            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($delCatId);

            DB::table('categories_balance')->insert([
                'invoice_number' => $purchase->invoice_number,
                'category_id' => $delCatId,
                'type' => 'حذف فواتير مشتريات',
                'quantity' => $qty * -1,
                'balance_before' => DB::table('categories')->where('id', $delCatId)->value('quantity') - ($qty * -1),
                'balance_after' => DB::table('categories')->where('id', $delCatId)->value('quantity'),
                'price' => $effectiveUnit * -1,
                'total_price' => $lineTotal * -1,
                'unit_cost' => $effectiveUnit,
                'cost_total' => $lineTotal * -1,
                'by' => auth()->user()->name,
                'created_at' =>now()
            ]
            );


            DB::table('warehouse_ratings')->insert([
                'category_id' => $delCatId,
                'price' => $effectiveUnit * -1,
                'quantity' => $qty * -1,
                'ref' => $purchase->invoice_number,
                'invoice_id' => $purchase->id,
                'fixed_quantity' => $qty * -1,
                'created_at' =>now()
            ]);
        }

        $mainPurchase = Purchase::find($id);
        $mainPurchase->status = '1';
        $mainPurchase->save();

        PurchasesTracking::create([
            'invoice_id' => $mainPurchase->id,
            'invoice_number' => $purchase->id,
            'action' => 'حذف فاتورة',
            'user_id' => auth()->id(),
        ]);

        $supplier = Supplier::find($purchase->supplier_id);
        $supplier->last_balance = $supplier->balance;
        $supplier->balance -= $purchase->due_amount;
        $supplier->save();

        DB::table('supplier_balance')->insert([
            'invoice_id' => $purchase->id,
            'balance_before' => $supplier->last_balance,
            'balance_after' => $supplier->balance,
            'user_id'=> auth()->user()->id
        ]);

        $glService = app(InventoryGlPostingService::class);
        $receiptDelete = $linesSumDelete + (float) $purchase->transport_cost;
        if ($receiptDelete > 0.00001 && $supplier) {
            $glService->reversePurchaseReceipt(
                $receiptDelete,
                $supplier,
                'عكس استلام مخزون — حذف فاتورة ' . $purchase->invoice_number,
                auth()->id()
            );
        }
        if ((double) $purchase->paid_amount > 0.00001 && $supplier) {
            $glService->reversePurchasePaymentGl($purchase, $supplier, (double) $purchase->paid_amount, auth()->id());
        }

        if ((double)$purchase->paid_amount > 0) {
            $this->refundPurchasePayment($purchase, (double)$purchase->paid_amount);
        }

        DB::commit();
        return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
