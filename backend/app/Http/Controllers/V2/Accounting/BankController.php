<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankTransaction;
use App\Models\Safe;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'type' => 'nullable|string', // e.g., Current, Savings
            'balance' => 'nullable|numeric',
            'usage' => 'nullable|string',
            'asset_id' => 'required|exists:tree_accounts,id', // Should match existing Bank model relation
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bank = Bank::create([
            'name' => $request->name,
            'type' => $request->type ?? 'main',
            'balance' => $request->balance ?? 0,
            'usage' => $request->usage,
            'asset_id' => $request->asset_id,
        ]);

        return response()->json([
            'message' => 'تم إنشاء البنك بنجاح',
            'data' => $bank->load('asset')
        ], 201);
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
            'asset_id' => 'nullable|exists:tree_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bank->update($request->only([
            'name', 'type', 'usage', 'asset_id'
        ]));

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

        $bank->delete();

        return response()->json(['message' => 'تم حذف البنك بنجاح'], 200);
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


            // Create Accounting Entries & Update Tree Account Balances
            // 1. Credit the Sender (From) -> Money Leaves -> Credit
            if ($fromAccountId) {
                AccountEntry::create([
                    'tree_account_id' => $fromAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "تحويل مالي ($type) - " . $request->notes,
                ]);
                $fromTree = TreeAccount::find($fromAccountId);
                if ($fromTree) {
                    $fromTree->increment('credit_balance', $amount);
                    if (in_array($fromTree->type, ['asset', 'expense'])) {
                        $fromTree->decrement('balance', $amount);
                    } else {
                        $fromTree->increment('balance', $amount);
                    }
                }
            }

            // 2. Debit the Receiver (To) -> Money Enters -> Debit
            if ($toAccountId) {
                 AccountEntry::create([
                    'tree_account_id' => $toAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "تحويل مالي ($type) - " . $request->notes,
                ]);
                $toTree = TreeAccount::find($toAccountId);
                if ($toTree) {
                    $toTree->increment('debit_balance', $amount);
                    if (in_array($toTree->type, ['asset', 'expense'])) {
                        $toTree->increment('balance', $amount);
                    } else {
                        $toTree->decrement('balance', $amount);
                    }
                }
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
                // 1. Debit Bank (Money In)
                AccountEntry::create([
                    'tree_account_id' => $bankAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "إيداع بنكي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                
                // 2. Credit Counter Account (Source)
                AccountEntry::create([
                    'tree_account_id' => $counterAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "إيداع بنكي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);

                // Update Balances
                // Bank Balance
                $bank->increment('balance', $amount);

                // Tree Balances
                $bankTree = TreeAccount::find($bankAccountId);
                $bankTree->increment('debit_balance', $amount);
                // Asset increases with Debit
                if (in_array($bankTree->type, ['asset', 'expense'])) {
                     $bankTree->increment('balance', $amount);
                } else {
                     $bankTree->decrement('balance', $amount);
                }

                $counterTree = TreeAccount::find($counterAccount->id);
                $counterTree->increment('credit_balance', $amount);
                // Asset decreases with Credit, Liability increases
                if (in_array($counterTree->type, ['asset', 'expense'])) {
                     $counterTree->decrement('balance', $amount);
                } else {
                     $counterTree->increment('balance', $amount);
                }

            } else { // payment
                // 1. Credit Bank (Money Out)
                AccountEntry::create([
                    'tree_account_id' => $bankAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "سحب بنكي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);

                // 2. Debit Counter Account (Destination)
                AccountEntry::create([
                    'tree_account_id' => $counterAccount->id,
                     'debit' => $amount,
                    'credit' => 0,
                    'description' => "سحب بنكي - " . $notes,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);

                // Update Balances
                // Bank Balance
                $bank->decrement('balance', $amount);

                // Tree Balances
                $bankTree = TreeAccount::find($bankAccountId);
                $bankTree->increment('credit_balance', $amount);
                 if (in_array($bankTree->type, ['asset', 'expense'])) {
                     $bankTree->decrement('balance', $amount);
                } else {
                     $bankTree->increment('balance', $amount);
                }

                $counterTree = TreeAccount::find($counterAccount->id);
                $counterTree->increment('debit_balance', $amount);
                 if (in_array($counterTree->type, ['asset', 'expense'])) {
                     $counterTree->increment('balance', $amount);
                } else {
                     $counterTree->decrement('balance', $amount);
                }
            }

            // Save Model Updates
            $bank->save();
            if(isset($bankTree)) $bankTree->save();
            if(isset($counterTree)) $counterTree->save();

            DB::commit();
            return response()->json(['message' => 'تمت العملية بنجاح'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}
