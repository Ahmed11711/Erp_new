<?php

namespace App\Http\Controllers;

use App\Models\ProcessingInvoice;
use App\Models\Purchase;
use App\Models\SupplierPay;
use App\Models\Bank;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\Supplier;
use App\Models\SupplierType;
use App\Models\TreeAccount;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\AccountEntry;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Processing\ProcessingInvoiceService;
use App\Services\Suppliers\SupplierDeletionService;
use App\Services\Suppliers\SupplierPurgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Validator;
use Illuminate\Support\Facades\Cache;

class SupplierController extends Controller
{
    //


    public function index(){
        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 5;
        $suppliers = Supplier::with('supplierType')->paginate($itemsPerPage);
        return response()->json($suppliers, 200);
    }
    public function supplier_names(){
        $data = Supplier::query()
            ->select('id', 'supplier_name')
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '!=', '')
            ->orderBy('supplier_name')
            ->get();

        return response()->json($data, 200);
    }

    /**
     * معالجو التشغيل الخارجي (مطابع / جهات تجهيز) — للاستخدام من وحدة التشغيل الخارجي.
     */
    public function processingVendors()
    {
        $rows = Supplier::query()
            ->select('id', 'supplier_name', 'supplier_phone', 'supplier_address', 'balance', 'supplier_type')
            ->with('supplierType:id,supplier_type')
            ->orderBy('supplier_name')
            ->get();

        return response()->json($rows, 200);
    }

    public function store(Request $request){
        Validator::make($request->all(),[
            'supplier_name' => 'required|string|max:255',
            // 'supplier_phone' => 'required|string|unique:suppliers,supplier_phone|regex:/^01[0125][0-9]{8}$/',
            'supplier_address' => 'nullable|string|max:255',
            'supplier_type' => 'nullable|numeric|exists:supplier_types,id',
            'supplier_rate' => 'nullable|numeric|min:0|max:10',
            'price_rate' => 'nullable|numeric|min:0|max:10',
        ])->validate();

        $supplier = Supplier::create([
            'supplier_name' => request('supplier_name'),
            'supplier_phone' => request('supplier_phone'),
            'supplier_address' => request('supplier_address'),
            'supplier_type' => request('supplier_type'),
            'supplier_rate' => request('supplier_rate'),
            'price_rate' => request('price_rate'),
            'balance' => 0,
            'last_balance' => 0,
        ]);

        // ربط تلقائي بحساب شجرة الحسابات من إعدادات ربط الحسابات
        app(AccountLinkingService::class)->ensureSupplierAccount($supplier);

        return response()->json(["success"=>true], 201);
    }

    public function show($id)
    {
        $supplier = Supplier::with('supplierType')->find($id);
        if (! $supplier) {
            return response()->json(['message' => 'المورد غير موجود'], 404);
        }

        return response()->json($supplier, 200);
    }

    public function update(Request $request, $id, AccountLinkingService $accountLinkingService)
    {
        $supplier = Supplier::find($id);
        if (! $supplier) {
            return response()->json(['message' => 'المورد غير موجود'], 404);
        }

        Validator::make($request->all(), [
            'supplier_name' => 'required|string|max:255',
            'supplier_address' => 'nullable|string|max:255',
            'supplier_phone' => 'nullable|string|max:255',
            'supplier_type' => 'nullable|numeric|exists:supplier_types,id',
            'supplier_rate' => 'nullable|numeric|min:0|max:10',
            'price_rate' => 'nullable|numeric|min:0|max:10',
        ])->validate();

        $supplier->update([
            'supplier_name' => $request->input('supplier_name'),
            'supplier_phone' => $request->input('supplier_phone'),
            'supplier_address' => $request->input('supplier_address'),
            'supplier_type' => $request->input('supplier_type'),
            'supplier_rate' => $request->input('supplier_rate'),
            'price_rate' => $request->input('price_rate'),
        ]);

        $accountLinkingService->ensureSupplierAccount($supplier);
        $accountLinkingService->syncSupplierTreeAccountName($supplier);

        return response()->json([
            'success' => true,
            'supplier' => $supplier->fresh('supplierType'),
        ], 200);
    }



    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 5;
        $search = Supplier::query();
        if($request->has('supplier_name')){
            $search->where('supplier_name', 'like', '%'.$request->supplier_name.'%');
        }
        if($request->has('supplier_type')){
            $search->where('supplier_type', $request->supplier_type);
        }
        if($request->has('supplier_phone')){
            $search->where('supplier_phone', 'like', '%'.$request->supplier_phone.'%');
        }
        if ($request->has('status')) {
            if ($request->status == 'own') {
                $search->where('balance', '<', 0);
            } elseif($request->status == 'want'){
                $search->where('balance', '>', 0);
            }
        }
        $search->with('supplierType');
        $suppliers = $search->paginate($itemsPerPage);

        $sumOfBalance = $search->sum('balance');
        $response = [
            'suppliers' => $suppliers,
            'sum_of_balance' => $sumOfBalance,
        ];

        return response()->json($response, 200);
    }

    /**
     * صفحة «حسابات الموردين» — نفس شكل pagination لـ transactions/by-customer-order/search.
     * رصيد موجب = مستحق للمورد؛ رصيد سالب = زيادة سداد / دائن.
     */
    public function supplierAccountsAggregated(Request $request)
    {
        $itemsPerPage = (int) $request->get('itemsPerPage', 15);

        $query = Supplier::query()
            ->select(['id', 'supplier_name', 'supplier_phone', 'balance'])
            ->withCount('purchases');

        if ($request->filled('supplier_name')) {
            $query->where('supplier_name', 'like', '%'.$request->supplier_name.'%');
        }
        if ($request->filled('supplier_phone')) {
            $query->where('supplier_phone', 'like', '%'.$request->supplier_phone.'%');
        }
        if ($request->has('status') && $request->status !== '' && $request->status !== null) {
            if ($request->status === 'own') {
                $query->where('balance', '<', 0);
            } elseif ($request->status === 'want') {
                $query->where('balance', '>', 0);
            }
        }

        $paginator = $query->orderBy('supplier_name')->paginate($itemsPerPage);

        $paginator->getCollection()->transform(function (Supplier $s) {
            $bal = (float) $s->balance;

            return [
                'supplier_id' => $s->id,
                'supplier_name' => $s->supplier_name,
                'supplier_phone' => $s->supplier_phone ?? '',
                'order_count' => $s->purchases_count,
                'debit' => $bal > 0 ? $bal : 0,
                'credit' => $bal < 0 ? abs($bal) : 0,
            ];
        });

        return response()->json($paginator);
    }

    public function supplier_details(Request $request, $id)
{
    $itemsPerPage = (int) (request('itemsPerPage') ?: 15);
    $supplierId = (int) $id;

    $supplier = Supplier::find($supplierId);
    $name = $supplier?->supplier_name;

    // أحدث سجل في supplier_balance لكل فاتورة شراء (تفادي تكرار الصفوف عند وجود أكثر من سجل)
    $latestPurchaseSb = DB::table('supplier_balance')
        ->select('invoice_id', DB::raw('MAX(id) as latest_sb_id'))
        ->whereNotNull('invoice_id')
        ->groupBy('invoice_id');

    $latestPaySb = DB::table('supplier_balance')
        ->select('supplierpay_id', DB::raw('MAX(id) as latest_sb_id'))
        ->whereNotNull('supplierpay_id')
        ->groupBy('supplierpay_id');

    $purchases = DB::table('purchases as p')
        ->select(
            'p.id as invoice_id',
            'sb.balance_before as balance_before',
            'sb.balance_after as balance_after',
            'u.name as user_name',
            'p.invoice_number as invoice_number',
            'p.receipt_date as receipt_date',
            'p.total_price as total_price',
            'p.paid_amount as paid_amount',
            'p.due_amount as due_amount',
            'p.invoice_type',
            'p.created_at as created_at'
        )
        ->leftJoinSub($latestPurchaseSb, 'sbm', function ($join) {
            $join->on('p.id', '=', 'sbm.invoice_id');
        })
        ->leftJoin('supplier_balance as sb', 'sb.id', '=', 'sbm.latest_sb_id')
        ->leftJoin('users as u', 'sb.user_id', '=', 'u.id')
        ->where('p.supplier_id', $supplierId);

    $pays = DB::table('supplier_pays as sp')
        ->select(
            'sp.id as invoice_id',
            'sb.balance_before as balance_before',
            'sb.balance_after as balance_after',
            'u.name as user_name',
            'sp.pay_number as invoice_number',
            'sp.receipt_date as receipt_date',
            'sp.amount as total_price',
            'sp.amount as paid_amount',
            'sp.amount as due_amount',
            DB::raw("'سداد' as invoice_type"),
            'sp.created_at as created_at'
        )
        ->leftJoinSub($latestPaySb, 'sbsp', function ($join) {
            $join->on('sp.id', '=', 'sbsp.supplierpay_id');
        })
        ->leftJoin('supplier_balance as sb', 'sb.id', '=', 'sbsp.latest_sb_id')
        ->leftJoin('users as u', 'sb.user_id', '=', 'u.id')
        ->where('sp.supplier_id', $supplierId);

    $invoicesAndPays = $purchases->union($pays)->orderBy('created_at', 'desc')->paginate($itemsPerPage);

    $result = [
        'data' => $invoicesAndPays,
        'name' => $name,
        'supplier' => $supplier ? [
            'id' => $supplier->id,
            'supplier_name' => $supplier->supplier_name,
            'balance' => $supplier->balance,
        ] : null,
    ];

    return response()->json($result, 200);
}











    public function getAllSupplierTypes(){
        $supplierTypes = SupplierType::all();
        return response()->json($supplierTypes, 200);
    }
    public function StoreSupplierType(Request $request){
        validator::make($request->all(),[
            'supplier_type' => 'required|string',
        ])->validate();
        $supplierType = SupplierType::create([
            'supplier_type' => request('supplier_type'),
        ]);
        return response()->json($supplierType, 201);
    }

    public function deleteType($id){
        $supplierType = SupplierType::find($id);
        if(!$supplierType){
            return response()->json(['message' => 'الفئة غير موجودة'], 404);
        }
        if (Supplier::where('supplier_type', $id)->exists()) {
            return response()->json(['message' => 'لا يمكن حذف الفئة لوجود موردين مرتبطين بها'], 422);
        }
        $supplierType->delete();
        return response()->json(['message' => 'تم الحذف بنجاح'], 200);
    }

    public function destroy($id, SupplierDeletionService $deletionService)
    {
        if (! has_permission('suppliers.delete') && ! has_permission('system.rbac')) {
            return response()->json(['message' => 'ليس لديك صلاحية حذف الموردين'], 403);
        }

        $supplier = Supplier::find($id);
        if (! $supplier) {
            return response()->json(['message' => 'المورد غير موجود'], 404);
        }

        try {
            $deletionService->delete($supplier);

            return response()->json(['message' => 'تم حذف المورد بنجاح'], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'حدث خطأ: '.$e->getMessage()], 500);
        }
    }

    public function destroyMany(Request $request, SupplierDeletionService $deletionService)
    {
        if (! has_permission('suppliers.delete') && ! has_permission('system.rbac')) {
            return response()->json(['message' => 'ليس لديك صلاحية حذف الموردين'], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:suppliers,id',
        ]);

        $results = $deletionService->deleteMany($request->input('ids'));
        $hasSuccess = $results['deleted'] !== [];
        $status = $hasSuccess ? 200 : 422;

        return response()->json([
            'success' => $hasSuccess,
            'message' => $hasSuccess
                ? 'تم حذف الموردين المحددين'
                : 'تعذر حذف أي مورد من المحددين',
            'results' => $results,
        ], $status);
    }

    public function purgePreview(SupplierPurgeService $purgeService)
    {
        if (! has_permission('suppliers.purge_all') && ! has_permission('system.rbac')) {
            return response()->json(['message' => 'ليس لديك صلاحية حذف جميع الموردين'], 403);
        }

        return response()->json($purgeService->preview(), 200);
    }

    public function purgeAll(Request $request, SupplierPurgeService $purgeService)
    {
        if (! has_permission('suppliers.purge_all') && ! has_permission('system.rbac')) {
            return response()->json(['message' => 'ليس لديك صلاحية حذف جميع الموردين'], 403);
        }

        $request->validate([
            'confirm' => 'required|accepted',
            'include_processing' => 'sometimes|boolean',
        ]);

        $preview = $purgeService->preview();
        if ($preview['supplier_count'] === 0) {
            return response()->json(['message' => 'لا يوجد موردين للحذف'], 422);
        }

        try {
            $counts = $purgeService->purge($request->boolean('include_processing', true));

            return response()->json([
                'message' => 'تم حذف جميع الموردين وحساباتهم بنجاح',
                'preview' => $preview,
                'counts' => $counts,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'حدث خطأ: '.$e->getMessage()], 500);
        }
    }

    public function supplierPay($id, Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'bank' => 'nullable|numeric|exists:banks,id',
            'payment_type' => 'nullable|in:safe,bank,service_account',
            'safe_id' => 'nullable|exists:safes,id',
            'bank_id' => 'nullable|exists:banks,id',
            'service_account_id' => 'nullable|exists:service_accounts,id',
            'processing_invoice_id' => 'nullable|exists:processing_invoices,id',
            'processing_order_id' => 'nullable|exists:processing_orders,id',
        ]);

        $amount = (float) $request->amount;
        $paymentType = $request->payment_type ?? 'bank';
        $bankId = $request->bank_id ?? $request->bank;
        $safeId = $request->safe_id;
        $serviceAccountId = $request->service_account_id;
        if ($paymentType === 'bank' && !$bankId) {
            $bankId = $request->bank;
        }
        if (!$bankId && !$safeId && !$serviceAccountId) {
            return response()->json(['message' => 'يجب تحديد مصدر الدفع (خزينة أو بنك أو حساب خدمي)'], 422);
        }

        // Legacy: supplier_pays.bank_id is required; use first bank when paying from safe/service
        $payBankId = $bankId ?? Bank::query()->value('id');

        DB::beginTransaction();
        try {
            $pay = SupplierPay::create([
                'bank_id' => $payBankId,
                'safe_id' => $safeId,
                'service_account_id' => $serviceAccountId,
                'amount' => $amount,
                'supplier_id' => $id,
                'receipt_date' => date('Y-m-d'),
            ]);

            $supplier = Supplier::find($id);
            if (!$supplier) {
                DB::rollBack();
                return response()->json(['message' => 'المورد غير موجود'], 404);
            }

            if ($amount > (float) $supplier->balance + 0.000001) {
                DB::rollBack();
                return response()->json([
                    'message' => 'المبلغ أكبر من ذمة المورد. الرصيد: ' . number_format((float) $supplier->balance, 2),
                ], 422);
            }

            $supplier->last_balance = $supplier->balance;
            $supplier->balance -= $amount;

            app(AccountLinkingService::class)->ensureSupplierAccount($supplier);
            $supplier->save();

            DB::table('supplier_balance')->insert([
                'supplierpay_id' => $pay->id,
                'balance_before' => $supplier->last_balance,
                'balance_after' => $supplier->balance,
                'user_id' => auth()->user()->id,
            ]);

            $creditTreeId = null;
            $sourceName = '';

            if ($safeId) {
                $safe = Safe::find($safeId);
                if (!$safe || !$safe->account_id) {
                    DB::rollBack();
                    return response()->json(['message' => 'الخزينة غير مرتبطة بحساب في شجرة الحسابات'], 422);
                }
                $creditTreeId = $safe->account_id;
                $sourceName = $safe->name;
                $safe->decrement('balance', $amount);
            } elseif ($serviceAccountId) {
                $svc = ServiceAccount::find($serviceAccountId);
                if (!$svc || !$svc->account_id) {
                    DB::rollBack();
                    return response()->json(['message' => 'الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات'], 422);
                }
                $creditTreeId = $svc->account_id;
                $sourceName = $svc->name;
                $svc->decrement('balance', $amount);
            } else {
                $bank = Bank::find($bankId);
                $balanceBefore = (float) $bank->balance;
                $bank->decrement('balance', $amount);
                DB::table('bank_details')->insert([
                    'bank_id' => $bankId,
                    'details' => ' سداد المورد ' . $supplier->supplier_name . ' بقيمة ' . $amount . 'ج',
                    'ref' => $pay->pay_number,
                    'type' => 'سداد',
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
            if ($supplierTreeId && $creditTreeId) {
                // max + lock داخل المعاملة — ترتيب entry_number كنص كان يعطي رقماً مكرراً (Duplicate 000007)
                $entryNumberStr = DailyEntry::getNextEntryNumber();
                $dailyEntry = DailyEntry::create([
                    'date' => now(),
                    'entry_number' => $entryNumberStr,
                    'description' => 'سداد مورد - ' . $supplier->supplier_name . ' - ' . $sourceName,
                    'user_id' => auth()->id(),
                ]);
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $supplierTreeId,
                    'debit' => $amount,
                    'credit' => 0,
                    'notes' => 'نقصان (سداد للمورد)',
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
                    'description' => 'سداد مورد - ' . $supplier->supplier_name,
                    'daily_entry_id' => $dailyEntry->id,
                ]);
                AccountEntry::create([
                    'tree_account_id' => $creditTreeId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'سداد مورد - ' . $supplier->supplier_name,
                    'daily_entry_id' => $dailyEntry->id,
                ]);
                // الأرصدة تُحسب من account_entries عبر AccountingService (لا حاجة لتحديث يدوي)
                // تحديث الحساب والحسابات الأب في الشجرة
                $accService = app(\App\Services\Accounting\AccountingService::class);
                $accService->updateAccountHierarchyBalances($supplierTreeId);
                $accService->updateAccountHierarchyBalances($creditTreeId);
            }

            $this->applyProcessingInvoicePayment(
                $pay,
                $amount,
                (int) $id,
                $request->processing_invoice_id ? (int) $request->processing_invoice_id : null,
                $request->processing_order_id ? (int) $request->processing_order_id : null,
            );

            DB::commit();
            return response()->json(['message' => 'success'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function applyProcessingInvoicePayment(
        SupplierPay $pay,
        float $amount,
        int $supplierId,
        ?int $processingInvoiceId,
        ?int $processingOrderId,
    ): void {
        if (! $processingInvoiceId && ! $processingOrderId) {
            return;
        }

        if (! Schema::hasColumn('supplier_pays', 'processing_invoice_id')) {
            return;
        }

        /** @var ProcessingInvoiceService $invoiceService */
        $invoiceService = app(ProcessingInvoiceService::class);

        if ($processingInvoiceId) {
            $invoice = ProcessingInvoice::query()->lockForUpdate()->findOrFail($processingInvoiceId);
            if ((int) $invoice->supplier_id !== $supplierId) {
                throw new \InvalidArgumentException('فاتورة التشغيل لا تخص هذا المورد.');
            }
            if (! in_array($invoice->status, ['posted', 'partially_paid'], true)) {
                throw new \InvalidArgumentException('فاتورة التشغيل غير مرحّلة أو مسددة بالكامل.');
            }
            if ($amount > (float) $invoice->due_amount + 0.000001) {
                throw new \InvalidArgumentException('المبلغ أكبر من المتبقي على فاتورة التشغيل.');
            }

            $pay->update(['processing_invoice_id' => $invoice->id]);
            $invoiceService->recordPayment($invoice, $amount);

            return;
        }

        $invoices = ProcessingInvoice::query()
            ->where('processing_order_id', $processingOrderId)
            ->where('supplier_id', $supplierId)
            ->whereIn('status', ['posted', 'partially_paid'])
            ->where('due_amount', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($invoices->isEmpty()) {
            return;
        }

        $orderDue = (float) $invoices->sum('due_amount');
        if ($amount > $orderDue + 0.000001) {
            throw new \InvalidArgumentException('المبلغ أكبر من إجمالي المتبقي على فواتير أمر التشغيل.');
        }

        $remaining = $amount;
        $firstInvoiceId = null;

        foreach ($invoices as $invoice) {
            if ($remaining <= 0.000001) {
                break;
            }
            $apply = min($remaining, (float) $invoice->due_amount);
            if ($apply <= 0.000001) {
                continue;
            }
            $invoiceService->recordPayment($invoice, $apply);
            $firstInvoiceId ??= (int) $invoice->id;
            $remaining -= $apply;
        }

        if ($firstInvoiceId) {
            $pay->update(['processing_invoice_id' => $firstInvoiceId]);
        }
    }


}
