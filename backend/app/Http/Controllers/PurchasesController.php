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
use App\Services\Accounting\LandedCostService;
use App\Services\CategoryInventoryCostService;
use App\Models\TransactionType;
use App\Services\Documents\DocumentNumberService;
use App\Services\Stock\PurchaseStockDocumentService;
use App\Enums\PurchaseInvoiceKind;
use App\Services\Purchases\PurchaseInvoiceAccountingService;
use App\Services\Purchases\PurchaseEditAuditService;
use App\Services\Purchases\PurchaseDeletionService;
use App\Services\Purchases\PurchaseInvoiceTypeResolver;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Validator;
class PurchasesController extends Controller
{
    //

    public function index()
    {
        $purchases = Purchase::query()
            ->notDeleted()
            ->with([
            'supplier' => fn ($query) => $query->select('id', 'supplier_name'),
            'shippingCompany:id,name,type',
        ])->get();
        return response()->json($purchases, 200);
    }

    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = Purchase::query()->whereNull('ref')->notDeleted();
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
            $type = $request->invoice_type;
            $search->where(function ($q) use ($type) {
                $q->where('invoice_type', $type)
                    ->orWhereHas('updatedPurchase', fn ($uq) => $uq->where('invoice_type', $type));
            });
        }
        if($request->has('supplier_id')){
            $search->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('purchase_id')) {
            $search->where('id', (int) $request->purchase_id);
        }
        if ($request->filled('q')) {
            $raw = trim($request->q);
            if (ctype_digit($raw)) {
                $id = (int) $raw;
                $search->where(function ($q) use ($id, $raw) {
                    $q->where('id', $id)
                        ->orWhere('invoice_number', 'like', '%' . $raw . '%')
                        ->orWhere('invoice_no', 'like', '%' . $raw . '%')
                        ->orWhere('external_invoice_no', 'like', '%' . $raw . '%');
                });
            } else {
                $term = '%' . $raw . '%';
                $search->where(function ($q) use ($term) {
                    $q->where('invoice_number', 'like', $term)
                        ->orWhere('invoice_no', 'like', $term)
                        ->orWhere('external_invoice_no', 'like', $term)
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
        }
        $search->select('purchases.*');
        $search->addSelect([
            DB::raw('(SELECT ic.product_name FROM invoice_categories ic WHERE ic.purchase_id = purchases.id ORDER BY ic.id ASC LIMIT 1) as first_product_name'),
            DB::raw('(SELECT ic.product_unit FROM invoice_categories ic WHERE ic.purchase_id = purchases.id ORDER BY ic.id ASC LIMIT 1) as first_product_unit'),
            DB::raw('(SELECT COALESCE(SUM(ic.product_quantity), 0) FROM invoice_categories ic WHERE ic.purchase_id = purchases.id) as invoice_lines_qty'),
        ]);
        $search = $search->with([
            'supplier:id,supplier_name',
            'shippingCompany:id,name,type',
            'updatedPurchase.shippingCompany:id,name,type',
        ])->orderBy('id', 'desc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }

    public function show($id, Request $request)
    {

        if($request->query('foredit') == 'true'){
            $row = Purchase::query()->find($id);
            if (! $row) {
                return response()->json(['error' => 'Invoice not found'], 404);
            }

            $mainId = $row->ref ? (int) $row->ref : (int) $row->id;
            $invoice = Purchase::query()
                ->where(function ($q) use ($mainId, $id) {
                    $q->where('id', $mainId)->orWhere('id', $id);
                })
                ->whereNull('ref')
                ->with(['bank:id,name', 'safe:id,name', 'serviceAccount:id,name', 'supplier:id,supplier_name', 'shippingCompany:id,name,type'])
                ->first();

            if (! $invoice) {
                $invoice = Purchase::query()->find($mainId);
            }

            $latestPurchase = Purchase::query()
                ->where('ref', $mainId)
                ->with(['bank:id,name', 'safe:id,name', 'serviceAccount:id,name', 'supplier:id,supplier_name', 'shippingCompany:id,name,type'])
                ->latest('id')
                ->first();

            if ($latestPurchase) {
                $categories = DB::table('invoice_categories')->where('purchase_id', $latestPurchase->id)->get();
                $invoice = $latestPurchase;
            } else {
                $categories = DB::table('invoice_categories')->where('purchase_id', $invoice->id)->get();
            }

            if ($invoice && ! $invoice->supplier && $invoice->supplier_id) {
                $invoice->setRelation('supplier', Supplier::query()->select('id', 'supplier_name')->find($invoice->supplier_id));
            }

            if ($invoice && ! $invoice->shippingCompany && $invoice->shipping_company_id) {
                $invoice->setRelation(
                    'shippingCompany',
                    \App\Models\ShippingCompany::query()->select('id', 'name', 'type')->find($invoice->shipping_company_id)
                );
            }

            return response()->json([
                'invoice' => $invoice,
                'categories' => $categories,
                'tracking' => $this->loadPurchaseTracking($mainId),
                'print_url' => URL::temporarySignedRoute(
                    'documents.purchases.print',
                    now()->addHours(48),
                    ['purchase' => $invoice->ref ? (int) $invoice->ref : (int) $invoice->id]
                ),
            ], 200);
        }

        $purchase = Purchase::where('id', $id)
            ->with(['bank:id,name', 'safe:id,name', 'supplier:id,supplier_name', 'shippingCompany:id,name,type'])
            ->first();

        if (!$purchase) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        // Lines are stored on the latest revision row (ref -> main id); the main row often has no invoice_categories after an edit.
        $mainId = $purchase->ref ? (int) $purchase->ref : (int) $purchase->id;
        $latestRevision = Purchase::where('ref', $mainId)
            ->with(['bank:id,name', 'safe:id,name', 'supplier:id,supplier_name', 'shippingCompany:id,name,type'])
            ->latest('id')
            ->first();

        $invoice = $latestRevision ?: $purchase;

        if (! $invoice->shippingCompany && $invoice->shipping_company_id) {
            $invoice->setRelation(
                'shippingCompany',
                \App\Models\ShippingCompany::query()->select('id', 'name', 'type')->find($invoice->shipping_company_id)
            );
        }

        $categories = DB::table('invoice_categories')->where('purchase_id', $invoice->id)->get();

        $tracking = $this->loadPurchaseTracking($mainId);

        $data = [
            'invoice' => $invoice,
            'tracking' => $tracking,
            'categories' => $categories,
            'cost_breakdown' => LandedCostService::purchaseBreakdown($invoice),
            'print_url' => URL::temporarySignedRoute(
                'documents.purchases.print',
                now()->addHours(48),
                ['purchase' => $mainId]
            ),
        ];

        return response()->json($data, 200);
    }



    public function store(Request $request)
    {
        // return $request;

        $rules = [
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'invoice_type' => 'required',
            'receipt_date' => 'required',
            'total_price' => 'required',
            'paid_amount' => 'required',
            'due_amount' => 'required',
            'transport_cost' => 'required',
            'price_edited' => 'required',
            'products' => 'required|array|min:1',
            'products.*.product_name' => 'required|string',
            'products.*.product_unit' => 'required|string',
            'products.*.product_quantity' => 'required|numeric',
            'products.*.product_price' => 'required|numeric',
            'products.*.total' => 'required|numeric',
            'products.*.price_edited' => 'required|boolean',
            'shipping_company_id' => 'nullable|exists:shipping_companies,id',
        ];
        $paymentType = $request->payment_type ?? 'bank';
        if ($request->has('invoiceId')) {
            $mainId = (int) $request->input('invoiceId');
            $chainIds = Purchase::query()
                ->where(function ($q) use ($mainId) {
                    $q->where('id', $mainId)->orWhere('ref', $mainId);
                })
                ->pluck('id')
                ->all();
            $rules['custom_invoice_no'] = [
                'nullable',
                'string',
                'max:64',
                Rule::unique('purchases', 'invoice_no')->where(function ($query) use ($chainIds) {
                    return $query->whereNotIn('id', $chainIds);
                }),
                Rule::unique('purchases', 'invoice_number')->where(function ($query) use ($chainIds) {
                    return $query->whereNotIn('id', $chainIds);
                }),
            ];
        } else {
            $rules['custom_invoice_no'] = [
                'nullable',
                'string',
                'max:64',
                Rule::unique('purchases', 'invoice_no'),
                Rule::unique('purchases', 'invoice_number'),
            ];
        }
        if ((float) $request->paid_amount > 0) {
            if ($paymentType === 'bank') {
                $rules['bank_id'] = 'required|exists:banks,id';
            } elseif ($paymentType === 'safe') {
                $rules['safe_id'] = 'required|exists:safes,id';
            } elseif ($paymentType === 'service_account') {
                $rules['service_account_id'] = 'required|exists:service_accounts,id';
            }
        }
        $data = $request->all();
        if (array_key_exists('supplier_id', $data)) {
            $rawSupplierId = $data['supplier_id'];
            if ($rawSupplierId === '' || $rawSupplierId === 'undefined' || $rawSupplierId === 'null' || ! is_numeric($rawSupplierId)) {
                $data['supplier_id'] = null;
            } else {
                $data['supplier_id'] = (int) $rawSupplierId;
            }
        }
        if (isset($data['products']) && is_string($data['products'])) {
            $decodedProducts = json_decode($data['products'], true);
            if (is_array($decodedProducts)) {
                $data['products'] = $decodedProducts;
            }
        }
        if (isset($data['custom_invoice_no'])) {
            $trimmed = trim((string) $data['custom_invoice_no']);
            $data['custom_invoice_no'] = $trimmed === '' ? null : $trimmed;
        }
        Validator::make($data, $rules)->validate();

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
        $oldGrandTotal = 0.0;
        $oldInvice = null;
        $oldSupplier = null;
        $oldCategories = collect();
        $oldKind = null;
        $mainInvoice = null;
        $status = null;
        $typeResolver = app(PurchaseInvoiceTypeResolver::class);
        $purchaseAccounting = app(PurchaseInvoiceAccountingService::class);
        $glService = app(InventoryGlPostingService::class);

        if ($request->has('invoiceId')) {
            $mainInvoice = Purchase::find($request->input('invoiceId'));

            $oldInvice = Purchase::where('invoice_number', $mainInvoice->invoice_number)->latest('id')->first();

            $oldCategories = DB::table('invoice_categories')->where('purchase_id', $oldInvice->id)->get();
            $oldKind = $typeResolver->kind($oldInvice->invoice_type);
            $old_paid_amount = (float) $oldInvice->paid_amount;
            $old_due_amount = (float) $oldInvice->due_amount;
            $oldSupplier = Supplier::find($oldInvice->supplier_id);

            $oldLinesSum = 0.0;
            foreach ($oldCategories as $oc) {
                $oldLinesSum += abs((float) $oc->total);
            }
            $oldShip = (float) ($oldInvice->shipping_total ?? $oldInvice->transport_cost);
            $oldProduct = (float) ($oldInvice->product_total ?? $oldLinesSum);
            $oldGrandTotal = (float) ($oldInvice->grand_total ?? ($oldProduct + $oldShip));

            $oldInvMap = CategoryInventoryCostService::aggregatePurchaseLineTotalsByInventoryTreeAccount($oldCategories);
            if ($oldProduct > 0.00001 && count($oldInvMap) === 0 && $oldKind !== PurchaseInvoiceKind::Amanat) {
                $fb = TreeAccount::resolveInventoryAccount();
                if ($fb) {
                    $oldInvMap[$fb->id] = $oldProduct;
                }
            }

            if ($oldSupplier) {
                $purchaseAccounting->reverseGlForPurchase(
                    $oldInvice,
                    $oldSupplier,
                    $oldInvMap,
                    $oldShip,
                    $oldProduct,
                    $oldKind,
                    auth()->id(),
                    'تعديل'
                );
            }

            if ($old_paid_amount > 0.00001 && $oldSupplier) {
                if ($oldKind === PurchaseInvoiceKind::PurchaseReturn) {
                    $glService->postPurchasePaymentGl($oldInvice, $oldSupplier, $old_paid_amount, auth()->id());
                } elseif ($typeResolver->affectsSupplierBalance($oldKind)) {
                    $glService->reversePurchasePaymentGl($oldInvice, $oldSupplier, $old_paid_amount, auth()->id());
                }
            }

            if ($old_paid_amount > 0.00001) {
                if ($oldKind === PurchaseInvoiceKind::PurchaseReturn) {
                    $this->deductPurchasePaymentSource($oldInvice, $old_paid_amount);
                } else {
                    $this->refundPurchasePayment($oldInvice, $old_paid_amount);
                }
            }

            $status = '0';
        }
        $invoiceKind = $typeResolver->kind((string) request('invoice_type'));
        $productsPreview = $this->decodeProductsPayload($request);
        $linesSumPreview = 0;
        foreach ($productsPreview as $product) {
            $linesSumPreview += (float) $product['total'];
        }
        $transportPreview = (float) request('transport_cost');
        $purchaseData = [
            'supplier_id' => (int) request('supplier_id'),
            'shipping_company_id' => request('shipping_company_id') ?: null,
            'invoice_type' => request('invoice_type'),
            'receipt_date' => request('receipt_date'),
            'total_price' => request('total_price'),
            'paid_amount' => request('paid_amount'),
            'due_amount' => request('due_amount'),
            'transport_cost' => request('transport_cost'),
            'product_total' => $linesSumPreview,
            'shipping_total' => $transportPreview,
            'grand_total' => $linesSumPreview + $transportPreview,
            'price_edited' => request('price_edited'),
            'invoice_image' => $img_name,
            'payment_type' => $paymentType,
            'status' => $status,
            'notes' => request('notes'),
            'external_invoice_no' => request('external_invoice_no'),
            'printable_status' => 'draft',
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
        if ($request->has('invoiceId')) {
            $mainInvoice->status = '0';
            $mainInvoice->edits = $mainInvoice->edits + 1;
            $mainInvoice->receipt_date = $purchase->receipt_date;
            $mainInvoice->invoice_type = $purchase->invoice_type;
            $mainInvoice->supplier_id = $purchase->supplier_id;
            $mainInvoice->shipping_company_id = $purchase->shipping_company_id;
            $mainInvoice->save();
            $purchase->ref = $mainInvoice->id;
            $customEdit = trim((string) $request->input('custom_invoice_no', ''));
            if ($customEdit !== '') {
                $purchase->invoice_no = $customEdit;
                $purchase->invoice_number = $customEdit;
            } else {
                $purchase->invoice_number = $mainInvoice->invoice_number;
                $purchase->invoice_no = $mainInvoice->invoice_no ?: $mainInvoice->invoice_number;
            }

            // مراجعة جديدة بنفس رقم المستند: الصف السابق ما زال يحمل invoice_no فتفشل DB (فهرس فريد).
            // يبقى الرقم على آخر صف فقط؛ المراجعة الحالية هي المرجع للطباعة والسندات.
            $mainId = (int) $mainInvoice->id;
            Purchase::query()
                ->where(function ($q) use ($mainId) {
                    $q->where('id', $mainId)->orWhere('ref', $mainId);
                })
                ->where('id', '!=', (int) $purchase->id)
                ->update(['invoice_no' => null]);
        } else {
            $custom = trim((string) $request->input('custom_invoice_no', ''));
            if ($custom !== '') {
                $purchase->invoice_no = $custom;
                $purchase->invoice_number = $custom;
            } else {
                $purchaseType = TransactionType::query()->where('code', $typeResolver->stockTransactionCode($invoiceKind))->firstOrFail();
                $purchase->invoice_no = app(DocumentNumberService::class)->generate((int) $purchaseType->id);
                $purchase->invoice_number = $purchase->invoice_no;
            }
        }
        $purchase->external_invoice_no = $request->input('external_invoice_no');
        $purchase->notes = $request->input('notes');
        $purchase->printable_status = $purchase->printable_status ?: 'draft';
        $purchase->save();
        $products = $this->decodeProductsPayload($request);
        if ($request->has('invoiceId')) {
            $lineResult = $purchaseAccounting->reconcileProductLinesOnEdit(
                $purchase,
                $oldCategories,
                $oldKind,
                $products,
                $invoiceKind
            );
        } else {
            $lineResult = $purchaseAccounting->applyProductLines($purchase, $products, $invoiceKind);
        }
        $linesSum = $lineResult['lines_sum'];
        $inventoryGlByAccount = $lineResult['inventory_gl'];

        if (abs($linesSum) > 0.00001 && count($inventoryGlByAccount) === 0 && $invoiceKind !== PurchaseInvoiceKind::Amanat) {
            $fallbackInv = TreeAccount::resolveInventoryAccount();
            if ($fallbackInv) {
                $inventoryGlByAccount[$fallbackInv->id] = abs($linesSum);
            }
        }

        $supplier = Supplier::find($purchase->supplier_id);
        $transport = (float) $request->transport_cost;
        $newReceiptAmount = abs($linesSum) + $transport;

        $purchase->product_total = abs($linesSum);
        $purchase->shipping_total = $transport;
        $purchase->grand_total = $newReceiptAmount;
        $purchase->save();

        $purchaseAccounting->adjustSupplierBalances(
            $supplier,
            $invoiceKind,
            (float) $purchase->due_amount,
            $newReceiptAmount,
            (int) $purchase->id,
            $oldSupplier,
            (float) $old_due_amount,
            $oldKind,
            (float) $oldGrandTotal,
        );

        $purchaseAccounting->postGlForPurchase(
            $purchase,
            $supplier,
            $inventoryGlByAccount,
            $transport,
            abs($linesSum),
            $invoiceKind,
            auth()->id()
        );

        if ((double) $request->paid_amount > 0) {
            $paid = abs((double) $request->paid_amount);
            if ($invoiceKind === PurchaseInvoiceKind::PurchaseReceipt) {
                $this->processPurchasePayment($purchase, $supplier, $paid, $paymentType, $request, $oldInvice ?? null);
            } elseif ($invoiceKind === PurchaseInvoiceKind::PurchaseReturn) {
                $this->processPurchaseRefundReceived($purchase, $supplier, $paid, $paymentType, $request);
            }
        }

        if (!($request->has('invoiceId'))) {
            PurchasesTracking::create([
                'invoice_id' => $purchase->id,
                'invoice_number' => $purchase->id,
                'action' => 'فاتورة جديدة',
                'user_id' => auth()->id(),
            ]);
        }

        app(PurchaseStockDocumentService::class)->syncPurchaseDocument($purchase->fresh());

        if ($request->has('invoiceId') && $mainInvoice && $oldInvice) {
            $actorName = auth()->user()->name ?? null;
            $editDetails = app(PurchaseEditAuditService::class)->buildEditDetails(
                $oldInvice,
                $purchase->fresh(),
                $oldCategories,
                $products,
                $oldKind,
                $invoiceKind,
                $lineResult['stock_warnings'] ?? [],
                $actorName,
                now()->format('Y-m-d H:i'),
            );

            PurchasesTracking::create([
                'invoice_id' => $mainInvoice->id,
                'invoice_number' => $purchase->id,
                'action' => 'تعديل فاتورة',
                'details' => $editDetails,
                'user_id' => auth()->id(),
            ]);
        }

        DB::commit();

        $response = ['success' => true];
        if (! empty($lineResult['stock_warnings'] ?? [])) {
            $response['warnings'] = $lineResult['stock_warnings'];
        }

        return response()->json($response, 200);
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

        if ($paymentType === 'safe' && $request->safe_id) {
            $safe = Safe::find($request->safe_id);
            if (!$safe || !$safe->account_id) {
                throw new \Exception('الخزينة غير مرتبطة بحساب في شجرة الحسابات');
            }
            $safe->decrement('balance', $amount);
        } elseif ($paymentType === 'service_account' && $request->service_account_id) {
            $svc = ServiceAccount::find($request->service_account_id);
            if (!$svc || !$svc->account_id) {
                throw new \Exception('الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات');
            }
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
        }

        app(InventoryGlPostingService::class)->postPurchasePaymentGl(
            $purchase->fresh(),
            $supplier,
            (float) $amount,
            auth()->id()
        );
    }

    /**
     * Refund received from supplier on purchase return: increase bank/safe and Dr cash / Cr AP.
     */
    private function processPurchaseRefundReceived($purchase, $supplier, $amount, $paymentType, $request): void
    {
        if ($amount <= 0.00001) {
            return;
        }

        if ($paymentType === 'safe' && $request->safe_id) {
            $safe = Safe::find($request->safe_id);
            if (! $safe || ! $safe->account_id) {
                throw new \Exception('الخزينة غير مرتبطة بحساب في شجرة الحسابات');
            }
            $safe->increment('balance', $amount);
        } elseif ($paymentType === 'service_account' && $request->service_account_id) {
            $svc = ServiceAccount::find($request->service_account_id);
            if (! $svc || ! $svc->account_id) {
                throw new \Exception('الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات');
            }
            $svc->increment('balance', $amount);
        } else {
            $bankId = $request->bank_id;
            $bank = Bank::find($bankId);
            if (! $bank) {
                throw new \Exception('البنك غير موجود');
            }
            $balanceBefore = (float) $bank->balance;
            $bank->increment('balance', $amount);
            DB::table('bank_details')->insert([
                'bank_id' => $bankId,
                'details' => ' استرداد من المورد '.$supplier->supplier_name,
                'ref' => $purchase->invoice_number,
                'type' => 'مرتجع مشتريات',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $bank->fresh()->balance,
                'date' => date('Y-m-d'),
                'created_at' => now(),
                'user_id' => auth()->user()->id,
            ]);
        }

        app(InventoryGlPostingService::class)->reversePurchasePaymentGl(
            $purchase,
            $supplier,
            $amount,
            auth()->id()
        );
    }

    /**
     * Deduct payment source balance (undo refund received on purchase return edit/delete).
     */
    private function deductPurchasePaymentSource($purchase, $amount): void
    {
        $pt = $purchase->payment_type ?? 'bank';
        if ($pt === 'safe' && $purchase->safe_id) {
            Safe::where('id', $purchase->safe_id)->decrement('balance', $amount);
        } elseif ($pt === 'service_account' && $purchase->service_account_id) {
            ServiceAccount::where('id', $purchase->service_account_id)->decrement('balance', $amount);
        } elseif ($purchase->bank_id) {
            $bank = Bank::find($purchase->bank_id);
            if ($bank) {
                $balanceBefore = (float) $bank->balance;
                $bank->decrement('balance', $amount);
                DB::table('bank_details')->insert([
                    'bank_id' => $purchase->bank_id,
                    'details' => ' عكس استرداد مرتجع مشتريات رقم '.$purchase->invoice_number,
                    'ref' => $purchase->invoice_number,
                    'type' => 'مرتجع مشتريات',
                    'amount' => $amount * -1,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $bank->fresh()->balance,
                    'date' => date('Y-m-d'),
                    'created_at' => now(),
                    'user_id' => auth()->user()->id,
                ]);
            }
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






    public function destroy($id, PurchaseDeletionService $deletionService)
    {
        try {
            if (auth()->user()->department != 'Admin') {
                $approval = $deletionService->requestApproval((int) $id, (int) auth()->id());

                return response()->json($approval, 201);
            }

            $deleteResult = $deletionService->delete((int) $id, (int) auth()->id());

            $response = ['success' => true];
            if (! empty($deleteResult['stock_warnings'] ?? [])) {
                $response['warnings'] = $deleteResult['stock_warnings'];
            }

            return response()->json($response, 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroyMany(Request $request, PurchaseDeletionService $deletionService)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:purchases,id',
        ]);

        $isAdmin = auth()->user()->department === 'Admin';
        $results = $deletionService->deleteMany(
            $request->input('ids'),
            (int) auth()->id(),
            $isAdmin
        );

        $hasSuccess = $results['deleted'] !== [] || $results['pending'] !== [];
        $status = $hasSuccess ? 200 : 422;

        return response()->json([
            'success' => $hasSuccess,
            'results' => $results,
            'warnings' => $results['warnings'] ?? [],
        ], $status);
    }

    /**
     * Marks purchase invoice printable lifecycle state (draft / printed / archived).
     */
    public function updatePrintable(Request $request, $id)
    {
        $request->validate([
            'printable_status' => 'required|string|in:draft,printed,archived',
        ]);

        $row = Purchase::query()->findOrFail($id);
        $mainId = $row->ref ? (int) $row->ref : (int) $row->id;
        $main = Purchase::query()->findOrFail($mainId);
        $main->printable_status = $request->printable_status;
        $main->save();

        return response()->json(['success' => true, 'invoice' => $main], 200);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeProductsPayload(Request $request): array
    {
        $products = $request->input('products');
        if (is_array($products)) {
            return $products;
        }
        if (is_string($products)) {
            $decoded = json_decode($products, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function loadPurchaseTracking(int $mainInvoiceId)
    {
        return PurchasesTracking::query()
            ->where('invoice_id', $mainInvoiceId)
            ->with(['user:id,name'])
            ->orderByDesc('created_at')
            ->get();
    }
}
