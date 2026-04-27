<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\BudgetReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DailyEntryController extends Controller
{
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

        $totalDebit = collect($request->items)->sum('debit');
        $totalCredit = collect($request->items)->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return response()->json([
                'message' => 'يجب أن يكون مجموع المدين مساوياً لمجموع الدائن',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit
            ], 422);
        }

        $budgetService = app(BudgetReviewService::class);
        $itemsForBudget = array_map(fn($i) => [
            'tree_account_id' => $i['account_id'],
            'debit' => $i['debit'] ?? 0,
            'credit' => $i['credit'] ?? 0,
        ], $request->items);
        $budgetResult = $budgetService->checkBudget($itemsForBudget, $request->date);
        if (!$budgetResult['valid']) {
            return response()->json([
                'message' => $budgetResult['message'],
                'violations' => $budgetResult['violations'],
            ], 422);
        }

        DB::beginTransaction();
        try {
            $entryNumber = DailyEntry::getNextEntryNumber();

            $dailyEntry = DailyEntry::create([
                'date' => $request->date,
                'entry_number' => $entryNumber,
                'description' => $request->description,
                'user_id' => auth()->id(),
            ]);

            $touchedAccountIds = [];

            foreach ($request->items as $item) {
                DailyEntryItem::create([
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
                    'description' => $item['notes'] ?? $request->description,
                    'daily_entry_id' => $dailyEntry->id,
                ]);

                $touchedAccountIds[] = $item['account_id'];
            }

            $accService = app(AccountingService::class);
            foreach (array_unique($touchedAccountIds) as $accId) {
                $accService->updateAccountHierarchyBalances($accId);
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

    public function show($id)
    {
        $entry = DailyEntry::with(['items.account', 'user'])->find($id);

        if (!$entry) {
            return response()->json(['message' => 'القيد اليومي غير موجود'], 404);
        }

        return response()->json($entry, 200);
    }

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
            $touchedAccountIds = $dailyEntry->items->pluck('account_id')->toArray();

            AccountEntry::where('daily_entry_id', $dailyEntry->id)->delete();
            $dailyEntry->items()->delete();

            $dailyEntry->update($request->only(['date', 'description']));

            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    DailyEntryItem::create([
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

                    $touchedAccountIds[] = $item['account_id'];
                }
            }

            $accService = app(AccountingService::class);
            foreach (array_unique($touchedAccountIds) as $accId) {
                $accService->updateAccountHierarchyBalances($accId);
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

    public function destroy($id)
    {
        $dailyEntry = DailyEntry::find($id);

        if (!$dailyEntry) {
            return response()->json(['message' => 'القيد اليومي غير موجود'], 404);
        }

        DB::beginTransaction();
        try {
            $touchedAccountIds = $dailyEntry->items->pluck('account_id')->toArray();

            AccountEntry::where('daily_entry_id', $dailyEntry->id)->delete();
            $dailyEntry->items()->delete();
            $dailyEntry->delete();

            $accService = app(AccountingService::class);
            foreach (array_unique($touchedAccountIds) as $accId) {
                $accService->updateAccountHierarchyBalances($accId);
            }

            DB::commit();
            return response()->json(['message' => 'تم حذف القيد اليومي بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}
