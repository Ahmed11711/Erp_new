<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Safe;
use App\Models\SafeTransaction;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'type' => 'required|in:main,branch',
            'balance' => 'nullable|numeric|min:0',
            'is_inside_branch' => 'nullable|boolean',
            'branch_name' => 'nullable|string',
            'account_id' => 'nullable|exists:tree_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $safe = Safe::create([
            'name' => $request->name,
            'type' => $request->type,
            'balance' => $request->balance ?? 0,
            'is_inside_branch' => $request->is_inside_branch ?? false,
            'branch_name' => $request->branch_name,
            'account_id' => $request->account_id,
        ]);

        return response()->json([
            'message' => 'تم إنشاء الخزينة بنجاح',
            'data' => $safe->load('account')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $safe = Safe::with('account')->find($id);
        
        if (!$safe) {
            return response()->json(['message' => 'الخزينة غير موجودة'], 404);
        }

        return response()->json($safe, 200);
    }

    /**
     * Update the specified resource in storage.
     */
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
            'account_id' => 'nullable|exists:tree_accounts,id',
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $safe = Safe::find($id);
        
        if (!$safe) {
            return response()->json(['message' => 'الخزينة غير موجودة'], 404);
        }

        if ($safe->balance > 0) {
            return response()->json(['message' => 'لا يمكن حذف الخزينة لأن رصيدها أكبر من صفر'], 422);
        }

        $safe->delete();

        return response()->json(['message' => 'تم حذف الخزينة بنجاح'], 200);
    }

    /**
     * Transfer between safes
     */
    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_safe_id' => 'required|exists:safes,id',
            'to_safe_id' => 'required|exists:safes,id|different:from_safe_id',
            'amount' => 'required|numeric|min:0',
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

            // Create accounting entries if accounts are set
            if ($fromSafe->account_id) {
                AccountEntry::create([
                    'tree_account_id' => $fromSafe->account_id,
                    'debit' => 0,
                    'credit' => $request->amount,
                    'description' => "تحويل من خزينة {$fromSafe->name} إلى خزينة {$toSafe->name}",
                ]);
                $fromTree = TreeAccount::find($fromSafe->account_id);
                if ($fromTree) {
                    $fromTree->increment('credit_balance', $request->amount);
                    if (in_array($fromTree->type, ['asset', 'expense'])) {
                        $fromTree->decrement('balance', $request->amount);
                    } else {
                        $fromTree->increment('balance', $request->amount);
                    }
                }
            }

            if ($toSafe->account_id) {
                AccountEntry::create([
                    'tree_account_id' => $toSafe->account_id,
                    'debit' => $request->amount,
                    'credit' => 0,
                    'description' => "تحويل من خزينة {$fromSafe->name} إلى خزينة {$toSafe->name}",
                ]);
                $toTree = TreeAccount::find($toSafe->account_id);
                if ($toTree) {
                    $toTree->increment('debit_balance', $request->amount);
                    if (in_array($toTree->type, ['asset', 'expense'])) {
                        $toTree->increment('balance', $request->amount);
                    } else {
                        $toTree->decrement('balance', $request->amount);
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
}

