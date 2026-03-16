<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\TreeAccount\TreeAccountResource;
use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingReportController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

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
     * Trial Balance Report - Enhanced Version (ميزان المراجعة الشامل)
     * Follows best practices of professional accounting systems:
     * - Includes ALL accounts (roots + children) - no exclusion
     * - Opening balance, movement, closing balance columns
     * - Standard account type order: Assets, Liabilities, Equity, Revenue, Expense
     * - Optimized queries (single batch fetch)
     * - Options: leaf_only, include_zero_balance
     */
    public function trialBalance(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'account_type' => 'nullable|string|in:asset,liability,equity,revenue,expense',
            'level' => 'nullable|integer',
            'search' => 'nullable|string',
            'leaf_only' => 'nullable|boolean',
            'include_zero_balance' => 'nullable|boolean',
        ]);

        $dateFrom = $request->date_from;
        $dateTo = $request->date_to ?? now()->format('Y-m-d');
        $leafOnly = $request->boolean('leaf_only', false);
        $includeZeroBalance = $request->boolean('include_zero_balance', false);

        // Build accounts query - include ALL accounts (roots + children)
        $accountsQuery = TreeAccount::with(['parent']);

        if ($leafOnly) {
            $accountsQuery->whereDoesntHave('children');
        }

        if ($request->filled('account_type')) {
            $accountsQuery->where('type', $request->account_type);
        }

        if ($request->filled('level')) {
            $accountsQuery->where('level', $request->level);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $accountsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Standard order: asset, liability, equity, revenue, expense (GAAP/IFRS)
        $typeOrder = ['asset' => 1, 'liability' => 2, 'equity' => 3, 'revenue' => 4, 'expense' => 5];
        $accounts = $accountsQuery->get()->sortBy(function ($a) use ($typeOrder) {
            return ($typeOrder[$a->type] ?? 99) * 100000 + (int) $a->code;
        })->values();

        $accountIds = $accounts->pluck('id')->toArray();

        if (empty($accountIds)) {
            return response()->json([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'data' => [],
                'totals' => [
                    'opening_debit' => 0, 'opening_credit' => 0,
                    'movement_debit' => 0, 'movement_credit' => 0,
                    'closing_debit' => 0, 'closing_credit' => 0,
                    'opening_difference' => 0, 'movement_difference' => 0, 'closing_difference' => 0,
                ],
                'validation' => ['is_balanced' => true, 'message' => 'ميزان المراجعة متوازن'],
                'count' => 0,
                'options' => ['leaf_only' => $leafOnly, 'include_zero_balance' => $includeZeroBalance],
            ], 200);
        }

        // Optimized: single query for all opening balances (before date_from)
        $openingRows = collect();
        if ($dateFrom && !empty($accountIds)) {
            $openingRows = AccountEntry::whereIn('tree_account_id', $accountIds)
                ->where('created_at', '<', $dateFrom . ' 00:00:00')
                ->selectRaw('tree_account_id, COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
                ->groupBy('tree_account_id')
                ->get()
                ->keyBy('tree_account_id');
        }

        // Optimized: single query for all movement (date_from to date_to)
        $movementRows = AccountEntry::whereIn('tree_account_id', $accountIds)
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom . ' 00:00:00'))
            ->where('created_at', '<=', $dateTo . ' 23:59:59')
            ->selectRaw('tree_account_id, COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
            ->groupBy('tree_account_id')
            ->get()
            ->keyBy('tree_account_id');

        $trialBalance = [];

        foreach ($accounts as $account) {
            $openingRow = $openingRows->get($account->id);
            $openingDebit = (float) ($openingRow?->total_debit ?? 0);
            $openingCredit = (float) ($openingRow?->total_credit ?? 0);
            $openingBalance = $openingDebit - $openingCredit;

            $movRow = $movementRows->get($account->id);
            $movementDebit = (float) ($movRow->total_debit ?? 0);
            $movementCredit = (float) ($movRow->total_credit ?? 0);

            $closingBalance = $openingBalance + ($movementDebit - $movementCredit);

            $hasActivity = $openingBalance != 0 || $movementDebit != 0 || $movementCredit != 0 || $closingBalance != 0;

            if (!$hasActivity && !$includeZeroBalance) {
                continue;
            }

            $trialBalance[] = [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_name_en' => $account->name_en,
                'account_type' => $account->type,
                'level' => $account->level,
                'parent_name' => $account->parent?->name,

                'opening_debit' => $openingBalance > 0 ? round(abs($openingBalance), 2) : 0,
                'opening_credit' => $openingBalance < 0 ? round(abs($openingBalance), 2) : 0,

                'movement_debit' => round($movementDebit, 2),
                'movement_credit' => round($movementCredit, 2),

                'closing_debit' => $closingBalance > 0 ? round(abs($closingBalance), 2) : 0,
                'closing_credit' => $closingBalance < 0 ? round(abs($closingBalance), 2) : 0,
            ];
        }

        $totals = [
            'opening_debit' => round(collect($trialBalance)->sum('opening_debit'), 2),
            'opening_credit' => round(collect($trialBalance)->sum('opening_credit'), 2),
            'movement_debit' => round(collect($trialBalance)->sum('movement_debit'), 2),
            'movement_credit' => round(collect($trialBalance)->sum('movement_credit'), 2),
            'closing_debit' => round(collect($trialBalance)->sum('closing_debit'), 2),
            'closing_credit' => round(collect($trialBalance)->sum('closing_credit'), 2),
        ];

        $totals['opening_difference'] = round(abs($totals['opening_debit'] - $totals['opening_credit']), 2);
        $totals['movement_difference'] = round(abs($totals['movement_debit'] - $totals['movement_credit']), 2);
        $totals['closing_difference'] = round(abs($totals['closing_debit'] - $totals['closing_credit']), 2);

        $validation = $this->accountingService->validateTrialBalance($trialBalance);

        if ($request->get('update_hierarchy', false)) {
            foreach ($accounts as $account) {
                $this->accountingService->updateAccountHierarchyBalances($account->id);
            }
        }

        return response()->json([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'data' => $trialBalance,
            'totals' => $totals,
            'validation' => $validation,
            'count' => count($trialBalance),
            'options' => [
                'leaf_only' => $leafOnly,
                'include_zero_balance' => $includeZeroBalance,
            ],
        ], 200);
    }

    /**
     * Process Cash Transaction
     */
    public function processCashTransaction(Request $request)
    {
        $request->validate([
            'cash_account_id' => 'required|exists:tree_accounts,id',
            'account_id' => 'required|exists:tree_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'transaction_type' => 'required|in:cash_in,cash_out',
            'voucher_id' => 'nullable|exists:vouchers,id',
            'daily_entry_id' => 'nullable|exists:daily_entries,id'
        ]);

        $result = $this->accountingService->processCashTransaction($request->all());

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get Account Hierarchy with Balances
     */
    public function getAccountHierarchy(Request $request)
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $hierarchy = $this->accountingService->getAccountHierarchyWithBalances($dateFrom, $dateTo);

        return response()->json($hierarchy, 200);
    }

    /**
     * Update Account Hierarchy Balances (single account)
     */
    public function updateHierarchyBalances(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:tree_accounts,id'
        ]);

        try {
            $this->accountingService->updateAccountHierarchyBalances($request->account_id);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث أرصدة التسلسل الهرمي بنجاح'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تحديث الأرصدة: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Recalculate ALL account hierarchy balances from scratch
     * إعادة حساب جميع أرصدة الشجرة - يضمن أن كل حساب يؤثر في ما فوقه
     */
    public function recalculateAllHierarchyBalances(Request $request)
    {
        try {
            $result = $this->accountingService->recalculateAllHierarchyBalances();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'updated_count' => $result['updated_count'],
                'total_accounts' => $result['total_accounts'] ?? null,
                'errors' => $result['errors'] ?? []
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إعادة الحساب: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Validate Income Structure
     */
    public function validateIncomeStructure(Request $request)
    {
        $validation = $this->accountingService->validateIncomeStructure();

        return response()->json($validation, 200);
    }

    /**
     * Accounting Tree Report
     */
    public function accountingTree(Request $request)
    {
        $accounts = TreeAccount::with([
                // Load nested children up to reasonable depth for the UI tree
                'children.children.children.children',
                'parent',
                'mainAccount',
            ])
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        return response()->json(
            TreeAccountResource::collection($accounts),
            200
        );
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

    /**
     * Income Statement (قائمة الدخل)
     * Multi-step format per GAAP/IFRS best practices
     * Computes: Revenue → COGS → Gross Profit → Operating Expenses → Operating Income
     *         → Other Income/Expenses → EBIT → Interest → EBT → Tax → Net Income
     * Inputs: month=YYYY-MM or date_from/date_to
     * Uses only leaf accounts to prevent double-counting in hierarchical chart of accounts
     */
    public function incomeStatement(Request $request)
    {
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($request->filled('month')) {
            $start = $request->month . '-01';
            $end = date('Y-m-t', strtotime($start));
        } else {
            $start = $request->date_from ?: date('Y-m-01');
            $end = $request->date_to ?: date('Y-m-t', strtotime($start));
        }

        $withinPeriod = function ($q) use ($start, $end) {
            return $q->where('created_at', '>=', $start . ' 00:00:00')
                     ->where('created_at', '<=', $end . ' 23:59:59');
        };

        // Leaf accounts only: prevents double-counting when parent balance = sum of children
        $leafAccountIds = TreeAccount::whereDoesntHave('children')->pluck('id')->toArray();
        $accounts = TreeAccount::select('id', 'name', 'name_en', 'code', 'type', 'detail_type')
            ->whereIn('id', $leafAccountIds)
            ->get()
            ->keyBy('id');

        $entrySums = AccountEntry::select('tree_account_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
            ->whereIn('tree_account_id', $leafAccountIds)
            ->when(true, $withinPeriod)
            ->groupBy('tree_account_id')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->tree_account_id => [
                    'debit' => (float)$row->total_debit,
                    'credit' => (float)$row->total_credit
                ]];
            });

        $netByNature = function ($acc, $sum) {
            if (!$sum) return 0.0;
            $type = $acc->type ?? null;
            if (in_array($type, ['revenue', 'income', 'liability', 'equity'])) {
                return (float)$sum['credit'] - (float)$sum['debit'];
            }
            return (float)$sum['debit'] - (float)$sum['credit'];
        };

        $hasKeyword = function ($name, array $patterns) {
            $n = mb_strtolower($name ?? '');
            foreach ($patterns as $p) {
                if (str_contains($n, mb_strtolower($p))) return true;
            }
            return false;
        };

        // Buckets - GAAP/IFRS multi-step structure
        $sales = 0.0;
        $salesReturns = 0.0;
        $cogs = 0.0;
        $operatingExpenses = 0.0;
        $salesExpenses = 0.0;
        $adminExpenses = 0.0;
        $purchaseExpenses = 0.0;
        $depreciation = 0.0;
        $otherRevenues = 0.0;
        $capitalGains = 0.0;
        $interestExpense = 0.0;
        $interestIncome = 0.0;
        $taxExpense = 0.0;

        foreach ($entrySums as $accId => $sum) {
            $acc = $accounts->get($accId);
            if (!$acc) continue;

            $net = $netByNature($acc, $sum);
            $name = ($acc->name ?? '') . ' ' . ($acc->name_en ?? '');
            $detail = $acc->detail_type ?? '';

            if (in_array($acc->type, ['revenue', 'income'])) {
                if ($detail === 'sales' || $hasKeyword($name, ['مبيعات', 'sales'])) {
                    $sales += $net;
                } elseif ($detail === 'sales_returns' || $hasKeyword($name, ['مرتجع', 'مردود', 'returns'])) {
                    $salesReturns += abs($net);
                } elseif ($detail === 'capital_gain' || $hasKeyword($name, ['رأس مالية', 'capital gain'])) {
                    $capitalGains += $net;
                } elseif ($detail === 'interest_income' || $hasKeyword($name, ['فوائد', 'إيراد فوائد', 'interest'])) {
                    $interestIncome += $net;
                } else {
                    $otherRevenues += $net;
                }
            } elseif ($acc->type === 'expense') {
                if ($detail === 'cogs' || $hasKeyword($name, ['تكلفة المبيعات', 'cost of sales', 'COGS', 'تكلفة البضاعة'])) {
                    $cogs += $net;
                } elseif ($detail === 'sales_expense' || $hasKeyword($name, ['مصاريف مبيعات', 'مصروف مبيعات'])) {
                    $salesExpenses += $net;
                } elseif ($detail === 'admin' || $hasKeyword($name, ['عمومية', 'إدارية', 'إداري', 'general & admin', 'g&a'])) {
                    $adminExpenses += $net;
                } elseif ($detail === 'purchase_expense' || $hasKeyword($name, ['مصروف مشتريات', 'شحن مشتريات', 'توريد'])) {
                    $purchaseExpenses += $net;
                } elseif ($detail === 'depreciation' || $hasKeyword($name, ['اهلاك', 'استهلاك', 'depreciation', 'amortization'])) {
                    $depreciation += $net;
                } elseif ($detail === 'interest_expense' || $hasKeyword($name, ['فوائد مدينة', 'مصروف فوائد', 'interest expense'])) {
                    $interestExpense += $net;
                } elseif ($detail === 'tax' || $hasKeyword($name, ['ضريبة', 'tax', 'زكاة'])) {
                    $taxExpense += $net;
                } else {
                    $operatingExpenses += $net;
                }
            }
        }

        // Inventory (for reference / COGS reconciliation)
        $inventoryAcc = TreeAccount::where('detail_type', 'inventory')->first();
        if (!$inventoryAcc) {
            $inventoryAcc = TreeAccount::where('type', 'asset')->where('name', 'like', '%مخزون%')->first();
        }

        $openingInventory = 0.0;
        $closingInventory = 0.0;
        if ($inventoryAcc) {
            $openingSums = AccountEntry::where('tree_account_id', $inventoryAcc->id)
                ->where('created_at', '<', $start . ' 00:00:00')
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();
            $openingInventory = max(0, (float)($openingSums->d ?? 0) - (float)($openingSums->c ?? 0));

            $closingSums = AccountEntry::where('tree_account_id', $inventoryAcc->id)
                ->where('created_at', '<=', $end . ' 23:59:59')
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();
            $closingInventory = max(0, (float)($closingSums->d ?? 0) - (float)($closingSums->c ?? 0));
        }

        // Multi-step income statement (GAAP/IFRS)
        $netSales = $sales - $salesReturns;
        $grossProfit = $netSales - $cogs;
        $operatingExpensesTotal = $operatingExpenses + $salesExpenses + $adminExpenses + $depreciation + $purchaseExpenses;
        $operatingIncome = $grossProfit - $operatingExpensesTotal;
        $otherIncomeTotal = $capitalGains + $otherRevenues + $interestIncome;
        $otherExpensesTotal = $interestExpense;
        $earningsBeforeTax = $operatingIncome + $otherIncomeTotal - $otherExpensesTotal;
        $netProfitAfterTax = $earningsBeforeTax - $taxExpense;

        $grossMarginPercent = $netSales != 0 ? round(($grossProfit / $netSales) * 100, 2) : 0;
        $operatingMarginPercent = $netSales != 0 ? round(($operatingIncome / $netSales) * 100, 2) : 0;
        $netMarginPercent = $netSales != 0 ? round(($netProfitAfterTax / $netSales) * 100, 2) : 0;

        return response()->json([
            'date_from' => $start,
            'date_to' => $end,
            // Revenue section
            'sales' => round($sales, 2),
            'sales_returns' => round($salesReturns, 2),
            'net_sales' => round($netSales, 2),
            // Cost of sales
            'opening_inventory' => round($openingInventory, 2),
            'closing_inventory' => round($closingInventory, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin_percent' => $grossMarginPercent,
            // Operating expenses
            'operating_expenses' => round($operatingExpenses, 2),
            'sales_expenses' => round($salesExpenses, 2),
            'admin_expenses' => round($adminExpenses, 2),
            'purchase_expenses' => round($purchaseExpenses, 2),
            'depreciation' => round($depreciation, 2),
            'operating_expenses_total' => round($operatingExpensesTotal, 2),
            'operating_income' => round($operatingIncome, 2),
            'operating_margin_percent' => $operatingMarginPercent,
            // Other income/expenses
            'other_revenues' => round($otherRevenues, 2),
            'capital_gains' => round($capitalGains, 2),
            'interest_income' => round($interestIncome, 2),
            'interest_expense' => round($interestExpense, 2),
            'other_income_total' => round($otherIncomeTotal, 2),
            'other_expenses_total' => round($otherExpensesTotal, 2),
            // Bottom line
            'earnings_before_tax' => round($earningsBeforeTax, 2),
            'tax_expense' => round($taxExpense, 2),
            'net_profit_after_tax' => round($netProfitAfterTax, 2),
            'net_profit_before_tax' => round($earningsBeforeTax, 2),
            'profit_margin_percent' => $netMarginPercent,
        ], 200);
    }

    /**
     * Product Performance Report
     * Per product: sales qty/amount, returns qty/amount, net sales, allocated COGS and gross profit
     * Inputs: date_from/date_to (optional). If none provided, current month to-date.
     */
    public function productPerformance(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $start = $request->date_from ?: date('Y-m-01');
        $end = $request->date_to ?: date('Y-m-d');

        // Helper closures
        $withinPeriod = function ($q) use ($start, $end) {
            return $q->where('created_at', '>=', $start . ' 00:00:00')
                     ->where('created_at', '<=', $end . ' 23:59:59');
        };

        // 1) Load movements from categories_balance within period
        // Shipments by order and category (to allocate COGS)
        $shipments = DB::table('categories_balance')
            ->select('invoice_number', 'category_id',
                DB::raw('SUM(quantity) as qty'),
                DB::raw('SUM(total_price) as amount'))
            ->where('type', 'شحن طلب')
            ->when(true, $withinPeriod)
            ->groupBy('invoice_number', 'category_id')
            ->get();

        // Returns per category
        $returns = DB::table('categories_balance')
            ->select('category_id',
                DB::raw('SUM(quantity) as qty'),
                DB::raw('SUM(total_price) as amount'))
            ->where('type', 'رفض استلام طلب')
            ->when(true, $withinPeriod)
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        // Aggregate shipments per category (sales)
        $salesByCategory = [];
        foreach ($shipments as $row) {
            $cid = (int)$row->category_id;
            if (!isset($salesByCategory[$cid])) {
                $salesByCategory[$cid] = ['qty' => 0.0, 'amount' => 0.0];
            }
            $salesByCategory[$cid]['qty'] += (float)$row->qty;
            $salesByCategory[$cid]['amount'] += (float)$row->amount;
        }

        // 2) Fetch COGS entries per order within the period and allocate across categories by sales weight
        $cogsByOrder = [];
        $cogsEntries = AccountEntry::select('description', 'debit', 'credit', 'created_at')
            ->when(true, $withinPeriod)
            ->get();

        foreach ($cogsEntries as $entry) {
            if (!$entry->description) continue;
            if (mb_strpos($entry->description, 'تكلفة البضاعة المباعة للطلب رقم') !== false) {
                if (preg_match('/الطلب رقم\s+(\d+)/u', $entry->description, $m)) {
                    $orderId = $m[1];
                    $amount = (float)$entry->debit - (float)$entry->credit;
                    $cogsByOrder[$orderId] = ($cogsByOrder[$orderId] ?? 0.0) + $amount;
                }
            }
        }

        // Total sales per order (for weights)
        $salesByOrder = [];
        foreach ($shipments as $row) {
            $orderId = $row->invoice_number;
            $salesByOrder[$orderId] = ($salesByOrder[$orderId] ?? 0.0) + (float)$row->amount;
        }

        // Allocate COGS to categories (from account_entries if available)
        $allocatedCogs = []; // category_id => cogs
        foreach ($shipments as $row) {
            $orderId = $row->invoice_number;
            $orderCogs = $cogsByOrder[$orderId] ?? 0.0;
            $orderSales = $salesByOrder[$orderId] ?? 0.0;
            if ($orderCogs <= 0 || $orderSales <= 0) continue;

            $cid = (int)$row->category_id;
            $weight = (float)$row->amount / $orderSales;
            $allocatedCogs[$cid] = ($allocatedCogs[$cid] ?? 0.0) + ($orderCogs * $weight);
        }

        // Fallback: if no COGS from account_entries (e.g. cogs/inventory accounts not configured),
        // calculate from categories table using avg cost (same formula as OrdersController)
        if (empty($allocatedCogs)) {
            $categories = DB::table('categories')->select('id', 'quantity', 'total_price', 'unit_price')->get()->keyBy('id');
            foreach ($salesByCategory as $cid => $salesData) {
                $salesQty = (float)($salesData['qty'] ?? 0);
                $retQty = (float)(optional($returns->get($cid))->qty ?? 0);
                $netQty = max(0, $salesQty - $retQty);
                if ($netQty <= 0) continue;

                $cat = $categories->get($cid);
                $avgCost = 0.0;
                if ($cat) {
                    $q = (float)($cat->quantity ?? 0);
                    $tp = (float)($cat->total_price ?? 0);
                    $up = (float)($cat->unit_price ?? 0);
                    if ($q > 0 && $tp != 0) {
                        $avgCost = $tp / $q;
                    } elseif ($up > 0) {
                        $avgCost = $up;
                    }
                }
                $allocatedCogs[$cid] = $netQty * $avgCost;
            }
        }

        // 3) Build per-product rows
        $categoryNames = DB::table('categories')->select('id', 'category_name')->get()->keyBy('id');

        $productRows = [];
        $categoryIds = array_unique(array_merge(array_keys($salesByCategory), array_keys($returns->toArray()), array_keys($allocatedCogs)));
        foreach ($categoryIds as $cid) {
            $sales = $salesByCategory[$cid]['amount'] ?? 0.0;
            $salesQty = $salesByCategory[$cid]['qty'] ?? 0.0;
            $ret = $returns->get($cid);
            $retQty = $ret->qty ?? 0.0;
            $retAmount = $ret->amount ?? 0.0;
            $netSales = $sales - $retAmount;
            $cogs = $allocatedCogs[$cid] ?? 0.0;
            $gross = $netSales - $cogs;
            $name = optional($categoryNames->get($cid))->category_name ?? ('#' . $cid);
            $code = null; // category_code column may not exist in categories table

            $productRows[] = [
                'category_id' => $cid,
                'category_code' => $code,
                'category_name' => $name,
                'sales_qty' => round($salesQty, 3),
                'sales_amount' => round($sales, 2),
                'returns_qty' => round($retQty, 3),
                'returns_amount' => round($retAmount, 2),
                'net_sales' => round($netSales, 2),
                'cogs' => round($cogs, 2),
                'gross_profit' => round($gross, 2),
                'gross_margin_percent' => $netSales != 0 ? round(($gross / $netSales) * 100, 2) : 0,
            ];
        }

        // Totals
        $totals = [
            'sales_qty' => round(array_sum(array_column($productRows, 'sales_qty')), 3),
            'sales_amount' => round(array_sum(array_column($productRows, 'sales_amount')), 2),
            'returns_qty' => round(array_sum(array_column($productRows, 'returns_qty')), 3),
            'returns_amount' => round(array_sum(array_column($productRows, 'returns_amount')), 2),
            'net_sales' => round(array_sum(array_column($productRows, 'net_sales')), 2),
            'cogs' => round(array_sum(array_column($productRows, 'cogs')), 2),
            'gross_profit' => round(array_sum(array_column($productRows, 'gross_profit')), 2),
            'gross_margin_percent' => 0, // computed below
        ];
        $totals['gross_margin_percent'] = $totals['net_sales'] != 0
            ? round(($totals['gross_profit'] / $totals['net_sales']) * 100, 2)
            : 0;

        // Sort by net sales desc for UI
        usort($productRows, fn($a, $b) => $b['net_sales'] <=> $a['net_sales']);

        return response()->json([
            'date_from' => $start,
            'date_to' => $end,
            'totals' => $totals,
            'data' => $productRows
        ], 200);
    }

    /**
     * Category Profitability Report (تقرير ربحية الصنف)
     * Same data as product-performance with category type, measurement unit, orders count
     */
    public function categoryProfitability(Request $request)
    {
        $productResponse = $this->productPerformance($request);
        $data = json_decode($productResponse->getContent(), true);
        if (!isset($data['data'])) {
            return $productResponse;
        }

        $categoryIds = array_column($data['data'], 'category_id');
        $categories = DB::table('categories')
            ->leftJoin('productions', 'categories.production_id', '=', 'productions.id')
            ->leftJoin('measurements', 'categories.measurement_id', '=', 'measurements.id')
            ->whereIn('categories.id', $categoryIds)
            ->select(
                'categories.id',
                DB::raw('COALESCE(productions.production_line, productions.warehouse, "-") as category_type'),
                DB::raw('COALESCE(measurements.unit, "-") as measurement_unit')
            )
            ->get()
            ->keyBy('id');

        $ordersByCategory = DB::table('categories_balance')
            ->where('type', 'شحن طلب')
            ->where('created_at', '>=', ($data['date_from'] ?? date('Y-m-01')) . ' 00:00:00')
            ->where('created_at', '<=', ($data['date_to'] ?? date('Y-m-d')) . ' 23:59:59')
            ->select('category_id', DB::raw('COUNT(DISTINCT invoice_number) as orders_count'))
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $rows = [];
        foreach ($data['data'] as $row) {
            $cid = $row['category_id'];
            $cat = $categories->get($cid);
            $ordersCount = optional($ordersByCategory->get($cid))->orders_count ?? 0;
            $salesQty = (float)($row['sales_qty'] ?? 0);
            $avgSellingPrice = $salesQty > 0 ? round((float)$row['sales_amount'] / $salesQty, 2) : 0;
            $netQty = max(0, $salesQty - (float)($row['returns_qty'] ?? 0));
            $avgCost = $netQty > 0 ? round((float)$row['cogs'] / $netQty, 2) : 0;

            $rows[] = [
                'category_id' => $cid,
                'category_name' => $row['category_name'] ?? '-',
                'category_type' => $cat ? $cat->category_type : '-',
                'measurement_unit' => $cat ? $cat->measurement_unit : '-',
                'sales_qty' => $row['sales_qty'],
                'sales_amount' => $row['sales_amount'],
                'orders_count' => (int)$ordersCount,
                'returns_qty' => $row['returns_qty'],
                'rejected_qty' => $row['returns_qty'],
                'avg_selling_price' => $avgSellingPrice,
                'avg_cost' => $avgCost,
                'net_profit' => $row['gross_profit'],
                'total_profit' => $row['gross_profit'],
                'profit_margin' => $row['gross_margin_percent'],
                'description' => '',
            ];
        }

        return response()->json([
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'totals' => $data['totals'] ?? [],
            'data' => $rows
        ], 200);
    }
}
