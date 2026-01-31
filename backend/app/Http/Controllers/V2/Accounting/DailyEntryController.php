<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DailyEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DailyEntry::with(['items.account', 'user']);

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('date', [$request->date_from, $request->date_to]);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('entry_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 25);
        $entries = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json($entries, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'description' => 'nullable|string',
            'items' => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:tree_accounts,id',
            'items.*.debit' => 'required_without:items.*.credit|numeric|min:0',
            'items.*.credit' => 'required_without:items.*.debit|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate that total debit equals total credit
        $totalDebit = collect($request->items)->sum('debit');
        $totalCredit = collect($request->items)->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return response()->json([
                'message' => 'يجب أن يكون مجموع المدين مساوياً لمجموع الدائن',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate entry number
            $lastEntry = DailyEntry::orderByDesc('entry_number')->first();
            $entryNumber = $lastEntry ? (int)$lastEntry->entry_number + 1 : 1;

            $dailyEntry = DailyEntry::create([
                'date' => $request->date,
                'entry_number' => str_pad($entryNumber, 6, '0', STR_PAD_LEFT),
                'description' => $request->description,
                'user_id' => auth()->id(),
            ]);

            // Create items and account entries
            foreach ($request->items as $item) {
                $entryItem = DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $item['account_id'],
                    'debit' => $item['debit'] ?? 0,
                    'credit' => $item['credit'] ?? 0,
                    'notes' => $item['notes'] ?? null,
                ]);

                // Create account entry
                AccountEntry::create([
                    'tree_account_id' => $item['account_id'],
                    'debit' => $item['debit'] ?? 0,
                    'credit' => $item['credit'] ?? 0,
                    'description' => $item['notes'] ?? $request->description,
                    'daily_entry_id' => $dailyEntry->id,
                ]);

                // Update account balance
                $account = TreeAccount::find($item['account_id']);
                $balanceChange = ($item['debit'] ?? 0) - ($item['credit'] ?? 0);
                $account->increment('balance', $balanceChange);
                
                if ($item['debit'] ?? 0 > 0) {
                    $account->increment('debit_balance', $item['debit']);
                }
                if ($item['credit'] ?? 0 > 0) {
                    $account->increment('credit_balance', $item['credit']);
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'تم إنشاء القيد اليومي بنجاح',
                'data' => $dailyEntry->load(['items.account', 'user'])
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
        $entry = DailyEntry::with(['items.account', 'user'])->find($id);
        
        if (!$entry) {
            return response()->json(['message' => 'القيد اليومي غير موجود'], 404);
        }

        return response()->json($entry, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $dailyEntry = DailyEntry::find($id);
        
        if (!$dailyEntry) {
            return response()->json(['message' => 'القيد اليومي غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|date',
            'description' => 'nullable|string',
            'items' => 'sometimes|array|min:2',
            'items.*.account_id' => 'required_with:items|exists:tree_accounts,id',
            'items.*.debit' => 'required_without:items.*.credit|numeric|min:0',
            'items.*.credit' => 'required_without:items.*.debit|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('items')) {
            $totalDebit = collect($request->items)->sum('debit');
            $totalCredit = collect($request->items)->sum('credit');

            if (abs($totalDebit - $totalCredit) > 0.01) {
                return response()->json([
                    'message' => 'يجب أن يكون مجموع المدين مساوياً لمجموع الدائن'
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Reverse old entries
            foreach ($dailyEntry->items as $item) {
                $account = TreeAccount::find($item->account_id);
                $balanceChange = $item->debit - $item->credit;
                $account->decrement('balance', $balanceChange);
                $account->decrement('debit_balance', $item->debit);
                $account->decrement('credit_balance', $item->credit);
            }

            // Delete old entries
            AccountEntry::where('daily_entry_id', $dailyEntry->id)->delete();
            $dailyEntry->items()->delete();

            // Update daily entry
            $dailyEntry->update($request->only(['date', 'description']));

            // Create new items
            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    $entryItem = DailyEntryItem::create([
                        'daily_entry_id' => $dailyEntry->id,
                        'account_id' => $item['account_id'],
                        'debit' => $item['debit'] ?? 0,
                        'credit' => $item['credit'] ?? 0,
                        'notes' => $item['notes'] ?? null,
                    ]);

                    AccountEntry::create([
                        'tree_account_id' => $item['account_id'],
                        'debit' => $item['debit'] ?? 0,
                        'credit' => $item['credit'] ?? 0,
                        'description' => $item['notes'] ?? $dailyEntry->description,
                        'daily_entry_id' => $dailyEntry->id,
                    ]);

                    $account = TreeAccount::find($item['account_id']);
                    $balanceChange = ($item['debit'] ?? 0) - ($item['credit'] ?? 0);
                    $account->increment('balance', $balanceChange);
                    $account->increment('debit_balance', $item['debit'] ?? 0);
                    $account->increment('credit_balance', $item['credit'] ?? 0);
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'تم تحديث القيد اليومي بنجاح',
                'data' => $dailyEntry->load(['items.account', 'user'])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $dailyEntry = DailyEntry::find($id);
        
        if (!$dailyEntry) {
            return response()->json(['message' => 'القيد اليومي غير موجود'], 404);
        }

        DB::beginTransaction();
        try {
            // Reverse account entries
            foreach ($dailyEntry->items as $item) {
                $account = TreeAccount::find($item->account_id);
                $balanceChange = $item->debit - $item->credit;
                $account->decrement('balance', $balanceChange);
                $account->decrement('debit_balance', $item->debit);
                $account->decrement('credit_balance', $item->credit);
            }

            // Delete account entries
            AccountEntry::where('daily_entry_id', $dailyEntry->id)->delete();

            // Delete items
            $dailyEntry->items()->delete();

            // Delete entry
            $dailyEntry->delete();

            DB::commit();
            return response()->json(['message' => 'تم حذف القيد اليومي بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}

