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
     * Trial Balance Report - Enhanced Version
     * Supports date ranges, opening balance, movement, and closing balance
     */
    public function trialBalance(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'account_type' => 'nullable|string',
            'level' => 'nullable|integer',
            'search' => 'nullable|string',
        ]);

        $dateFrom = $request->date_from;
        $dateTo = $request->date_to ?? now()->format('Y-m-d');
        
        // Build accounts query
        $accountsQuery = TreeAccount::whereNotNull('parent_id')
            ->with(['parent']);

        // Apply filters
        if ($request->has('account_type')) {
            $accountsQuery->where('type', $request->account_type);
        }

        if ($request->has('level')) {
            $accountsQuery->where('level', $request->level);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $accountsQuery->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $accounts = $accountsQuery->orderBy('code')->get();

        $trialBalance = [];

        foreach ($accounts as $account) {
            // Calculate opening balance (before date_from)
            $openingDebit = 0;
            $openingCredit = 0;
            
            if ($dateFrom) {
                $openingDebit = AccountEntry::where('tree_account_id', $account->id)
                    ->where('created_at', '<', $dateFrom . ' 00:00:00')
                    ->sum('debit');

                $openingCredit = AccountEntry::where('tree_account_id', $account->id)
                    ->where('created_at', '<', $dateFrom . ' 00:00:00')
                    ->sum('credit');
            }

            $openingBalance = $openingDebit - $openingCredit;

            // Calculate movement (between date_from and date_to)
            $movementQuery = AccountEntry::where('tree_account_id', $account->id);
            
            if ($dateFrom) {
                $movementQuery->where('created_at', '>=', $dateFrom . ' 00:00:00');
            }
            
            $movementQuery->where('created_at', '<=', $dateTo . ' 23:59:59');

            $movementDebit = $movementQuery->sum('debit');
            $movementCredit = AccountEntry::where('tree_account_id', $account->id)
                ->when($dateFrom, function($q) use ($dateFrom) {
                    return $q->where('created_at', '>=', $dateFrom . ' 00:00:00');
                })
                ->where('created_at', '<=', $dateTo . ' 23:59:59')
                ->sum('credit');

            // Calculate closing balance
            $closingBalance = $openingBalance + ($movementDebit - $movementCredit);

            // Only include accounts with activity
            if ($openingBalance != 0 || $movementDebit != 0 || $movementCredit != 0 || $closingBalance != 0) {
                $trialBalance[] = [
                    'account_id' => $account->id,
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'account_name_en' => $account->name_en,
                    'account_type' => $account->type,
                    'level' => $account->level,
                    'parent_name' => $account->parent ? $account->parent->name : null,
                    
                    // Opening Balance
                    'opening_debit' => $openingBalance > 0 ? abs($openingBalance) : 0,
                    'opening_credit' => $openingBalance < 0 ? abs($openingBalance) : 0,
                    
                    // Movement
                    'movement_debit' => $movementDebit,
                    'movement_credit' => $movementCredit,
                    
                    // Closing Balance
                    'closing_debit' => $closingBalance > 0 ? abs($closingBalance) : 0,
                    'closing_credit' => $closingBalance < 0 ? abs($closingBalance) : 0,
                ];
            }
        }

        // Calculate totals
        $totals = [
            'opening_debit' => collect($trialBalance)->sum('opening_debit'),
            'opening_credit' => collect($trialBalance)->sum('opening_credit'),
            'movement_debit' => collect($trialBalance)->sum('movement_debit'),
            'movement_credit' => collect($trialBalance)->sum('movement_credit'),
            'closing_debit' => collect($trialBalance)->sum('closing_debit'),
            'closing_credit' => collect($trialBalance)->sum('closing_credit'),
        ];

        $totals['opening_difference'] = abs($totals['opening_debit'] - $totals['opening_credit']);
        $totals['movement_difference'] = abs($totals['movement_debit'] - $totals['movement_credit']);
        $totals['closing_difference'] = abs($totals['closing_debit'] - $totals['closing_credit']);

        return response()->json([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'data' => $trialBalance,
            'totals' => $totals,
            'count' => count($trialBalance),
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

