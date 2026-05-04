<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankTransaction;
use App\Models\Safe;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Bank::with('asset')->where('type', 'main'); // Assuming 'asset' relation exists as per Model

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 25);
        $banks = $query->orderBy('name')->paginate($perPage);

        return response()->json($banks, 200);
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

            // Execute Balance Updates
            $fromEntity->decrement('balance', $amount);
            $toEntity->increment('balance', $amount);
            
            // Create Accounting Entries
            // Assuming tree accounts are linked via 'asset_id' for Banks and 'account_id' for Safes
            // Normalize the ID field name access
            $fromAccountId = ($type === 'transfer_safe_to_bank') ? $fromEntity->account_id : $fromEntity->asset_id;
            $toAccountId   = ($type === 'transfer_bank_to_safe') ? $toEntity->account_id : $toEntity->asset_id;
            // For Bank to Bank
            if ($type === 'transfer_bank_to_bank') {
               $fromAccountId = $fromEntity->asset_id;
               $toAccountId = $toEntity->asset_id;
            }


            $accService = app(\App\Services\Accounting\AccountingService::class);

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
    public function directTransaction(Request $request)
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

        DB::beginTransaction();
        try {
            $bank = Bank::find($request->bank_id);
            $counterAccount = TreeAccount::find($request->counter_account_id);
            $amount = $request->amount;
            $type = $request->type;
            $date = $request->date;
            $notes = $request->notes;

            // Ensure Bank has a linked Tree Account
            if (!$bank->asset_id) {
                return response()->json(['message' => 'البنك غير مرتبط بحساب شجري'], 422);
            }
            $bankAccountId = $bank->asset_id;

            // Check Balance for Withdrawal (Payment)
            if ($type === 'payment' && $bank->balance < $amount) {
                return response()->json(['message' => 'رصيد البنك غير كافي للسحب'], 422);
            }

            // Create Accounting Entries
            // Receipt (Deposit): Debit Bank, Credit Counter Account
            // Payment (Withdraw): Credit Bank, Debit Counter Account

            if ($type === 'receipt') {
                AccountEntry::create([
                    'tree_account_id' => $bankAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "إيداع بنكي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                AccountEntry::create([
                    'tree_account_id' => $counterAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "إيداع بنكي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                $bank->increment('balance', $amount);
            } else {
                AccountEntry::create([
                    'tree_account_id' => $bankAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "سحب بنكي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                AccountEntry::create([
                    'tree_account_id' => $counterAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "سحب بنكي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                $bank->decrement('balance', $amount);
            }

            $accService = app(\App\Services\Accounting\AccountingService::class);
            $accService->updateAccountHierarchyBalances($bankAccountId);
            $accService->updateAccountHierarchyBalances($counterAccount->id);

            DB::commit();
            return response()->json(['message' => 'تمت العملية بنجاح'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}
