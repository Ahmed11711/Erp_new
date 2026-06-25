<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankTransaction;
use App\Models\Safe;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\DirectCashTransactionService;
use App\Services\Accounting\BankOperationalLedgerService;
use App\Services\Accounting\BankAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankController extends Controller
{
    public function __construct(protected BankAccessService $bankAccess)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = rbac_user();
        $canManageAccess = $this->bankAccess->canManageAccess($user);

        $with = ['asset'];
        if ($canManageAccess) {
            $with[] = 'assignedUsers:id,name,email,department';
        }

        $query = Bank::with($with)->where('type', 'main');
        $query = $this->bankAccess->scopeAccessibleTo($query, $user);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 25);
        $banks = $query->orderBy('name')->paginate($perPage);

        return response()->json($banks, 200);
    }

    /**
     * GET accounting/banks/{id}/users — المستخدمون المصرّح لهم باستخدام البنك.
     */
    public function assignedUsers(int $id)
    {
        $user = rbac_user();
        if (! $this->bankAccess->canManageAccess($user)) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        Bank::query()->findOrFail($id);

        return response()->json([
            'data' => $this->bankAccess->assignedUsersForBank($id),
        ], 200);
    }

    /**
     * PUT accounting/banks/{id}/users — مزامنة المستخدمين المصرّح لهم.
     * user_ids فارغ = البنك متاح للجميع.
     */
    public function syncAssignedUsers(Request $request, int $id)
    {
        $user = rbac_user();
        if (! $this->bankAccess->canManageAccess($user)) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $synced = $this->bankAccess->syncAssignedUsers($id, $data['user_ids'] ?? []);

        return response()->json([
            'message' => 'تم حفظ صلاحيات البنك',
            'data' => $synced,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     * يُنشئ حساباً فرعياً في شجرة الحسابات تحت الحساب الأب (مثل الخزن)، ويربط البنك بهذا الحساب الفرعي.
     */
    public function store(Request $request)
    {
        $balance = (float) ($request->input('balance', 0));

        $rules = [
            'name' => 'required|string',
            'type' => 'nullable|string',
            'balance' => 'nullable|numeric|min:0',
            'usage' => 'nullable|string',
            'parent_account_id' => 'nullable|exists:tree_accounts,id',
            'asset_id' => 'nullable|exists:tree_accounts,id',
            'counter_account_id' => 'nullable|exists:tree_accounts,id',
        ];

        $parentId = $request->input('parent_account_id') ?? $request->input('asset_id');
        if (!$parentId) {
            return response()->json([
                'errors' => ['parent_account_id' => ['يجب اختيار الحساب الأب في شجرة الحسابات']],
            ], 422);
        }

        if ($balance > 0.000001) {
            $rules['counter_account_id'] = 'required|exists:tree_accounts,id';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $parentAccount = TreeAccount::find($parentId);

            $lastChild = TreeAccount::where('parent_id', $parentAccount->id)
                ->orderByDesc('code')
                ->lockForUpdate()
                ->first();

            switch ($parentAccount->level) {
                case 1:
                    $newCode = $lastChild ? $lastChild->code + 1 : ($parentAccount->code * 10 + 1);
                    $newLevel = 2;
                    break;
                case 2:
                    if (!$lastChild) {
                        if ($parentAccount->code < 100) {
                            $parentCode = (string) $parentAccount->code;
                            $newCode = (int) ($parentCode[0] . '0' . $parentCode[1]);
                        } else {
                            $newCode = $parentAccount->code * 10 + 1;
                        }
                    } else {
                        $newCode = $lastChild->code + 1;
                    }
                    $newLevel = 3;
                    break;
                case 3:
                    $newCode = $lastChild ? $lastChild->code + 1 : ($parentAccount->code * 10 + 1);
                    $newLevel = 4;
                    break;
                default:
                    $newCode = $lastChild ? $lastChild->code + 1 : ($parentAccount->code * 10 + 1);
                    $newLevel = ($parentAccount->level ?? 1) + 1;
                    break;
            }

            $childAccount = TreeAccount::create([
                'name' => 'بنك - ' . $request->name,
                'name_en' => 'Bank - ' . $request->name,
                'code' => $newCode,
                'type' => $parentAccount->type,
                'level' => $newLevel,
                'parent_id' => $parentAccount->id,
                'balance' => 0,
                'debit_balance' => 0,
                'credit_balance' => 0,
                'is_trading_account' => false,
                'detail_type' => 'bank',
            ]);

            $bank = Bank::create([
                'name' => $request->name,
                'type' => $request->type ?? 'main',
                'balance' => $balance,
                'usage' => $request->usage,
                'asset_id' => $childAccount->id,
            ]);

            if ($balance > 0.000001) {
                $bankAccountId = (int) $childAccount->id;
                $counterId = (int) $request->counter_account_id;

                if ($bankAccountId === $counterId) {
                    throw new \Exception('الحساب المقابل يجب أن يكون مختلفاً عن حساب البنك');
                }
                $now = now();
                $desc = 'رصيد افتتاحي بنك - ' . $bank->name;

                AccountEntry::create([
                    'tree_account_id' => $bankAccountId,
                    'debit' => $balance,
                    'credit' => 0,
                    'description' => $desc,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                AccountEntry::create([
                    'tree_account_id' => $counterId,
                    'debit' => 0,
                    'credit' => $balance,
                    'description' => $desc,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                /** @var AccountingService $accService */
                $accService = app(AccountingService::class);
                $accService->updateAccountHierarchyBalances($bankAccountId);
                $accService->updateAccountHierarchyBalances($counterId);
            }

            DB::commit();

            return response()->json([
                'message' => 'تم إنشاء البنك بنجاح',
                'data' => $bank->load('asset'),
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
        $bank = Bank::with('asset')->find($id);

        if (!$bank) {
            return response()->json(['message' => 'البنك غير موجود'], 404);
        }

        return response()->json($bank, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $bank = Bank::find($id);

        if (!$bank) {
            return response()->json(['message' => 'البنك غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'type' => 'nullable|string',
            'usage' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldName = $bank->name;
        $bank->update($request->only([
            'name', 'type', 'usage',
        ]));

        if ($request->has('name') && $request->name !== $oldName && $bank->asset_id) {
            $treeAccount = TreeAccount::find($bank->asset_id);
            if ($treeAccount && $treeAccount->detail_type === 'bank') {
                $treeAccount->update([
                    'name' => 'بنك - ' . $request->name,
                    'name_en' => 'Bank - ' . $request->name,
                ]);
            }
        }

        return response()->json([
            'message' => 'تم تحديث بيانات البنك بنجاح',
            'data' => $bank->load('asset')
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $bank = Bank::find($id);

        if (!$bank) {
            return response()->json(['message' => 'البنك غير موجود'], 404);
        }

        if ($bank->balance != 0) {
            return response()->json(['message' => 'لا يمكن حذف البنك لأن رصيده لا يساوي صفر'], 422);
        }

        DB::beginTransaction();
        try {
            $accountId = $bank->asset_id;
            $bank->delete();

            if ($accountId) {
                $treeAccount = TreeAccount::find($accountId);
                if ($treeAccount && $treeAccount->detail_type === 'bank') {
                    $hasEntries = AccountEntry::where('tree_account_id', $accountId)->exists();
                    if (!$hasEntries) {
                        $treeAccount->delete();
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'تم حذف البنك بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Transfer between banks or bank <-> safe
     */
    public function transfer(Request $request)
    {
        // Support multiple transfer types: 'bank_to_bank', 'bank_to_safe', 'safe_to_bank'
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:transfer_bank_to_bank,transfer_bank_to_safe,transfer_safe_to_bank',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            // Conditionals fields will be validated below
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fromId = $request->from_id;
        $toId = $request->to_id;
        $amount = $request->amount;
        $type = $request->type;
        $date = $request->date;

        DB::beginTransaction();
        try {
            $fromEntity = null;
            $toEntity = null;
            $transaction = null;

            if ($type === 'transfer_bank_to_bank') {
                $fromEntity = Bank::find($fromId);
                $toEntity = Bank::find($toId);

                if (!$fromEntity || !$toEntity) return response()->json(['message' => 'البنك غير موجود'], 404);
                if ($fromEntity->id === $toEntity->id) return response()->json(['message' => 'لا يمكن التحويل لنفس البنك'], 422);
                if ($fromEntity->balance < $amount) return response()->json(['message' => 'الرصيد غير كافي'], 422);

                $transaction = BankTransaction::create([
                    'date' => $date,
                    'type' => 'transfer_bank_to_bank',
                    'from_bank_id' => $fromEntity->id,
                    'to_bank_id' => $toEntity->id,
                    'amount' => $amount,
                    'notes' => $request->notes,
                    'user_id' => auth()->id(),
                ]);

                app(BankOperationalLedgerService::class)->transfer(
                    $fromEntity,
                    $toEntity,
                    (float) $amount,
                    $request->notes ?? '',
                    'V2-T' . $transaction->id,
                    $date
                );

                DB::commit();

                return response()->json(['message' => 'تم التحويل بنجاح'], 200);

            } elseif ($type === 'transfer_bank_to_safe') {
                $fromEntity = Bank::find($fromId);
                $toEntity = Safe::find($toId);

                if (!$fromEntity) return response()->json(['message' => 'البنك غير موجود'], 404);
                if (!$toEntity) return response()->json(['message' => 'الخزينة غير موجودة'], 404);
                if ($fromEntity->balance < $amount) return response()->json(['message' => 'الرصيد غير كافي'], 422);

                $transaction = BankTransaction::create([
                    'date' => $date,
                    'type' => 'transfer_bank_to_safe',
                    'from_bank_id' => $fromEntity->id,
                    'to_safe_id' => $toEntity->id,
                    'amount' => $amount,
                    'notes' => $request->notes,
                    'user_id' => auth()->id(),
                ]);

            } elseif ($type === 'transfer_safe_to_bank') {
                $fromEntity = Safe::find($fromId);
                $toEntity = Bank::find($toId);

                if (!$fromEntity) return response()->json(['message' => 'الخزينة غير موجودة'], 404);
                if (!$toEntity) return response()->json(['message' => 'البنك غير موجود'], 404);
                if ($fromEntity->balance < $amount) return response()->json(['message' => 'الرصيد غير كافي'], 422);

                $transaction = BankTransaction::create([
                    'date' => $date,
                    'type' => 'transfer_safe_to_bank',
                    'from_safe_id' => $fromEntity->id,
                    'to_bank_id' => $toEntity->id,
                    'amount' => $amount,
                    'notes' => $request->notes,
                    'user_id' => auth()->id(),
                ]);
            }

            // Execute Balance Updates (bank↔safe transfers only; bank↔bank handled above)
            if ($type !== 'transfer_bank_to_bank') {
                $ledger = app(BankOperationalLedgerService::class);
                $transferRef = 'V2-T' . ($transaction->id ?? '');
                $note = $request->notes ?? '';

                if ($type === 'transfer_bank_to_safe') {
                    $toEntity->increment('balance', $amount);
                    $ledger->recordOperationalMovement(
                        $fromEntity,
                        -(float) $amount,
                        'تحويل إلى خزينة - ' . $note,
                        $transferRef,
                        'تحويل',
                        auth()->id(),
                        $date
                    );
                } elseif ($type === 'transfer_safe_to_bank') {
                    $fromEntity->decrement('balance', $amount);
                    $ledger->recordOperationalMovement(
                        $toEntity,
                        (float) $amount,
                        'استلام من خزينة - ' . $note,
                        $transferRef,
                        'تحويل',
                        auth()->id(),
                        $date
                    );
                }
            }

            if ($type === 'transfer_bank_to_bank') {
                DB::commit();

                return response()->json(['message' => 'تم التحويل بنجاح'], 200);
            }

            $fromAccountId = ($type === 'transfer_safe_to_bank') ? $fromEntity->account_id : $fromEntity->asset_id;
            $toAccountId = ($type === 'transfer_bank_to_safe') ? $toEntity->account_id : $toEntity->asset_id;

            $accService = app(AccountingService::class);

            if ($fromAccountId) {
                AccountEntry::create([
                    'tree_account_id' => $fromAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "تحويل مالي ($type) - " . $request->notes,
                ]);
                $accService->updateAccountHierarchyBalances($fromAccountId);
            }

            if ($toAccountId) {
                AccountEntry::create([
                    'tree_account_id' => $toAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "تحويل مالي ($type) - " . $request->notes,
                ]);
                $accService->updateAccountHierarchyBalances($toAccountId);
            }

            DB::commit();

            return response()->json(['message' => 'تم التحويل بنجاح'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Handle Direct Deposit/Withdraw (Receipt/Payment) against a Tree Account
     */
    public function directTransaction(Request $request, DirectCashTransactionService $cashService)
    {
        $validator = Validator::make($request->all(), [
            'bank_id' => 'required|exists:banks,id',
            'type' => 'required|in:receipt,payment',
            'counter_account_id' => 'required|exists:tree_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $txn = $cashService->createBank($validator->validated());

            return response()->json([
                'message' => 'تمت العملية بنجاح',
                'data' => $cashService->showBankDirect($txn->id),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function listDirectTransactions(Request $request, DirectCashTransactionService $cashService)
    {
        $filters = $request->only(['bank_id', 'from_date', 'to_date', 'per_page']);

        return response()->json($cashService->listBankDirect($filters), 200);
    }

    public function showDirectTransaction(int $id, DirectCashTransactionService $cashService)
    {
        try {
            return response()->json(['data' => $cashService->showBankDirect($id)], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function updateDirectTransaction(Request $request, int $id, DirectCashTransactionService $cashService)
    {
        $validator = Validator::make($request->all(), [
            'bank_id' => 'required|exists:banks,id',
            'type' => 'required|in:receipt,payment',
            'counter_account_id' => 'required|exists:tree_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $txn = $cashService->updateBankDirect($id, $validator->validated());

            return response()->json([
                'message' => 'تم تعديل العملية بنجاح',
                'data' => $cashService->showBankDirect($txn->id),
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}
