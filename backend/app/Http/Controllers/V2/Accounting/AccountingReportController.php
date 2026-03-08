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

        // Validate trial balance equality
        $validation = $this->accountingService->validateTrialBalance($trialBalance);

        // Update account hierarchy balances if requested
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
     * Update Account Hierarchy Balances
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
     * Income Statement
     * Computes revenues, COGS, expenses and margins from accounting entries
     * Inputs: month=YYYY-MM or date_from/date_to
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

        // Helper to constrain by period
        $withinPeriod = function ($q) use ($start, $end) {
            return $q->where('created_at', '>=', $start . ' 00:00:00')
                     ->where('created_at', '<=', $end . ' 23:59:59');
        };

        // Load all accounts once
        $accounts = TreeAccount::select('id', 'name', 'name_en', 'type', 'detail_type')->get()->keyBy('id');

        // Query sums by account id
        $entrySums = AccountEntry::select('tree_account_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
            ->when(true, $withinPeriod)
            ->groupBy('tree_account_id')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->tree_account_id => [
                    'debit' => (float)$row->total_debit,
                    'credit' => (float)$row->total_credit
                ]];
            });

        // Helper to net amount by nature
        $netByNature = function ($acc, $sum) {
            if (!$sum) return 0.0;
            $type = $acc->type ?? null;
            if (in_array($type, ['revenue', 'income', 'liability', 'equity'])) {
                return (float)$sum['credit'] - (float)$sum['debit'];
            }
            return (float)$sum['debit'] - (float)$sum['credit'];
        };

        // Pattern helpers
        $hasKeyword = function ($name, array $patterns) {
            $n = mb_strtolower($name ?? '');
            foreach ($patterns as $p) {
                if (str_contains($n, mb_strtolower($p))) return true;
            }
            return false;
        };

        // Buckets
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

        foreach ($entrySums as $accId => $sum) {
            $acc = $accounts->get($accId);
            if (!$acc) continue;

            $net = $netByNature($acc, $sum);
            $name = $acc->name . ' ' . $acc->name_en;
            $detail = $acc->detail_type;

            if (in_array($acc->type, ['revenue', 'income'])) {
                if ($detail === 'sales' || $hasKeyword($name, ['مبيعات', 'sales'])) {
                    $sales += $net;
                } elseif ($detail === 'sales_returns' || $hasKeyword($name, ['مرتجع', 'مردود', 'returns'])) {
                    $salesReturns += abs($net);
                } elseif ($detail === 'capital_gain' || $hasKeyword($name, ['رأس مالية', 'capital gain'])) {
                    $capitalGains += $net;
                } else {
                    $otherRevenues += $net;
                }
            } elseif ($acc->type === 'expense') {
                if ($detail === 'cogs' || $hasKeyword($name, ['تكلفة المبيعات', 'cost of sales', 'COGS'])) {
                    $cogs += $net;
                } elseif ($detail === 'sales_expense' || $hasKeyword($name, ['مصاريف مبيعات'])) {
                    $salesExpenses += $net;
                } elseif ($detail === 'admin' || $hasKeyword($name, ['عمومية', 'إدارية', 'إداري', 'general & admin', 'g&a'])) {
                    $adminExpenses += $net;
                } elseif ($detail === 'purchase_expense' || $hasKeyword($name, ['مصروف مشتريات', 'شحن مشتريات', 'توريد'])) {
                    $purchaseExpenses += $net;
                } elseif ($detail === 'depreciation' || $hasKeyword($name, ['اهلاك', 'استهلاك', 'depreciation', 'amortization'])) {
                    $depreciation += $net;
                } else {
                    $operatingExpenses += $net;
                }
            }
        }

        // Get inventory balances (opening at start, closing at end)
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

        $netSales = $sales - $salesReturns;
        $grossProfit = $netSales - $cogs;
        $otherIncomeTotal = $capitalGains + $otherRevenues;
        $otherExpensesTotal = $purchaseExpenses + $adminExpenses + $depreciation + $salesExpenses;
        $operatingExpensesTotal = $operatingExpenses + $salesExpenses + $adminExpenses + $depreciation + $purchaseExpenses;
        $netProfitBeforeTax = $grossProfit + $otherIncomeTotal - $otherExpensesTotal;

        // Margins
        $grossMarginPercent = $netSales != 0 ? round(($grossProfit / $netSales) * 100, 2) : 0;
        $netMarginPercent = $netSales != 0 ? round(($netProfitBeforeTax / $netSales) * 100, 2) : 0;

        return response()->json([
            'date_from' => $start,
            'date_to' => $end,
            'sales' => round($sales, 2),
            'sales_returns' => round($salesReturns, 2),
            'net_sales' => round($netSales, 2),
            'opening_inventory' => round($openingInventory, 2),
            'closing_inventory' => round($closingInventory, 2),
            'cogs' => round($cogs, 2),
            'operating_expenses' => round($operatingExpenses, 2),
            'sales_expenses' => round($salesExpenses, 2),
            'admin_expenses' => round($adminExpenses, 2),
            'purchase_expenses' => round($purchaseExpenses, 2),
            'depreciation' => round($depreciation, 2),
            'capital_gains' => round($capitalGains, 2),
            'other_revenues' => round($otherRevenues, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin_percent' => $grossMarginPercent,
            'other_income_total' => round($otherIncomeTotal, 2),
            'other_expenses_total' => round($otherExpensesTotal, 2),
            'operating_expenses_total' => round($operatingExpensesTotal, 2),
            'net_profit_before_tax' => round($netProfitBeforeTax, 2),
            'profit_margin_percent' => $netMarginPercent
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

        // Allocate COGS to categories
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

        // 3) Build per-product rows
        $categoryNames = DB::table('categories')->select('id', 'category_name', 'category_code')->get()->keyBy('id');

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
            $code = optional($categoryNames->get($cid))->category_code ?? null;

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
}
