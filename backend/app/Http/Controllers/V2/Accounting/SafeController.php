<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Safe;
use App\Models\SafeTransaction;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SafeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Safe::with('account');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 25);
        $safes = $query->orderBy('name')->paginate($perPage);

        return response()->json($safes, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $balance = (float) ($request->input('balance', 0));

        $rules = [
            'name' => 'required|string',
            'type' => 'required|in:main,branch',
            'balance' => 'nullable|numeric|min:0',
            'is_inside_branch' => 'nullable|boolean',
            'branch_name' => 'nullable|string',
            'account_id' => 'required|exists:tree_accounts,id',
            'counter_account_id' => 'nullable|exists:tree_accounts,id|different:account_id',
        ];

        if ($balance > 0.000001) {
            $rules['counter_account_id'] = 'required|exists:tree_accounts,id|different:account_id';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $safe = Safe::create([
                'name' => $request->name,
                'type' => $request->type,
                'balance' => $balance,
                'is_inside_branch' => $request->is_inside_branch ?? false,
                'branch_name' => $request->branch_name,
                'account_id' => $request->account_id,
            ]);

            if ($balance > 0.000001) {
                $safeAccountId = (int) $safe->account_id;
                $counterId = (int) $request->counter_account_id;
                $now = now();
                $desc = 'رصيد افتتاحي خزينة - ' . $safe->name;

                AccountEntry::create([
                    'tree_account_id' => $safeAccountId,
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

                // تراكمي مثل CapitalController — لا نستخدم updateAccountHierarchyBalances هنا لأنه يعيد
                // كتابة debit/credit/balance من مجموع القيود فقط ويمسح أرصدة قديمة غير مُثبتة بقيود.
                $safeTree = TreeAccount::find($safeAccountId);
                $safeTree->increment('debit_balance', $balance);
                if (in_array($safeTree->type, ['asset', 'expense'], true)) {
                    $safeTree->increment('balance', $balance);
                } else {
                    $safeTree->decrement('balance', $balance);
                }

                $counterTree = TreeAccount::find($counterId);
                $counterTree->increment('credit_balance', $balance);
                if (in_array($counterTree->type, ['asset', 'expense'], true)) {
                    $counterTree->decrement('balance', $balance);
                } else {
                    $counterTree->increment('balance', $balance);
                }

                /** @var AccountingService $accService */
                $accService = app(AccountingService::class);
                $accService->propagateBalancesUpFromLeaf($safeAccountId);
                $accService->propagateBalancesUpFromLeaf($counterId);
            }

            DB::commit();

            return response()->json([
                'message' => 'تم إنشاء الخزينة بنجاح',
                'data' => $safe->load('account'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    // ... (show method remains unchanged) ...

    public function update(Request $request, $id)
    {
        $safe = Safe::find($id);
        
        if (!$safe) {
            return response()->json(['message' => 'الخزينة غير موجودة'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'is_inside_branch' => 'nullable|boolean',
            'branch_name' => 'nullable|string',
            'account_id' => 'required|exists:tree_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $safe->update($request->only([
            'name', 'is_inside_branch', 'branch_name', 'account_id'
        ]));

        return response()->json([
            'message' => 'تم تحديث الخزينة بنجاح',
            'data' => $safe->load('account')
        ], 200);
    }

    public function show($id)
    {
        $safe = Safe::with('account')->find($id);
        if (!$safe) {
            return response()->json(['message' => 'الخزينة غير موجودة'], 404);
        }
        return response()->json($safe, 200);
    }

    public function destroy($id)
    {
        $safe = Safe::find($id);
        if (!$safe) {
            return response()->json(['message' => 'الخزينة غير موجودة'], 404);
        }
        if ((float) $safe->balance != 0) {
            return response()->json(['message' => 'لا يمكن حذف خزينة لها رصيد'], 422);
        }
        $safe->delete();
        return response()->json(['message' => 'تم حذف الخزينة بنجاح'], 200);
    }

    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_safe_id' => 'required|exists:safes,id',
            'to_safe_id' => 'required|exists:safes,id|different:from_safe_id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $fromSafe = Safe::find($request->from_safe_id);
            $toSafe = Safe::find($request->to_safe_id);

            if ($fromSafe->balance < $request->amount) {
                return response()->json(['message' => 'رصيد الخزينة المصدر غير كافي'], 422);
            }

            // Create transaction
            SafeTransaction::create([
                'date' => now(),
                'type' => 'transfer',
                'from_safe_id' => $request->from_safe_id,
                'to_safe_id' => $request->to_safe_id,
                'amount' => $request->amount,
                'notes' => $request->notes,
                'user_id' => auth()->id(),
            ]);

            // Update balances
            $fromSafe->decrement('balance', $request->amount);
            $toSafe->increment('balance', $request->amount);

            $desc = "تحويل من خزينة {$fromSafe->name} إلى خزينة {$toSafe->name}" . ($request->notes ? " - {$request->notes}" : "");
            $accService = app(AccountingService::class);

            if ($fromSafe->account_id) {
                AccountEntry::create([
                    'tree_account_id' => $fromSafe->account_id,
                    'debit' => 0,
                    'credit' => $request->amount,
                    'description' => $desc,
                ]);
                $accService->updateAccountHierarchyBalances($fromSafe->account_id);
            }

            if ($toSafe->account_id) {
                AccountEntry::create([
                    'tree_account_id' => $toSafe->account_id,
                    'debit' => $request->amount,
                    'credit' => 0,
                    'description' => $desc,
                ]);
                $accService->updateAccountHierarchyBalances($toSafe->account_id);
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
            'safe_id' => 'required|exists:safes,id',
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
            $safe = Safe::find($request->safe_id);
            $counterAccount = TreeAccount::find($request->counter_account_id);
            $amount = $request->amount;
            $type = $request->type;
            $date = $request->date;
            $notes = $request->notes;

            // Ensure Safe has a linked Tree Account
            if (!$safe->account_id) {
                return response()->json(['message' => 'الخزينة غير مرتبطة بحساب شجري'], 422);
            }
            $safeAccountId = $safe->account_id;

            // Check Balance for Withdrawal (Payment)
            if ($type === 'payment' && $safe->balance < $amount) {
                return response()->json(['message' => 'رصيد الخزينة غير كافي'], 422);
            }

            // Create Safe Transaction Record
            SafeTransaction::create([
                'from_safe_id' => ($type === 'payment') ? $safe->id : null,
                'to_safe_id' => ($type === 'receipt') ? $safe->id : null,
                'amount' => $amount,
                'type' => $type, // ensure 'receipt' and 'payment' are valid enum values or handle accordingly
                'date' => $date,
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);

            if ($type === 'receipt') {
                // 1. Debit Safe (Money In)
                AccountEntry::create([
                    'tree_account_id' => $safeAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "إيداع خزينة - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                
                // 2. Credit Counter Account (Source)
                AccountEntry::create([
                    'tree_account_id' => $counterAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "إيداع خزينة - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);

                // Update Balances
                $safe->increment('balance', $amount);

                $safeTree = TreeAccount::find($safeAccountId);
                $safeTree->increment('debit_balance', $amount);
                if (in_array($safeTree->type, ['asset', 'expense'])) {
                     $safeTree->increment('balance', $amount);
                } else {
                     $safeTree->decrement('balance', $amount);
                }
                $safeTree->save();

                $counterTree = TreeAccount::find($counterAccount->id);
                $counterTree->increment('credit_balance', $amount);
                if (in_array($counterTree->type, ['asset', 'expense'])) {
                     $counterTree->decrement('balance', $amount);
                } else {
                     $counterTree->increment('balance', $amount);
                }
                $counterTree->save();

            } else { // payment
                // 1. Credit Safe (Money Out)
                AccountEntry::create([
                    'tree_account_id' => $safeAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "صرف خزينة - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);

                // 2. Debit Counter Account (Destination)
                AccountEntry::create([
                    'tree_account_id' => $counterAccount->id,
                     'debit' => $amount,
                    'credit' => 0,
                    'description' => "صرف خزينة - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);

                // Update Balances
                $safe->decrement('balance', $amount);

                $safeTree = TreeAccount::find($safeAccountId);
                $safeTree->increment('credit_balance', $amount);
                 if (in_array($safeTree->type, ['asset', 'expense'])) {
                     $safeTree->decrement('balance', $amount);
                } else {
                     $safeTree->increment('balance', $amount);
                }
                $safeTree->save();

                $counterTree = TreeAccount::find($counterAccount->id);
                $counterTree->increment('debit_balance', $amount);
                 if (in_array($counterTree->type, ['asset', 'expense'])) {
                     $counterTree->increment('balance', $amount);
                } else {
                     $counterTree->decrement('balance', $amount);
                }
                $counterTree->save();
            }

            DB::commit();
            return response()->json(['message' => 'تمت العملية بنجاح'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}

