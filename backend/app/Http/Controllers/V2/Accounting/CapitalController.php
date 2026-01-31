<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Capital;
use App\Models\Bank;
use App\Models\Safe;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CapitalController extends Controller
{
    public function index()
    {
        $capitals = Capital::with('equityAccount')->orderBy('date', 'desc')->paginate(20);
        return response()->json($capitals);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'target_type' => 'required|in:bank,safe',
            'target_id' => 'required|integer',
            'equity_account_id' => 'required|exists:tree_accounts,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Create Capital Record
            $capital = Capital::create([
                'amount' => $request->amount,
                'date' => $request->date,
                'target_type' => $request->target_type,
                'target_id' => $request->target_id,
                'equity_account_id' => $request->equity_account_id,
                'notes' => $request->notes,
                'user_id' => auth()->id()
            ]);

            // 2. Identify Target Account (Debit Side)
            $targetAccountInfo = null;
            if ($request->target_type === 'bank') {
                $bank = Bank::find($request->target_id);
                if (!$bank) throw new \Exception("البنك غير موجود");
                $bank->increment('balance', $request->amount);
                $targetAccountInfo = ['id' => $bank->asset_id, 'name' => $bank->name];
                
                // Track Bank Movement if needed? (BankTransaction) - Optional but good practice
            } else {
                $safe = Safe::find($request->target_id);
                if (!$safe) throw new \Exception("الخزينة غير موجودة");
                $safe->increment('balance', $request->amount);
                $targetAccountInfo = ['id' => $safe->account_id, 'name' => $safe->name];
            }

            if (!$targetAccountInfo['id']) {
                 throw new \Exception("الحساب المستهدف (بنك/خزينة) غير مرتبط بشجرة الحسابات");
            }

            // 3. Create Accounting Entries
            // Debit: Target (Asset) - Money coming in
            AccountEntry::create([
                'tree_account_id' => $targetAccountInfo['id'],
                'debit' => $request->amount,
                'credit' => 0,
                'description' => "إضافة رأس مال - " . $request->notes,
                'created_at' => $request->date,
                'updated_at' => $request->date,
            ]);
            $targetTree = TreeAccount::find($targetAccountInfo['id']);
            $targetTree->increment('debit_balance', $request->amount);
             if (in_array($targetTree->type, ['asset', 'expense'])) {
                $targetTree->increment('balance', $request->amount);
            } else {
                $targetTree->decrement('balance', $request->amount);
            }

            // Credit: Equity Account
            AccountEntry::create([
                'tree_account_id' => $request->equity_account_id,
                'debit' => 0,
                'credit' => $request->amount,
                'description' => "إضافة رأس مال - " . $request->notes,
                'created_at' => $request->date,
                'updated_at' => $request->date,
            ]);
            $equityTree = TreeAccount::find($request->equity_account_id);
            $equityTree->increment('credit_balance', $request->amount);
            if (in_array($equityTree->type, ['asset', 'expense'])) {
                $equityTree->decrement('balance', $request->amount);
            } else {
                $equityTree->increment('balance', $request->amount);
            }

            DB::commit();
            return response()->json(['message' => 'تم إضافة رأس المال بنجاح', 'data' => $capital]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}
