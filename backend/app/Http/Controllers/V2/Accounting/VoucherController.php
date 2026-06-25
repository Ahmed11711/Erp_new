<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\Order;
use App\Models\ShippingCompany;
use App\Models\CollectionCompany;
use App\Models\shippingCompanyDetails;
use App\Enums\CollectionProviderType;
use App\Enums\OrderCollectionStatus;
use App\Enums\OrderSettlementStatus;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\BudgetReviewService;
use App\Services\Accounting\PaymentSourceOperationalLedgerService;
use App\Services\Shipping\CollectionReceivableAccountResolver;
use App\Support\RbacLegacyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    private const SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES = ['تم شحن', 'تم التسليم'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Voucher::with(['account', 'client', 'supplier', 'shippingCompany', 'collectionCompany', 'user']);

        if ($request->has('voucher_type')) {
            $query->where('voucher_type', $request->voucher_type);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('date', [$request->date_from, $request->date_to]);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('client_or_supplier_name', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 25);
        $vouchers = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json($vouchers, 200);
    }

    /**
     * قائمة عملاء الشركات + عملاء الأفراد (من الطلبات) لشاشات النقد الوارد/الصادر.
     */
    public function clientOptions(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $term = $search !== '' ? '%'.addcslashes($search, '%_\\').'%' : null;

        $companiesQuery = \App\Models\customerCompany::query()->orderBy('name');
        if ($term) {
            $companiesQuery->where('name', 'like', $term);
        }
        $companies = $companiesQuery
            ->limit(500)
            ->get(['id', 'name'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'label' => trim((string) ($c->name ?: '')),
            ])
            ->filter(fn ($row) => $row['label'] !== '')
            ->values();

        $individualsQuery = Order::query()
            ->select(
                'customer_phone_1',
                DB::raw('MAX(customer_name) as customer_name'),
                DB::raw("MAX(COALESCE(customer_type, 'فرد')) as customer_type"),
                DB::raw('MAX(company_id) as resolved_company_id'),
            )
            ->whereNotNull('customer_phone_1')
            ->where('customer_phone_1', '!=', '')
            ->groupBy('customer_phone_1');

        if ($term) {
            $individualsQuery->where(function ($q) use ($term) {
                $q->where('customer_name', 'like', $term)
                    ->orWhere('customer_phone_1', 'like', $term);
            });
        }

        $individuals = $individualsQuery
            ->orderByDesc(DB::raw('MAX(orders.id)'))
            ->limit(500)
            ->get()
            ->filter(function ($row) {
                $type = mb_strtolower(trim((string) ($row->customer_type ?? 'فرد')));
                if (in_array($type, ['شركة', 'company', 'corporate'], true)) {
                    return false;
                }
                if ($row->resolved_company_id) {
                    return false;
                }

                return true;
            })
            ->map(function ($row) {
                $name = trim((string) ($row->customer_name ?? ''));
                $phone = trim((string) ($row->customer_phone_1 ?? ''));

                return [
                    'phone' => $phone,
                    'name' => $name,
                    'label' => $name !== '' ? "{$name} — {$phone}" : $phone,
                ];
            })
            ->filter(fn ($row) => $row['phone'] !== '')
            ->values();

        return response()->json([
            'companies' => $companies,
            'individuals' => $individuals,
        ], 200);
    }


    // ... helper method to handle legacy operational balances ...
    private function updateOperationalBalance($voucher, $reverse = false) {
        if ($voucher->voucher_type === 'shipping_company' || $voucher->voucher_type === 'collection_company') {
            return;
        }

        $user_id = auth()->id();
        $isClient = $voucher->voucher_type === 'client';
        $amount = $voucher->amount;
        
        // Determine Direction based on Voucher Type & Operation
        // Receipt ( قبض ) : Client pays us -> Client Balance decreases (-) | Supplier pays us -> Supplier Balance increases (+? Refund) usually Supplier Balance is Liability (Credit). 
        // Let's follow standard: 
        // Client (Asset): Debit increases, Credit decreases. Receipt = Credit to Client. So Balance decreases.
        // Supplier (Liability): Credit increases, Debit decreases. Payment = Debit to Supplier. So Balance decreases.
        
        $sign = 1;
        if ($voucher->type === 'receipt') {
             // Receipt: Money come IN. 
             // Logic: Client Balance reduces (Payment received). 
             $sign = -1; 
        } else {
             // Payment: Money go OUT. 
             // Logic: Supplier Balance reduces (Payment made).
             // Logic: Client Balance increases (Refund given).
             $sign = ($isClient) ? 1 : -1;
        }

        // If reversing (for delete/update), flip the sign
        if ($reverse) {
            $sign *= -1;
        }

        $finalAmount = $amount * $sign;

        if ($isClient) {
             if ($voucher->client_id && ! $this->isIndividualClientVoucher($voucher)) {
                // Use Stored Procedure for Customer
                // CALL update_customer_company_balance(company_id, amount, bank_id, ref, details, type, user_id, date)
                
                // Determine Bank ID if the account used is a bank
                // For now, we pass null as 'bank_id' usually tracks specific bank balance updates in the SP, 
                // but here we are just updating the CUSTOMER balance. 
                // However, OrdersController passes bank_id. 
                // Let's attempt to map account_id to bank_id if possible, or pass null.
                $bankId = null; 
                // $bank = \App\Models\Bank::where('asset_id', $voucher->account_id)->first(); // Logic to find bank if needed
                
                $details = "سند {$voucher->type} رقم {$voucher->id} - {$voucher->notes}";
                $type = 'سندات'; 

                // DIRECT UPDATE (Replacing Missing Stored Procedure)
                $client = \App\Models\customerCompany::find($voucher->client_id);
                if ($client) {
                    $currentBalance = $client->balance;
                    $client->balance += $finalAmount;
                    $client->save();
    
                    DB::table('customer_company_details')->insert([
                        'bank_id' => $bankId,
                        'customer_company_id' => $client->id,
                        'ref' => $voucher->id,
                        'details' => $details,
                        'type' => $type,
                        'amount' => $finalAmount,
                        'balance_before' => $currentBalance,
                        'balance_after' => $client->balance,
                        'date' => date('Y-m-d'),
                        'created_at' => now(),
                        'user_id' => $user_id
                    ]);
                }
             }
        } else {
            // Supplier Logic
            if ($voucher->supplier_id) {
                $supplier = \App\Models\Supplier::find($voucher->supplier_id);
                if ($supplier) {
                    $oldBalance = $supplier->balance;
                    $supplier->last_balance = $oldBalance;
                    $supplier->balance = $oldBalance + $finalAmount; // Add the signed amount
                    $supplier->save();

                    // Insert into supplier_balance history
                    // Only insert history if NOT reversing? Or insert reverse entry?
                    // Typically history tracks actions. 
                    // If we are DELETING, we might want to just update balance or insert a "Correction" entry.
                    // PurchaseController inserts entry on Delete/Edit.
                    
                    DB::table('supplier_balance')->insert([
                        // 'supplierpay_id' => null, // This table seems to link to supplier_pays OR purchases. It has invoice_id and supplierpay_id.
                        // We need to check if we can link it to voucher. 
                        // The migration we saw made 'invoice_id' nullable. 
                        // Does it have 'voucher_id'? No. 
                        // We might need to use the 'details' or just record the balance change.
                        // Let's check columns: balance_before, balance_after, user_id, invoice_id, supplierpay_id. 
                        // We don't have a voucher_id column. 
                        // We will leave FKs null and rely on the fact that the balance updated.
                        'balance_before' => $oldBalance,
                        'balance_after' => $supplier->balance,
                        'user_id' => $user_id,
                        'created_at' => now(),
                        // 'invoice_id' => null
                    ]);
                }
            }
        }
    }

    /**
     * مزامنة الرصيد التشغيلي لمصدر النقد (خزينة / بنك / حساب خدمي) مع السند.
     */
    private function syncPaymentSourceOperationalBalance(Voucher $voucher, bool $reverse): void
    {
        app(PaymentSourceOperationalLedgerService::class)->syncFromVoucher(
            (int) $voucher->account_id,
            $voucher->type,
            (float) $voucher->amount,
            (int) $voucher->id,
            $voucher->notes,
            $voucher->date instanceof \DateTimeInterface
                ? $voucher->date->format('Y-m-d')
                : (is_string($voucher->date) ? substr($voucher->date, 0, 10) : date('Y-m-d')),
            $reverse
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Manually Validate Client/Supplier Existence
        if ($request->voucher_type === 'client') {
            $clientKind = $this->resolveRequestedClientKind($request);
            if ($clientKind === 'individual') {
                if (! $request->filled('individual_customer_phone') || ! $request->filled('individual_customer_name')) {
                    return response()->json(['message' => 'بيانات عميل الأفراد غير مكتملة (الاسم والموبايل)'], 422);
                }
            } elseif (! $request->client_id || ! \App\Models\customerCompany::where('id', $request->client_id)->exists()) {
                return response()->json(['message' => 'العميل المختار غير صحيح'], 422);
            }
        } elseif ($request->voucher_type === 'supplier') {
             if (!$request->supplier_id || !\App\Models\Supplier::where('id', $request->supplier_id)->exists()) {
                 return response()->json(['message' => 'المورد المختار غير صحيح'], 422);
             }
        } elseif ($request->voucher_type === 'shipping_company') {
            if (!$request->shipping_company_id || ! ShippingCompany::where('id', $request->shipping_company_id)->exists()) {
                return response()->json(['message' => 'شركة الشحن أو المندوب المختار غير صحيح'], 422);
            }
            $shipCheck = ShippingCompany::find($request->shipping_company_id);
            if (!$shipCheck?->receivable_tree_account_id) {
                return response()->json([
                    'message' => 'لا يوجد حساب ذمم تحصيل مرتبط بهذه الجهة. اربط «حساب الذمم» من بيانات شركة الشحن / المندوب أولاً.',
                ], 422);
            }
        } elseif ($request->voucher_type === 'collection_company') {
            if (!$request->collection_company_id || ! CollectionCompany::where('id', $request->collection_company_id)->exists()) {
                return response()->json(['message' => 'شركة التحصيل المختارة غير صحيحة'], 422);
            }
            $ccCheck = CollectionCompany::find($request->collection_company_id);
            $accountLinkingService = app(\App\Services\Accounting\AccountLinkingService::class);
            $ccAccount = $accountLinkingService->ensureCollectionCompanyAccount($ccCheck);
            if (! $ccAccount) {
                return response()->json([
                    'message' => 'لا يوجد حساب ذمم مرتبط بشركة التحصيل. اربطها من إدارة شركات التحصيل أو شجرة الحسابات أولاً.',
                ], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'type' => 'required|in:receipt,payment',
            'voucher_type' => 'required|in:client,supplier,shipping_company,collection_company',
            'account_id' => 'required|exists:tree_accounts,id',
            'shipping_company_id' => 'nullable|required_if:voucher_type,shipping_company|exists:shipping_companies,id',
            'collection_company_id' => 'nullable|required_if:voucher_type,collection_company|exists:collection_companies,id',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'settled_order_ids' => 'nullable|array',
            'settled_order_ids.*' => 'integer|exists:orders,id',
            'client_kind' => 'nullable|in:company,individual',
            'individual_customer_phone' => 'nullable|string|max:32',
            'individual_customer_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->validateReceiptSettlementOrFail($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        try {
            app(\App\Services\Accounting\ReceivableTreeAccountGuard::class)
                ->assertValidPaymentSourceTreeAccount((int) $request->account_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        DB::beginTransaction();
        try {
            $clientKind = $request->voucher_type === 'client'
                ? $this->resolveRequestedClientKind($request)
                : null;

            $partyLabel = $request->input('client_or_supplier_name');
            if ($request->voucher_type === 'client' && $clientKind === 'individual') {
                $partyLabel = $partyLabel ?: trim($request->individual_customer_name.' — '.$request->individual_customer_phone);
            } elseif ($request->voucher_type === 'shipping_company') {
                $partyLabel = $partyLabel ?: ShippingCompany::find($request->shipping_company_id)?->name;
            } elseif ($request->voucher_type === 'collection_company') {
                $partyLabel = $partyLabel ?: CollectionCompany::find($request->collection_company_id)?->name;
            }

            $voucher = Voucher::create([
                'date' => $request->date,
                'type' => $request->type,
                'voucher_type' => $request->voucher_type,
                'account_id' => $request->account_id,
                'client_kind' => $clientKind,
                'client_id' => ($request->voucher_type === 'client' && $clientKind === 'company') ? $request->client_id : null,
                'individual_customer_phone' => ($request->voucher_type === 'client' && $clientKind === 'individual') ? trim((string) $request->individual_customer_phone) : null,
                'individual_customer_name' => ($request->voucher_type === 'client' && $clientKind === 'individual') ? trim((string) $request->individual_customer_name) : null,
                'supplier_id' => $request->voucher_type === 'supplier' ? $request->supplier_id : null,
                'shipping_company_id' => $request->voucher_type === 'shipping_company' ? $request->shipping_company_id : null,
                'collection_company_id' => $request->voucher_type === 'collection_company' ? $request->collection_company_id : null,
                'client_or_supplier_name' => $partyLabel,
                'amount' => $request->amount,
                'notes' => $request->notes,
                'reference_number' => $request->reference_number,
                'user_id' => auth()->id(),
            ]);

            // 1. Get or Create the Partner's Tree Account (Client or Supplier)
            $partnerTreeAccountId = null;
            
            $accountLinkingService = app(\App\Services\Accounting\AccountLinkingService::class);

            if ($request->voucher_type === 'client') {
                $partnerTreeAccountId = $this->resolveClientPartnerTreeAccountIdFromRequest($request, $accountLinkingService);
                if (! $partnerTreeAccountId) {
                    throw new \Exception('العميل ليس لديه حساب شجري مرتبط.');
                }
            } elseif ($request->voucher_type === 'supplier') {
                $supplier = \App\Models\Supplier::find($request->supplier_id);
                $account = $accountLinkingService->ensureSupplierAccount($supplier);
                $partnerTreeAccountId = $account?->id;
            } elseif ($request->voucher_type === 'collection_company') {
                $cc = CollectionCompany::find($request->collection_company_id);
                $partnerTreeAccountId = $this->resolveCollectionCompanyPartnerAccountId($cc);
            } else {
                $partnerTreeAccountId = ShippingCompany::find($request->shipping_company_id)?->receivable_tree_account_id;
            }

            if (!$partnerTreeAccountId) {
                DB::rollBack();

                return response()->json(['message' => 'تعذر تحديد حساب الطرف المحاسبي للسند'], 422);
            }

            // 2. Identify Debit and Credit Accounts
            $debitAccountId = null;
            $creditAccountId = null;

            if ($request->type === 'receipt') {
                // Receipt: Debit Safe/Bank, Credit Partner
                $debitAccountId = $request->account_id;
                $creditAccountId = $partnerTreeAccountId;
            } else {
                // Payment: Debit Partner, Credit Safe/Bank
                $debitAccountId = $partnerTreeAccountId;
                $creditAccountId = $request->account_id;
            }

            // Budget review
            $budgetService = app(BudgetReviewService::class);
            $budgetResult = $budgetService->checkBudget([
                ['tree_account_id' => $debitAccountId, 'debit' => $request->amount, 'credit' => 0],
                ['tree_account_id' => $creditAccountId, 'debit' => 0, 'credit' => $request->amount],
            ], $request->date);
            if (!$budgetResult['valid']) {
                DB::rollBack();
                return response()->json([
                    'message' => $budgetResult['message'],
                    'violations' => $budgetResult['violations'],
                ], 422);
            }

            // 3–4. قيد يومي + account_entries
            $this->createVoucherJournalEntries(
                $voucher,
                (int) $debitAccountId,
                (int) $creditAccountId,
                (float) $request->amount,
                $request->date
            );

            // تحديث الطرفين وجميع الحسابات الأب من مجموع القيود فقط (مصدر واحد للحقيقة)
            $accountingService = app(\App\Services\Accounting\AccountingService::class);
            $accountingService->updateAccountHierarchyBalances($debitAccountId);
            $accountingService->updateAccountHierarchyBalances($creditAccountId);

            // ------------------------------------------------------------------
            // UPDATE OPERATIONAL BALANCES (Legacy System)
            // ------------------------------------------------------------------
            $this->updateOperationalBalance($voucher, false); // Add Effect
            $this->syncPaymentSourceOperationalBalance($voucher, false);

            $this->applyShippingCompanyReceiptSettlement($voucher, $request);
            $this->applyCollectionCompanyReceiptSettlement($voucher, $request);

            DB::commit();
            return response()->json([
                'message' => 'تم إنشاء السند بنجاح',
                'data' => $voucher->load(['account', 'client', 'supplier', 'shippingCompany', 'collectionCompany', 'user'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $voucher = Voucher::with(['account', 'client', 'supplier', 'shippingCompany', 'collectionCompany', 'user'])->find($id);
        
        if (!$voucher) {
            return response()->json(['message' => 'السند غير موجود'], 404);
        }

        return response()->json($voucher, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (! $this->userCanEditFinance()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $voucher = Voucher::find($id);
        
        if (!$voucher) {
            return response()->json(['message' => 'السند غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|date',
            'type' => 'sometimes|in:receipt,payment',
            'account_id' => 'sometimes|exists:tree_accounts,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('account_id')) {
            try {
                app(\App\Services\Accounting\ReceivableTreeAccountGuard::class)
                    ->assertValidPaymentSourceTreeAccount((int) $request->account_id);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        DB::beginTransaction();
        try {
            // ------------------------------------------------------------------
            // REVERSE OLD OPERATIONAL BALANCE
            // ------------------------------------------------------------------
            $this->updateOperationalBalance($voucher, true); // Reverse Old Effect
            $this->syncPaymentSourceOperationalBalance($voucher, true);
            $oldEntries = AccountEntry::where('voucher_id', $voucher->id)->get();
            $oldAffectedIds = $oldEntries->pluck('tree_account_id')->unique()->filter()->map(fn ($id) => (int) $id)->all();
            $this->deleteVoucherJournalEntries($voucher);
            $accountingService = app(\App\Services\Accounting\AccountingService::class);
            foreach ($oldAffectedIds as $aid) {
                $accountingService->updateAccountHierarchyBalances($aid);
            }

            // Update voucher
            $voucher->update($request->only([
                'date', 'type', 'account_id', 'amount', 'notes', 'reference_number'
            ]));

            // New Accounting Logic (Copy from Store)
             // 1. Get the Partner's Tree Account (Client or Supplier)
            $partnerTreeAccountId = null;
            $accountLinkingService = app(\App\Services\Accounting\AccountLinkingService::class);
            if ($voucher->voucher_type === 'client') {
                $partnerTreeAccountId = $this->resolveClientPartnerTreeAccountId($voucher, $accountLinkingService);
                if (! $partnerTreeAccountId) {
                    throw new \Exception('العميل ليس لديه حساب شجري مرتبط.');
                }
            } elseif ($voucher->voucher_type === 'supplier') {
                $supplier = \App\Models\Supplier::find($voucher->supplier_id);
                $partnerTreeAccountId = $supplier ? $supplier->tree_account_id : null;
                 if (!$partnerTreeAccountId) throw new \Exception("المورد ليس لديه حساب شجري مرتبط.");
            } elseif ($voucher->voucher_type === 'collection_company') {
                $cc = CollectionCompany::find($voucher->collection_company_id);
                $partnerTreeAccountId = $this->resolveCollectionCompanyPartnerAccountId($cc);
                if (! $partnerTreeAccountId) {
                    throw new \Exception('شركة التحصيل ليست لها حساب ذمم مرتبط.');
                }
            } else {
                $ship = ShippingCompany::find($voucher->shipping_company_id);
                $partnerTreeAccountId = $ship?->receivable_tree_account_id;
                if (!$partnerTreeAccountId) throw new \Exception('شركة الشحن / المندوب ليست لها حساب ذمم تحصيل مرتبط.');
            }

            $debitAccountId = null;
            $creditAccountId = null;

            if ($voucher->type === 'receipt') {
                $debitAccountId = $voucher->account_id;
                $creditAccountId = $partnerTreeAccountId;
            } else {
                $debitAccountId = $partnerTreeAccountId;
                $creditAccountId = $voucher->account_id;
            }

            // Create journal entries
            $this->createVoucherJournalEntries(
                $voucher,
                (int) $debitAccountId,
                (int) $creditAccountId,
                (float) $voucher->amount,
                $voucher->date instanceof \DateTimeInterface
                    ? $voucher->date->format('Y-m-d')
                    : (is_string($voucher->date) ? substr($voucher->date, 0, 10) : date('Y-m-d'))
            );

            // تحديث الحساب والحسابات الأب في الشجرة من القيود
            foreach ([$debitAccountId, $creditAccountId] as $tid) {
                $accountingService->updateAccountHierarchyBalances((int) $tid);
            }

            // ------------------------------------------------------------------
            // APPLY NEW OPERATIONAL BALANCE
            // ------------------------------------------------------------------
            $this->updateOperationalBalance($voucher, false); // Add New Effect
            $this->syncPaymentSourceOperationalBalance($voucher->fresh(), false);

            DB::commit();
            return response()->json([
                'message' => 'تم تحديث السند بنجاح',
                'data' => $voucher->load(['account', 'client', 'supplier', 'shippingCompany', 'collectionCompany', 'user'])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    private function shippingCompanyOpenDetailsQuery(int $companyId, array $orderIds): \Illuminate\Database\Eloquent\Builder
    {
        $q = shippingCompanyDetails::query()
            ->where('shipping_company_id', $companyId)
            ->where('is_done', 0)
            ->whereIn('status', self::SHIPPING_DETAIL_OPEN_COLLECTION_STATUSES);

        if ($orderIds !== []) {
            $q->whereIn('order_id', $orderIds);
        }

        return $q;
    }

    /**
     * قبض شركة شحن: إغلاق سطور الطلبات فقط عند تطابق المبلغ مع مجموع السطور المفتوحة
     * (المحددة أو الكل). وإلا يُسجَّل قبض عام دون إغلاق مستحقات الطلبات.
     */
    private function applyShippingCompanyReceiptSettlement(Voucher $voucher, Request $request): void
    {
        if ($voucher->voucher_type !== 'shipping_company' || $voucher->type !== 'receipt') {
            return;
        }

        $companyId = (int) $voucher->shipping_company_id;
        if ($companyId <= 0) {
            return;
        }

        $orderIds = $request->input('settled_order_ids', []);
        $filteredIds = is_array($orderIds)
            ? array_values(array_unique(array_filter(array_map('intval', $orderIds))))
            : [];

        $rows = $this->shippingCompanyOpenDetailsQuery($companyId, $filteredIds)->get();
        $sum = round((float) $rows->sum(static fn ($r) => (float) $r->amount), 2);
        $vAmt = round((float) $voucher->amount, 2);

        if ($rows->isNotEmpty() && abs($sum - $vAmt) <= 0.05) {
            $this->closeShippingCompanyDetailLines($voucher, $rows);

            return;
        }

        if ($filteredIds !== []) {
            throw new \InvalidArgumentException(
                sprintf(
                    'تعذر إغلاق الطلبات المحددة: المبلغ %.2f لا يطابق مجموع السطور المفتوحة %.2f.',
                    $vAmt,
                    $sum
                )
            );
        }

        $this->recordUnlinkedShippingCompanyReceipt($voucher);
    }

    /**
     * قبض شركة تحصيل: تسوية ذمم الطلبات (collection_provider) أولاً، ثم سجل شركة الشحن المرتبطة legacy.
     * القيد المحاسبي يُسجَّل على حساب ذمم شركة التحصيل في الشجرة (نفس حساب الطلبات).
     */
    private function applyCollectionCompanyReceiptSettlement(Voucher $voucher, Request $request): void
    {
        if ($voucher->voucher_type !== 'collection_company' || $voucher->type !== 'receipt') {
            return;
        }

        $collectionCompanyId = (int) $voucher->collection_company_id;
        if ($collectionCompanyId <= 0) {
            return;
        }

        $cc = CollectionCompany::find($collectionCompanyId);
        if (! $cc) {
            return;
        }

        $orderIds = $request->input('settled_order_ids', []);
        $filteredIds = is_array($orderIds)
            ? array_values(array_unique(array_filter(array_map('intval', $orderIds))))
            : [];

        $vAmt = round((float) $voucher->amount, 2);

        $orderRows = $this->collectionCompanyPendingOrdersQuery($collectionCompanyId, $filteredIds)
            ->get([
                'orders.id as order_id',
                DB::raw('COALESCE(order_details.collection_receivable_amount, 0) as coll_amt'),
            ]);
        $orderSum = round((float) $orderRows->sum(static fn ($r) => (float) $r->coll_amt), 2);

        if ($orderRows->isNotEmpty() && $vAmt > 0.009 && $vAmt <= $orderSum + 0.05) {
            $this->applyCollectionCompanyOrderSettlement($voucher, $orderRows, $vAmt);

            if ($cc->linked_shipping_company_id) {
                $this->closeLinkedShippingLinesForFullySettledOrders($voucher, (int) $cc->linked_shipping_company_id);
            }

            return;
        }

        if ($filteredIds === [] && $vAmt > 0.009) {
            $allPendingRows = $this->collectionCompanyPendingOrdersQuery($collectionCompanyId, [])
                ->get([
                    'orders.id as order_id',
                    DB::raw('COALESCE(order_details.collection_receivable_amount, 0) as coll_amt'),
                ]);
            $allPendingSum = round((float) $allPendingRows->sum(static fn ($r) => (float) $r->coll_amt), 2);

            if ($allPendingRows->isNotEmpty() && $vAmt <= $allPendingSum + 0.05) {
                $this->applyCollectionCompanyOrderSettlement($voucher, $allPendingRows, $vAmt);

                if ($cc->linked_shipping_company_id) {
                    $this->closeLinkedShippingLinesForFullySettledOrders($voucher, (int) $cc->linked_shipping_company_id);
                }

                return;
            }
        }

        if ($cc->linked_shipping_company_id) {
            $linkedId = (int) $cc->linked_shipping_company_id;
            $rows = $this->shippingCompanyOpenDetailsQuery($linkedId, $filteredIds)->get();
            $sum = round((float) $rows->sum(static fn ($r) => (float) $r->amount), 2);

            if ($rows->isNotEmpty() && abs($sum - $vAmt) <= 0.05) {
                $this->closeShippingCompanyDetailLines($voucher, $rows);

                return;
            }

            if ($filteredIds !== []) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'تعذر إغلاق الطلبات المحددة: المبلغ %.2f لا يطابق مجموع السطور المفتوحة %.2f.',
                        $vAmt,
                        $sum
                    )
                );
            }

            $this->recordUnlinkedShippingCompanyReceipt($voucher, $linkedId);

            return;
        }

        if ($filteredIds !== []) {
            throw new \InvalidArgumentException(
                sprintf(
                    'تعذر تسوية الطلبات المحددة: المبلغ %.2f يتجاوز مجموع ذمم التحصيل %.2f.',
                    $vAmt,
                    $orderSum
                )
            );
        }
    }

    private function collectionCompanyPendingOrdersQuery(int $collectionCompanyId, array $orderIds): \Illuminate\Database\Query\Builder
    {
        $cc = CollectionCompany::find($collectionCompanyId);
        $linkedId = $cc?->linked_shipping_company_id;
        $settledStatus = OrderSettlementStatus::Settled->value;

        $query = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where(function ($q) use ($collectionCompanyId, $linkedId) {
                $q->where(function ($q2) use ($collectionCompanyId) {
                    $q2->where('order_details.collection_provider_type', CollectionProviderType::CollectionCompany->value)
                        ->where('order_details.collection_provider_id', $collectionCompanyId);
                });
                if ($linkedId) {
                    $q->orWhere('order_details.collection_company_id', $linkedId);
                }
            })
            ->whereRaw('COALESCE(order_details.collection_receivable_amount, 0) > 0.009')
            ->whereNotIn('orders.order_status', ['ملغي', 'تم التحصيل'])
            ->where(function ($q) use ($settledStatus) {
                $q->whereNull('order_details.settlement_status')
                    ->orWhere('order_details.settlement_status', '!=', $settledStatus);
            });

        if ($orderIds !== []) {
            $query->whereIn('orders.id', $orderIds);
        }

        return $query;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $orderRows
     */
    private function applyCollectionCompanyOrderSettlement(Voucher $voucher, $orderRows, float $voucherAmount): void
    {
        $remaining = round($voucherAmount, 2);
        if ($remaining <= 0.009 || $orderRows->isEmpty()) {
            return;
        }

        $collectDate = $voucher->date instanceof \DateTimeInterface
            ? $voucher->date->format('Y-m-d')
            : (is_string($voucher->date) ? substr($voucher->date, 0, 10) : date('Y-m-d'));

        foreach ($orderRows->sortBy('order_id') as $row) {
            if ($remaining <= 0.009) {
                break;
            }

            $order = Order::with('order_details')->find((int) $row->order_id);
            if (! $order || ! $order->order_details) {
                continue;
            }

            $od = $order->order_details;
            $openAmt = round((float) ($od->collection_receivable_amount ?? $row->coll_amt ?? 0), 2);
            if ($openAmt <= 0.009) {
                continue;
            }

            $apply = round(min($remaining, $openAmt), 2);
            $newReceivable = round(max(0, $openAmt - $apply), 2);

            $od->collection_receivable_amount = $newReceivable;
            $od->settlement_status = $newReceivable <= 0.009
                ? OrderSettlementStatus::Settled->value
                : OrderSettlementStatus::Partial->value;
            $od->collection_status = $newReceivable <= 0.009
                ? OrderCollectionStatus::Collected->value
                : OrderCollectionStatus::Partial->value;
            $od->collection_date = $collectDate;
            $od->status_date = $collectDate;
            $od->settlement_voucher_id = $voucher->id;
            $od->save();

            DB::table('voucher_order_settlement_lines')->insert([
                'voucher_id' => $voucher->id,
                'order_id' => $order->id,
                'amount_applied' => $apply,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($newReceivable <= 0.009 && $order->order_status !== 'تم التحصيل') {
                $order->order_status = 'تم التحصيل';
                $order->save();
            }

            $remaining = round($remaining - $apply, 2);
        }
    }

    private function closeLinkedShippingLinesForFullySettledOrders(Voucher $voucher, int $linkedShippingCompanyId): void
    {
        $fullySettledOrderIds = DB::table('voucher_order_settlement_lines as vosl')
            ->join('order_details as od', 'od.order_id', '=', 'vosl.order_id')
            ->where('vosl.voucher_id', $voucher->id)
            ->whereRaw('COALESCE(od.collection_receivable_amount, 0) <= 0.009')
            ->pluck('vosl.order_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($fullySettledOrderIds === []) {
            return;
        }

        $linkedRows = $this->shippingCompanyOpenDetailsQuery($linkedShippingCompanyId, $fullySettledOrderIds)->get();
        if ($linkedRows->isNotEmpty()) {
            $this->closeShippingCompanyDetailLines($voucher, $linkedRows);
        }
    }

    private function reverseCollectionCompanyReceiptSettlement(Voucher $voucher): void
    {
        if ($voucher->voucher_type !== 'collection_company' || $voucher->type !== 'receipt') {
            return;
        }

        $cc = CollectionCompany::find((int) $voucher->collection_company_id);
        if ($cc?->linked_shipping_company_id) {
            $this->reverseShippingCompanyReceiptSettlement($voucher, (int) $cc->linked_shipping_company_id);
        }

        $this->reverseCollectionCompanyOrderSettlement($voucher);
    }

    private function reverseCollectionCompanyOrderSettlement(Voucher $voucher): void
    {
        $lines = DB::table('voucher_order_settlement_lines')
            ->where('voucher_id', $voucher->id)
            ->get();

        if ($lines->isNotEmpty()) {
            foreach ($lines as $line) {
                $order = Order::with('order_details')->find((int) $line->order_id);
                if (! $order || ! $order->order_details) {
                    continue;
                }

                $od = $order->order_details;
                $restored = round((float) ($od->collection_receivable_amount ?? 0) + (float) $line->amount_applied, 2);
                $od->collection_receivable_amount = $restored;
                $od->settlement_status = OrderSettlementStatus::Open->value;
                $od->collection_status = OrderCollectionStatus::Pending->value;

                if ((int) ($od->settlement_voucher_id ?? 0) === (int) $voucher->id) {
                    $od->settlement_voucher_id = null;
                }

                $od->save();

                if ($order->order_status === 'تم التحصيل' && $restored > 0.009) {
                    $order->order_status = 'تم التسليم';
                    $order->save();
                }
            }

            DB::table('voucher_order_settlement_lines')->where('voucher_id', $voucher->id)->delete();

            return;
        }

        $orders = Order::with('order_details')
            ->whereHas('order_details', fn ($q) => $q->where('settlement_voucher_id', $voucher->id))
            ->get();

        foreach ($orders as $order) {
            $od = $order->order_details;
            if (! $od) {
                continue;
            }

            $od->settlement_status = OrderSettlementStatus::Open->value;
            $od->collection_status = OrderCollectionStatus::Pending->value;
            $od->settlement_voucher_id = null;
            $od->save();

            if ($order->order_status === 'تم التحصيل') {
                $order->order_status = 'تم التسليم';
                $order->save();
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, shippingCompanyDetails>  $rows
     */
    private function closeShippingCompanyDetailLines(Voucher $voucher, $rows): void
    {
        $userName = auth()->user()?->name ?? 'system';

        foreach ($rows as $elm) {
            $order = Order::with('order_details')->find($elm->order_id);
            if (! $order || ! $order->order_details) {
                continue;
            }

            $elm->is_done = 1;
            $elm->status = 'تم التحصيل';
            $elm->collect_date = $voucher->date;
            $elm->voucher_id = $voucher->id;
            $elm->save();

            $amount = (float) -$elm->amount;
            DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                (float) $elm->shipping_company_id,
                (int) $elm->order_id,
                $order->order_details->shipping_date,
                'تم التحصيل',
                $amount,
                $userName,
                now(),
            ]);
        }

        foreach ($rows->pluck('order_id')->unique() as $oid) {
            $this->finalizeOrderIfAllShippingLinesSettled((int) $oid);
        }
    }

    /** قبض عام على ذمة الشركة دون إغلاق سطور طلبات مفتوحة (GL يُسجَّل منفصلاً). */
    private function recordUnlinkedShippingCompanyReceipt(Voucher $voucher, ?int $overrideCompanyId = null): void
    {
        $companyId = $overrideCompanyId ?? (int) $voucher->shipping_company_id;
        $amount = round((float) $voucher->amount, 2);
        if ($companyId <= 0 || $amount <= 0) {
            return;
        }

        $userName = auth()->user()?->name ?? 'system';
        $company = ShippingCompany::query()->lockForUpdate()->find($companyId);
        if (! $company) {
            return;
        }

        $company->balance = round((float) $company->balance - $amount, 3);
        $company->save();

        shippingCompanyDetails::create([
            'order_id' => null,
            'voucher_id' => $voucher->id,
            'shipping_date' => $voucher->date,
            'collect_date' => $voucher->date,
            'status' => 'سند قبض',
            'amount' => -$amount,
            'shipping_company_id' => $companyId,
            'ref' => 'V'.$voucher->id,
            'by' => $userName,
            'is_done' => 1,
        ]);
    }

    private function reverseShippingCompanyReceiptSettlement(Voucher $voucher, ?int $overrideCompanyId = null): void
    {
        if ($voucher->type !== 'receipt') {
            return;
        }

        $isShippingVoucher = $voucher->voucher_type === 'shipping_company';
        $isCollectionLinkedVoucher = $voucher->voucher_type === 'collection_company' && $overrideCompanyId !== null;

        if (! $isShippingVoucher && ! $isCollectionLinkedVoucher) {
            return;
        }

        $details = shippingCompanyDetails::query()
            ->where('voucher_id', $voucher->id)
            ->where('status', 'سند قبض')
            ->get();

        if ($details->isEmpty()) {
            return;
        }

        $companyId = $overrideCompanyId ?? (int) $voucher->shipping_company_id;
        $company = ShippingCompany::query()->lockForUpdate()->find($companyId);
        if (! $company) {
            return;
        }

        $total = round((float) $details->sum(static fn ($d) => abs((float) $d->amount)), 2);
        $company->balance = round((float) $company->balance + $total, 3);
        $company->save();

        shippingCompanyDetails::query()->whereIn('id', $details->pluck('id'))->delete();
    }

    /**
     * نفس حساب ذمم الطلبات المستخدم في التسجيل المحاسبي للطلبات.
     */
    private function resolveCollectionCompanyPartnerAccountId(?CollectionCompany $cc): ?int
    {
        if (! $cc) {
            return null;
        }

        app(AccountLinkingService::class)->ensureCollectionCompanyAccount($cc);
        $cc->refresh();

        return app(CollectionReceivableAccountResolver::class)->receivableAccountIdForProvider(
            CollectionProviderType::CollectionCompany->value,
            (int) $cc->id
        );
    }

    private function finalizeOrderIfAllShippingLinesSettled(int $orderId): void
    {
        Order::reconcileCollectionStatusIfAllShippingLinesClosed($orderId);
    }

    private function voucherJournalDescription(Voucher $voucher): string
    {
        $typeLabel = $voucher->type === 'receipt' ? 'قبض' : 'صرف';

        return "سند {$typeLabel} - {$voucher->client_or_supplier_name}"
            . ($voucher->notes ? " - {$voucher->notes}" : '');
    }

    private function createVoucherJournalEntries(
        Voucher $voucher,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $date
    ): void {
        $description = $this->voucherJournalDescription($voucher);
        $entryNumber = DailyEntry::getNextEntryNumber();

        $dailyEntry = DailyEntry::create([
            'date' => $date,
            'entry_number' => $entryNumber,
            'description' => $description,
            'user_id' => auth()->id(),
        ]);

        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $debitAccountId,
            'debit' => $amount,
            'credit' => 0,
            'notes' => $description,
        ]);

        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => $amount,
            'notes' => $description,
        ]);

        AccountEntry::create([
            'tree_account_id' => $debitAccountId,
            'debit' => $amount,
            'credit' => 0,
            'description' => $description,
            'voucher_id' => $voucher->id,
            'daily_entry_id' => $dailyEntry->id,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        AccountEntry::create([
            'tree_account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => $amount,
            'description' => $description,
            'voucher_id' => $voucher->id,
            'daily_entry_id' => $dailyEntry->id,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }

    private function deleteVoucherJournalEntries(Voucher $voucher): void
    {
        $entries = AccountEntry::query()->where('voucher_id', $voucher->id)->get();
        $dailyEntryIds = $entries->pluck('daily_entry_id')->filter()->unique()->values();

        AccountEntry::query()->where('voucher_id', $voucher->id)->delete();

        foreach ($dailyEntryIds as $dailyEntryId) {
            DailyEntryItem::query()->where('daily_entry_id', $dailyEntryId)->delete();
            DailyEntry::query()->where('id', $dailyEntryId)->delete();
        }
    }

    /**
     * @return array<int, int>
     */
    private function parseSettledOrderIds(Request $request): array
    {
        $orderIds = $request->input('settled_order_ids', []);

        return is_array($orderIds)
            ? array_values(array_unique(array_filter(array_map('intval', $orderIds))))
            : [];
    }

    private function amountsMatch(float $expected, float $actual): bool
    {
        return abs(round($expected, 2) - round($actual, 2)) <= 0.05;
    }

    private function validateReceiptSettlementOrFail(Request $request): void
    {
        if ($request->type !== 'receipt') {
            return;
        }

        $orderIds = $this->parseSettledOrderIds($request);
        if ($orderIds === []) {
            return;
        }

        $expected = $this->expectedReceiptSettlementAmount($request, $orderIds);
        if ($expected === null) {
            throw new \InvalidArgumentException('تعذر حساب مبلغ الطلبات المحددة — تأكد أنها معلّقة وقابلة للتحصيل.');
        }

        $amount = round((float) $request->amount, 2);

        if ($request->voucher_type === 'collection_company') {
            if ($amount <= 0) {
                throw new \InvalidArgumentException('مبلغ السند يجب أن يكون أكبر من صفر.');
            }

            if ($amount > round($expected, 2) + 0.05) {
                throw new \InvalidArgumentException(sprintf(
                    'مبلغ السند (%.2f) يتجاوز مجموع ذمم الطلبات المحددة (%.2f).',
                    $amount,
                    $expected
                ));
            }

            return;
        }

        if (! $this->amountsMatch($expected, $amount)) {
            throw new \InvalidArgumentException(sprintf(
                'مبلغ السند (%.2f) لا يطابق مجموع الطلبات المحددة (%.2f).',
                $amount,
                $expected
            ));
        }
    }

    private function expectedReceiptSettlementAmount(Request $request, array $orderIds): ?float
    {
        if ($request->voucher_type === 'shipping_company') {
            $companyId = (int) $request->shipping_company_id;
            if ($companyId <= 0) {
                return null;
            }
            $rows = $this->shippingCompanyOpenDetailsQuery($companyId, $orderIds)->get();
            if ($rows->isEmpty()) {
                return null;
            }

            return round((float) $rows->sum(static fn ($r) => (float) $r->amount), 2);
        }

        if ($request->voucher_type === 'collection_company') {
            $cc = CollectionCompany::find((int) $request->collection_company_id);
            if (! $cc) {
                return null;
            }

            $orderRows = $this->collectionCompanyPendingOrdersQuery((int) $cc->id, $orderIds)->get([
                'orders.id as order_id',
                DB::raw('COALESCE(order_details.collection_receivable_amount, 0) as coll_amt'),
            ]);
            if ($orderRows->isNotEmpty()) {
                return round((float) $orderRows->sum(static fn ($r) => (float) $r->coll_amt), 2);
            }

            if ($cc->linked_shipping_company_id) {
                $rows = $this->shippingCompanyOpenDetailsQuery((int) $cc->linked_shipping_company_id, $orderIds)->get();
                if ($rows->isEmpty()) {
                    return null;
                }

                return round((float) $rows->sum(static fn ($r) => (float) $r->amount), 2);
            }

            return null;
        }

        return null;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $voucher = Voucher::find($id);
        
        if (!$voucher) {
            return response()->json(['message' => 'السند غير موجود'], 404);
        }

        DB::beginTransaction();
        try {
            // ------------------------------------------------------------------
            // REVERSE OPERATIONAL BALANCE
            // ------------------------------------------------------------------
            $this->updateOperationalBalance($voucher, true); // Reverse Effect
            $this->syncPaymentSourceOperationalBalance($voucher, true);
            $this->reverseShippingCompanyReceiptSettlement($voucher);
            $this->reverseCollectionCompanyReceiptSettlement($voucher);

            $oldEntries = AccountEntry::where('voucher_id', $voucher->id)->get();
            $affectedAccountIds = $oldEntries->pluck('tree_account_id')->unique()->values()->all();
            $this->deleteVoucherJournalEntries($voucher);

            // إعادة حساب أرصدة الحسابات المتأثرة والحسابات الأب بعد حذف القيود
            $accountingService = app(\App\Services\Accounting\AccountingService::class);
            foreach ($affectedAccountIds as $accountId) {
                $accountingService->updateAccountHierarchyBalances($accountId);
            }

            // Delete voucher
            $voucher->delete();

            DB::commit();
            return response()->json(['message' => 'تم حذف السند بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    private function userCanEditFinance(): bool
    {
        return RbacLegacyAccess::passes(auth()->user(), [
            'Admin',
            'Account Management',
            'Logistics Specialist',
            'Financial Accounts',
        ], ['finance.edit', 'system.rbac']);
    }

    private function resolveRequestedClientKind(Request $request): string
    {
        $kind = strtolower(trim((string) $request->input('client_kind', '')));
        if ($kind === 'individual') {
            return 'individual';
        }
        if ($kind === 'company') {
            return 'company';
        }

        return $request->filled('individual_customer_phone') && ! $request->filled('client_id')
            ? 'individual'
            : 'company';
    }

    private function isIndividualClientVoucher(Voucher $voucher): bool
    {
        if ($voucher->voucher_type !== 'client') {
            return false;
        }

        if ($voucher->client_kind === 'individual') {
            return true;
        }

        return ! $voucher->client_id && filled($voucher->individual_customer_phone);
    }

    private function resolveClientPartnerTreeAccountIdFromRequest(Request $request, AccountLinkingService $accountLinkingService): ?int
    {
        if ($this->resolveRequestedClientKind($request) === 'individual') {
            $account = $accountLinkingService->ensureIndividualCustomerAccount(
                trim((string) $request->individual_customer_name),
                trim((string) $request->individual_customer_phone)
            );

            return $account?->id;
        }

        $client = \App\Models\customerCompany::find($request->client_id);

        return $accountLinkingService->ensureCustomerCompanyAccount($client)?->id;
    }

    private function resolveClientPartnerTreeAccountId(Voucher $voucher, AccountLinkingService $accountLinkingService): ?int
    {
        if ($this->isIndividualClientVoucher($voucher)) {
            $account = $accountLinkingService->ensureIndividualCustomerAccount(
                trim((string) $voucher->individual_customer_name),
                trim((string) $voucher->individual_customer_phone)
            );

            return $account?->id;
        }

        $client = \App\Models\customerCompany::find($voucher->client_id);

        return $client?->tree_account_id
            ?: $accountLinkingService->ensureCustomerCompanyAccount($client)?->id;
    }
}

