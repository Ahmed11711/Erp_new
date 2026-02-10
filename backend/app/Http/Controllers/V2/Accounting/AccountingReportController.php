<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\TreeAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingReportController extends Controller
{
    /**
     * Daily Ledger Report
     */
    public function dailyLedger(Request $request)
    {
        $query = AccountEntry::with(['account'])
            ->select('account_entries.*')
            ->join('tree_accounts', 'account_entries.tree_account_id', '=', 'tree_accounts.id');

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('account_entries.created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        } elseif ($request->has('date')) {
            $query->whereDate('account_entries.created_at', $request->date);
        }

        if ($request->has('account_id')) {
            $query->where('account_entries.tree_account_id', $request->account_id);
        }

        $perPage = $request->get('per_page', 25);
        $entries = $query->orderBy('account_entries.created_at', 'desc')
            ->orderBy('account_entries.id', 'desc')
            ->paginate($perPage);

        // Calculate totals
        $totals = [
            'total_debit' => $entries->sum('debit'),
            'total_credit' => $entries->sum('credit'),
        ];

        return response()->json([
            'data' => $entries,
            'totals' => $totals
        ], 200);
    }

    /**
     * Account Balance Report
     */
    public function accountBalance(Request $request)
    {
        $query = TreeAccount::with(['parent', 'mainAccount']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('code')->get();

        // Calculate balances from entries
        foreach ($accounts as $account) {
            $entries = AccountEntry::where('tree_account_id', $account->id);
            
            if ($request->has('date_from') && $request->has('date_to')) {
                $entries->whereBetween('created_at', [
                    $request->date_from . ' 00:00:00',
                    $request->date_to . ' 23:59:59'
                ]);
            }

            $account->calculated_debit = $entries->sum('debit');
            $account->calculated_credit = $entries->sum('credit');
            $account->calculated_balance = $account->calculated_debit - $account->calculated_credit;
        }

        return response()->json($accounts, 200);
    }

    /**
     * Trial Balance Report
     */
    public function trialBalance(Request $request)
    {
        $date = $request->date ?? now()->format('Y-m-d');

        $accounts = TreeAccount::whereNotNull('parent_id')
            ->with(['parent'])
            ->orderBy('code')
            ->get();

        $trialBalance = [];

        foreach ($accounts as $account) {
            $debit = AccountEntry::where('tree_account_id', $account->id)
                ->whereDate('created_at', '<=', $date)
                ->sum('debit');

            $credit = AccountEntry::where('tree_account_id', $account->id)
                ->whereDate('created_at', '<=', $date)
                ->sum('credit');

            $balance = $debit - $credit;

            if ($balance != 0) {
                $trialBalance[] = [
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'account_type' => $account->type,
                    'debit' => $balance > 0 ? abs($balance) : 0,
                    'credit' => $balance < 0 ? abs($balance) : 0,
                ];
            }
        }

        $totalDebit = collect($trialBalance)->sum('debit');
        $totalCredit = collect($trialBalance)->sum('credit');

        return response()->json([
            'date' => $date,
            'data' => $trialBalance,
            'totals' => [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'difference' => abs($totalDebit - $totalCredit),
            ]
        ], 200);
    }

    /**
     * Accounting Tree Report
     */
    public function accountingTree(Request $request)
    {
        $accounts = TreeAccount::with(['children', 'parent', 'mainAccount'])
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        return response()->json($accounts, 200);
    }
    /**
     * Account Statement Report
     */
    public function accountStatement(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:tree_accounts,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $accountId = $request->input('account_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $account = TreeAccount::find($accountId);

        // 1. Calculate Opening Balance
        $openingBalance = 0;
        
        // Define balance type multiplier based on account type
        // Asset/Expense: Debit is positive (+), Credit is negative (-)
        // Liability/Income/Equity: Credit is positive (+), Debit is negative (-)
        $isDebitNature = in_array($account->type, ['asset', 'expense']);

        if ($dateFrom) {
            $openingQuery = AccountEntry::where('tree_account_id', $accountId)
                ->where('created_at', '<', $dateFrom . ' 00:00:00');
            
            $totalDebit = $openingQuery->sum('debit');
            $totalCredit = $openingQuery->sum('credit');

            if ($isDebitNature) {
                $openingBalance = $totalDebit - $totalCredit;
            } else {
                $openingBalance = $totalCredit - $totalDebit;
            }
        }

        // 2. Fetch Entries
        $query = AccountEntry::with(['voucher', 'dailyEntry'])
            ->where('tree_account_id', $accountId);

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $entries = $query->orderBy('created_at', 'asc')->get();

        // 3. Calculate Running Balance
        $runningBalance = $openingBalance;
        $processedEntries = $entries->map(function ($entry) use (&$runningBalance, $isDebitNature) {
            if ($isDebitNature) {
                $change = $entry->debit - $entry->credit;
            } else {
                $change = $entry->credit - $entry->debit;
            }
            $runningBalance += $change;
            
            $entry->running_balance = $runningBalance;
            return $entry;
        });

        return response()->json([
            'account' => $account,
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'entries' => $processedEntries,
            'total_debit' => $entries->sum('debit'),
            'total_credit' => $entries->sum('credit'),
        ], 200);
    }
}

