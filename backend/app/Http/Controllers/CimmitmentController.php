<?php

namespace App\Http\Controllers;

use App\Models\Cimmitment;
use App\Models\Supplier;
use App\Services\Accounting\CommitmentAccountingService;
use App\Services\Accounting\AccountLinkingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CimmitmentController extends Controller
{
    public function __construct(
        protected CommitmentAccountingService $commitmentAccountingService,
        protected AccountLinkingService $accountLinkingService
    ) {}

    public function index(Request $request)
    {
        $query = Cimmitment::with(['supplier', 'expenseAccount', 'liabilityAccount']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('payee_type')) {
            $query->where('payee_type', $request->payee_type);
        }

        $data = $query->orderBy('date', 'desc')->get();

        return response()->json($data->map(function ($c) {
            return array_merge($c->toArray(), [
                'payee_display_name' => $c->payee_display_name,
                'remaining_amount' => $c->remaining_amount,
            ]);
        }), 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'deserved_amount' => 'required|numeric|min:0',
            'payee_type' => 'required|in:supplier,other',
            'supplier_id' => 'required_if:payee_type,supplier|nullable|exists:suppliers,id',
            'payee_name' => 'required_if:payee_type,other|nullable|string|max:255',
            'expense_account_id' => 'required|exists:tree_accounts,id',
            'liability_account_id' => 'nullable|exists:tree_accounts,id',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $data = $request->only([
                'name', 'date', 'deserved_amount', 'payee_type', 'supplier_id',
                'payee_name', 'expense_account_id', 'liability_account_id', 'notes'
            ]);

            // إذا كان المورد: استخدام حسابه من شجرة الحسابات
            if ($data['payee_type'] === 'supplier' && !empty($data['supplier_id'])) {
                $supplier = Supplier::findOrFail($data['supplier_id']);
                $account = $this->accountLinkingService->ensureSupplierAccount($supplier);
                $data['liability_account_id'] = $account?->id ?? $data['liability_account_id'] ?? null;
            }

            // إذا لم يُحدد حساب الالتزام: استخدام الافتراضي من الإعدادات
            if (empty($data['liability_account_id'])) {
                $defaultLiability = $this->commitmentAccountingService->getDefaultLiabilityAccount();
                $data['liability_account_id'] = $defaultLiability?->id;
            }

            if (empty($data['liability_account_id'])) {
                return response()->json([
                    'message' => 'يجب تحديد حساب الالتزام أو إعداد الحساب الافتراضي في الإعدادات'
                ], 422);
            }

            $commitment = Cimmitment::create($data);

            // تسجيل القيد المحاسبي
            $this->commitmentAccountingService->recordCommitmentEntry($commitment);

            DB::commit();

            return response()->json([
                'message' => 'تم إضافة الالتزام وتسجيل القيد المحاسبي بنجاح',
                'data' => $commitment->load(['supplier', 'expenseAccount', 'liabilityAccount'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CimmitmentController::store failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'فشل في إضافة الالتزام: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Cimmitment $cimmitment)
    {
        $cimmitment->load(['supplier', 'expenseAccount', 'liabilityAccount', 'accountEntries']);
        return response()->json(array_merge($cimmitment->toArray(), [
            'payee_display_name' => $cimmitment->payee_display_name,
            'remaining_amount' => $cimmitment->remaining_amount,
        ]), 200);
    }

    /**
     * تسجيل دفعة سداد للالتزام
     */
    public function pay(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'cash_account_id' => 'required|exists:tree_accounts,id',
            'payment_source_type' => 'required|in:safe,bank,service_account',
            'payment_source_id' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $commitment = Cimmitment::findOrFail($id);

        if ($commitment->status === Cimmitment::STATUS_PAID) {
            return response()->json(['message' => 'هذا الالتزام مدفوع بالكامل'], 422);
        }

        try {
            $this->commitmentAccountingService->recordPaymentEntry(
                $commitment,
                (float) $request->amount,
                (int) $request->cash_account_id,
                $request->description,
                $request->payment_source_type,
                (int) $request->payment_source_id
            );
            return response()->json([
                'message' => 'تم تسجيل السداد بنجاح',
                'data' => $commitment->fresh()
            ], 200);
        } catch (\Exception $e) {
            Log::error('CimmitmentController::pay failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'فشل في تسجيل السداد: ' . $e->getMessage()
            ], 500);
        }
    }
}
