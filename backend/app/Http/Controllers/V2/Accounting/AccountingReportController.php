<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\TreeAccount\TreeAccountResource;
use App\Models\AccountEntry;
use App\Repositories\TreeAccount\TreeAccountRepositoryInterface;
use App\Models\DailyEntry;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountEntryEditLinkService;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\BankOperationalLedgerService;
use App\Services\Accounting\ProductPerformanceReportService;
use App\Services\Accounting\TrialBalanceLevelView;
use App\Services\CategoryInventoryCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingReportController extends Controller
{
    protected $accountingService;

    public function __construct(
        AccountingService $accountingService,
        protected ProductPerformanceReportService $productPerformanceReportService
    ) {
        $this->accountingService = $accountingService;
    }

    /**
     * Daily Ledger Report
     * Uses accounting date: daily_entries.date / vouchers.date, else account_entries.created_at.
     * Ordered chronologically (oldest first).
     */
    public function dailyLedger(Request $request)
    {
        $effectiveDateExpr = 'DATE(COALESCE(de.date, v.date, account_entries.created_at))';

        $query = AccountEntry::with(['account', 'dailyEntry.user', 'voucher.user'])
            ->select('account_entries.*')
            ->join('tree_accounts', 'account_entries.tree_account_id', '=', 'tree_accounts.id')
            ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
            ->leftJoin('vouchers as v', 'account_entries.voucher_id', '=', 'v.id');

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereRaw("{$effectiveDateExpr} BETWEEN ? AND ?", [
                $request->date_from,
                $request->date_to,
            ]);
        } elseif ($request->has('date')) {
            $query->whereRaw("{$effectiveDateExpr} = ?", [$request->date]);
        }

        if ($request->filled('account_id')) {
            $query->where('account_entries.tree_account_id', $request->account_id);
        }

        /** فقط حركات مرتبطة برأس قيد يومي (شاشة القيود اليومية أو أي ترحيل ينشئ DailyEntry) */
        if ($request->boolean('daily_entry_only')) {
            $query->whereNotNull('account_entries.daily_entry_id');
        }

        if ($request->filled('user_id')) {
            $this->applyAccountEntryUserFilter($query, (int) $request->user_id);
        }

        // إجمالي المدين/الدائن لكل النتائج المصفّاة — وليس للصفحة الحالية فقط
        $totalsQuery = clone $query;
        $totals = [
            'total_debit' => (float) $totalsQuery->sum('account_entries.debit'),
            'total_credit' => (float) $totalsQuery->sum('account_entries.credit'),
        ];

        $perPage = $request->get('per_page', 25);
        $entries = $query->orderByRaw("{$effectiveDateExpr} ASC")
            ->orderBy('account_entries.daily_entry_id')
            ->orderBy('account_entries.created_at', 'asc')
            ->orderBy('account_entries.id', 'asc')
            ->paginate($perPage);

        $entries->getCollection()->transform(function (AccountEntry $entry) {
            $entry->setAttribute(
                'entry_date',
                $entry->dailyEntry?->date ?? $entry->voucher?->date ?? $entry->created_at
            );
            $entry->setAttribute(
                'posted_at',
                $entry->dailyEntry?->created_at
                    ?? $entry->voucher?->created_at
                    ?? $entry->created_at
            );

            // رقم القيد الظاهر للمستخدم: يطابق شاشة القيود اليومية؛ وإلا سند/دفعة دفعة دُفعت بدون قيد يومي
            $journalRef = $entry->dailyEntry?->entry_number;
            if (! $journalRef && $entry->voucher_id) {
                $ref = $entry->voucher?->reference_number;
                $journalRef = $ref !== null && $ref !== ''
                    ? (string) $ref
                    : ('سند #' . $entry->voucher_id);
            }
            if (! $journalRef && $entry->entry_batch_code) {
                $journalRef = $entry->entry_batch_code;
            }
            $entry->setAttribute('journal_entry_number', $journalRef);
            $entry->setAttribute(
                'journal_header_description',
                $entry->dailyEntry?->description
            );
            $entry->setAttribute(
                'journal_user_name',
                $entry->dailyEntry?->user?->name ?? $entry->voucher?->user?->name
            );

            return $entry;
        });

        $this->attachPerformedByUserNames($entries->getCollection());
        $entries->getCollection()->transform(function (AccountEntry $entry) {
            if (! $entry->getAttribute('journal_user_name') && $entry->getAttribute('user_name')) {
                $entry->setAttribute('journal_user_name', $entry->getAttribute('user_name'));
            }
            if (! $entry->getAttribute('journal_header_description') && $entry->description) {
                $entry->setAttribute('journal_header_description', $entry->description);
            }

            return $entry;
        });

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
            $entries = AccountEntry::query()
                ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
                ->leftJoin('vouchers as v', 'account_entries.voucher_id', '=', 'v.id')
                ->where('account_entries.tree_account_id', $account->id);

            if ($request->has('date_from') && $request->has('date_to')) {
                $effectiveDateExpr = 'DATE(COALESCE(de.date, v.date, account_entries.created_at))';
                $entries->whereRaw("{$effectiveDateExpr} BETWEEN ? AND ?", [
                    $request->date_from,
                    $request->date_to,
                ]);
            }

            $account->calculated_debit = $entries->sum('account_entries.debit');
            $account->calculated_credit = $entries->sum('account_entries.credit');
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
            'account_type' => 'nullable|string|in:asset,liability,equity,revenue,expense,settlement',
            'level' => 'nullable|integer',
            'search' => 'nullable|string',
            'leaf_only' => 'nullable|boolean',
            'include_zero_balance' => 'nullable|boolean',
        ]);

        $dateFrom = $request->date_from;
        $dateTo = $request->date_to ?? now()->format('Y-m-d');
        $leafOnly = $request->boolean('leaf_only', false);
        $includeZeroBalance = $request->boolean('include_zero_balance', false);
        $level = $request->filled('level') ? (int) $request->level : null;
        $search = $request->filled('search') ? trim((string) $request->search) : '';
        // عند فلترة مستوى معيّن: الأرصدة = الحساب + كل الفروع (تجميع شجري)
        $rollupSubtree = $level !== null;

        // Build accounts query - include ALL accounts (roots + children)
        $accountsQuery = TreeAccount::with(['parent']);

        if ($leafOnly) {
            $accountsQuery->whereDoesntHave('children');
        }

        if ($request->filled('account_type')) {
            $accountsQuery->where('type', $request->account_type);
        }

        // البحث على مستوى معيّن يتم بعد اختيار صفوف القطع (الحساب أو فروعه أو آبائه).
        if (!$rollupSubtree && $search !== '') {
            $accountsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Standard order: asset, liability, equity, revenue, expense, settlement
        $typeOrder = ['asset' => 1, 'liability' => 2, 'equity' => 3, 'revenue' => 4, 'expense' => 5, 'settlement' => 6];
        $accounts = $accountsQuery->get();

        if ($rollupSubtree) {
            $treeRows = TreeAccount::query()->get(['id', 'parent_id', 'level', 'name', 'name_en', 'code']);
            $displayIds = (new TrialBalanceLevelView())->displayAccountIds(
                $treeRows,
                $level,
                $search !== '' ? $search : null,
                $accounts->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
            $displaySet = array_fill_keys($displayIds, true);
            $accounts = $accounts->filter(fn ($account) => isset($displaySet[(int) $account->id]));
        }

        $accounts = $accounts->sortBy(function ($a) use ($typeOrder) {
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
                'options' => [
                    'leaf_only' => $leafOnly,
                    'include_zero_balance' => $includeZeroBalance,
                    'rollup_subtree' => $rollupSubtree,
                ],
            ], 200);
        }

        $childrenMap = [];
        if ($rollupSubtree) {
            $childrenMap = $this->buildTreeAccountChildrenMap();
        }

        // قيود كل الحسابات عند التجميع (حتى تُحسب حركة الأبناء تحت المستوى المختار)
        $entryAccountScope = $rollupSubtree
            ? null
            : $accountIds;

        $effectiveDateExpr = 'DATE(COALESCE(de.date, v.date, account_entries.created_at))';

        $openingRows = collect();
        if ($dateFrom) {
            $openingQuery = AccountEntry::query()
                ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
                ->leftJoin('vouchers as v', 'account_entries.voucher_id', '=', 'v.id')
                ->selectRaw('account_entries.tree_account_id, COALESCE(SUM(account_entries.debit),0) as total_debit, COALESCE(SUM(account_entries.credit),0) as total_credit')
                ->groupBy('account_entries.tree_account_id');
            $this->applyOpeningPeriodFilter($openingQuery, $effectiveDateExpr, $dateFrom);
            if ($entryAccountScope !== null) {
                $openingQuery->whereIn('account_entries.tree_account_id', $entryAccountScope);
            }
            $openingRows = $openingQuery->get()->keyBy('tree_account_id');
        }

        $movementQuery = AccountEntry::query()
            ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
            ->leftJoin('vouchers as v', 'account_entries.voucher_id', '=', 'v.id')
            ->selectRaw('account_entries.tree_account_id, COALESCE(SUM(account_entries.debit),0) as total_debit, COALESCE(SUM(account_entries.credit),0) as total_credit')
            ->groupBy('account_entries.tree_account_id');
        $this->applyMovementPeriodFilter($movementQuery, $effectiveDateExpr, $dateFrom, $dateTo);
        if ($entryAccountScope !== null) {
            $movementQuery->whereIn('account_entries.tree_account_id', $entryAccountScope);
        }
        $movementRows = $movementQuery->get()->keyBy('tree_account_id');

        $openingMemo = [];
        $movementMemo = [];
        $trialBalance = [];

        foreach ($accounts as $account) {
            if ($rollupSubtree) {
                [$openingDebit, $openingCredit] = $this->sumAccountSubtreeTotals(
                    (int) $account->id,
                    $childrenMap,
                    $openingRows,
                    $openingMemo
                );
                [$movementDebit, $movementCredit] = $this->sumAccountSubtreeTotals(
                    (int) $account->id,
                    $childrenMap,
                    $movementRows,
                    $movementMemo
                );
            } else {
                $openingRow = $openingRows->get($account->id);
                $openingDebit = (float) ($openingRow?->total_debit ?? 0);
                $openingCredit = (float) ($openingRow?->total_credit ?? 0);

                $movRow = $movementRows->get($account->id);
                $movementDebit = (float) ($movRow->total_debit ?? 0);
                $movementCredit = (float) ($movRow->total_credit ?? 0);
            }

            $openingBalance = $openingDebit - $openingCredit;
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
                'rollup_subtree' => $rollupSubtree,
            ],
        ], 200);
    }

    /**
     * خريطة parent_id => [child_id, ...] لكل شجرة الحسابات.
     *
     * @return array<int, list<int>>
     */
    private function buildTreeAccountChildrenMap(): array
    {
        $map = [];
        $rows = TreeAccount::query()->get(['id', 'parent_id']);
        foreach ($rows as $row) {
            if ($row->parent_id) {
                $map[(int) $row->parent_id][] = (int) $row->id;
            }
        }

        return $map;
    }

    /**
     * مجموع مدين/دائن الحساب + كل الأبناء (مع memo).
     *
     * @param  array<int, list<int>>  $childrenMap
     * @param  \Illuminate\Support\Collection<int|string, object>  $rowsByAccountId
     * @param  array<int, array{0: float, 1: float}>  $memo
     * @return array{0: float, 1: float}
     */
    private function sumAccountSubtreeTotals(
        int $accountId,
        array $childrenMap,
        $rowsByAccountId,
        array &$memo
    ): array {
        if (isset($memo[$accountId])) {
            return $memo[$accountId];
        }

        // منع الدوران في الشجرة (parent_id دائري) من الدخول في تكرار لا نهائي.
        $memo[$accountId] = [0.0, 0.0];

        $row = $rowsByAccountId->get($accountId);
        $debit = (float) ($row->total_debit ?? 0);
        $credit = (float) ($row->total_credit ?? 0);

        foreach ($childrenMap[$accountId] ?? [] as $childId) {
            [$childDebit, $childCredit] = $this->sumAccountSubtreeTotals(
                (int) $childId,
                $childrenMap,
                $rowsByAccountId,
                $memo
            );
            $debit += $childDebit;
            $credit += $childCredit;
        }

        return $memo[$accountId] = [$debit, $credit];
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
        $accounts = app(TreeAccountRepositoryInterface::class)->getNestedTree();

        return response()->json(
            TreeAccountResource::collection($accounts),
            200
        );
    }
    /**
     * Account Statement Report
     * يشمل الحساب المختار وجميع الحسابات التابعة له في الشجرة (كشف مجمّع للحسابات الرئيسية).
     */
    public function accountStatement(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:tree_accounts,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $accountId = (int) $request->input('account_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $userId = $request->filled('user_id') ? (int) $request->user_id : null;

        // Soft-deleted accounts still exist in tree_accounts (exists validation passes) but
        // TreeAccount::find() excludes them — keep statements available for historical review.
        $account = TreeAccount::withTrashed()->find($accountId);
        if (! $account) {
            return response()->json(['message' => 'الحساب غير موجود'], 404);
        }

        $scopeAccountIds = $this->descendantTreeAccountIdsIncludingSelf($accountId);
        $consolidated = count($scopeAccountIds) > 1;

        // 1. Calculate Opening Balance
        $openingBalance = 0;

        // Define balance type multiplier based on account type
        // Asset/Expense: Debit is positive (+), Credit is negative (-)
        // Liability/Income/Equity: Credit is positive (+), Debit is negative (-)
        $isDebitNature = in_array($account->type, ['asset', 'expense']);

        $effectiveDateExpr = 'DATE(COALESCE(de.date, v.date, account_entries.created_at))';

        if ($dateFrom) {
            $openingQuery = AccountEntry::query()
                ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
                ->leftJoin('vouchers as v', 'account_entries.voucher_id', '=', 'v.id')
                ->whereIn('account_entries.tree_account_id', $scopeAccountIds);
            $this->applyOpeningPeriodFilter($openingQuery, $effectiveDateExpr, $dateFrom);

            if ($userId !== null) {
                $this->applyAccountEntryUserFilter($openingQuery, $userId);
            }

            $totalDebit = (clone $openingQuery)->sum(DB::raw('account_entries.debit'));
            $totalCredit = (clone $openingQuery)->sum(DB::raw('account_entries.credit'));

            if ($isDebitNature) {
                $openingBalance = $totalDebit - $totalCredit;
            } else {
                $openingBalance = $totalCredit - $totalDebit;
            }
        }

        // 2. Fetch Entries (filter & sort by accounting date, not only system created_at)
        $query = AccountEntry::with([
                'voucher.user:id,name',
                'dailyEntry.user:id,name',
                'account',
            ])
            ->select('account_entries.*')
            ->whereIn('account_entries.tree_account_id', $scopeAccountIds)
            ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
            ->leftJoin('vouchers as v', 'account_entries.voucher_id', '=', 'v.id');

        $this->applyMovementPeriodFilter($query, $effectiveDateExpr, $dateFrom, $dateTo);

        if ($userId !== null) {
            $this->applyAccountEntryUserFilter($query, $userId);
        }

        $entries = $query->orderByRaw("{$effectiveDateExpr} ASC")
            ->orderBy('account_entries.created_at', 'asc')
            ->orderBy('account_entries.id', 'asc')
            ->get();

        $openingImportEntries = collect();
        if ($dateFrom) {
            $openingImportQuery = AccountEntry::with([
                    'voucher.user:id,name',
                    'dailyEntry.user:id,name',
                    'account',
                ])
                ->select('account_entries.*')
                ->whereIn('account_entries.tree_account_id', $scopeAccountIds)
                ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
                ->leftJoin('vouchers as v', 'account_entries.voucher_id', '=', 'v.id')
                ->where('account_entries.entry_batch_code', 'like', 'OPENING-IMPORT-%');
            $this->applyOpeningPeriodFilter($openingImportQuery, $effectiveDateExpr, $dateFrom);
            if ($userId !== null) {
                $this->applyAccountEntryUserFilter($openingImportQuery, $userId);
            }
            $openingImportEntries = $openingImportQuery
                ->orderByRaw("{$effectiveDateExpr} ASC")
                ->orderBy('account_entries.id', 'asc')
                ->get();
        }

        $displayEntries = $openingImportEntries->concat($entries)->values();

        $this->attachPerformedByUserNames($displayEntries);

        $forAdmin = $this->userCanEditAccountStatementEntries();
        app(AccountEntryEditLinkService::class)->attachEditLinks($displayEntries, $forAdmin);

        $openingImportIds = $openingImportEntries->pluck('id')->all();

        // 3. Calculate Running Balance — سطور الاستيراد توضيحية فقط (مضمّنة في الافتتاحي)
        $runningBalance = $openingBalance;
        $processedEntries = $displayEntries->map(function ($entry) use (&$runningBalance, $isDebitNature, $openingImportIds) {
            $entry->setAttribute(
                'entry_date',
                $entry->dailyEntry?->date ?? $entry->voucher?->date ?? $entry->created_at
            );
            $entry->setAttribute(
                'posted_at',
                $entry->dailyEntry?->created_at
                    ?? $entry->voucher?->created_at
                    ?? $entry->created_at
            );

            $isOpeningImport = in_array((int) $entry->id, $openingImportIds, true);
            $entry->setAttribute('is_opening_import', $isOpeningImport);

            if (! $isOpeningImport) {
                if ($isDebitNature) {
                    $change = $entry->debit - $entry->credit;
                } else {
                    $change = $entry->credit - $entry->debit;
                }
                $runningBalance += $change;
            }

            $entry->running_balance = $runningBalance;

            return $entry;
        });

        return response()->json([
            'account' => $account,
            'consolidated' => $consolidated,
            'accounts_in_scope' => count($scopeAccountIds),
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'entries' => $processedEntries,
            'total_debit' => $entries->sum('debit'),
            'total_credit' => $entries->sum('credit'),
        ], 200);
    }

    /**
     * أول المدة: كل ما قبل تاريخ البداية،
     * بالإضافة إلى قيد استيراد الرصيد الافتتاحي المؤرخ بنفس يوم البداية
     * حتى يظهر كرصيد أول المدة عند عرض الميزان/الكشف من تاريخ الاستيراد.
     */
    private function applyOpeningPeriodFilter($query, string $effectiveDateExpr, string $dateFrom): void
    {
        $query->where(function ($q) use ($effectiveDateExpr, $dateFrom) {
            $q->whereRaw("{$effectiveDateExpr} < ?", [$dateFrom])
                ->orWhere(function ($q2) use ($effectiveDateExpr, $dateFrom) {
                    $q2->where('account_entries.entry_batch_code', 'like', 'OPENING-IMPORT-%')
                        ->whereRaw("{$effectiveDateExpr} = ?", [$dateFrom]);
                });
        });
    }

    /**
     * حركة الفترة: من تاريخ البداية إلى النهاية، مع استثناء استيراد أول المدة
     * المؤرخ بنفس يوم البداية لأنه يُحسب في عمود الافتتاح.
     */
    private function applyMovementPeriodFilter($query, string $effectiveDateExpr, ?string $dateFrom, ?string $dateTo): void
    {
        if ($dateFrom) {
            $query->whereRaw("{$effectiveDateExpr} >= ?", [$dateFrom])
                ->whereRaw(
                    'NOT (COALESCE(account_entries.entry_batch_code, \'\') LIKE ? AND '.$effectiveDateExpr.' = ?)',
                    ['OPENING-IMPORT-%', $dateFrom]
                );
        }
        if ($dateTo) {
            $query->whereRaw("{$effectiveDateExpr} <= ?", [$dateTo]);
        }
    }

    private function applyAccountEntryUserFilter($query, int $userId): void
    {
        $opsPrefix = BankOperationalLedgerService::BATCH_PREFIX;

        $query->where(function ($q) use ($userId, $opsPrefix) {
            $q->where('de.user_id', $userId)
                ->orWhere('v.user_id', $userId)
                ->orWhereIn('account_entries.entry_batch_code', function ($sub) use ($userId) {
                    $sub->select('entry_batch_code')
                        ->from('bank_transactions')
                        ->where('user_id', $userId)
                        ->whereNotNull('entry_batch_code');
                })
                ->orWhereIn('account_entries.entry_batch_code', function ($sub) use ($userId) {
                    $sub->select('entry_batch_code')
                        ->from('safe_transactions')
                        ->where('user_id', $userId)
                        ->whereNotNull('entry_batch_code');
                })
                ->orWhere(function ($q2) use ($userId, $opsPrefix) {
                    $q2->where('account_entries.entry_batch_code', 'like', $opsPrefix . '%')
                        ->whereExists(function ($exists) use ($userId, $opsPrefix) {
                            $exists->select(DB::raw(1))
                                ->from('bank_details')
                                ->where('bank_details.user_id', $userId)
                                ->whereRaw(
                                    'account_entries.entry_batch_code LIKE CONCAT(?, \'%-\', bank_details.ref)',
                                    [$opsPrefix]
                                );
                        });
                });
        });
    }

    private function userCanEditAccountStatementEntries(): bool
    {
        $user = auth()->user();
        if ($user && trim((string) ($user->department ?? '')) === 'Admin') {
            return true;
        }

        return has_any_permission([
            'finance.account_statement.edit',
            'system.rbac',
        ]);
    }

    /**
     * يضيف user_name لكل قيد من السند/القيد اليومي أو حركات البنك/الخزنة المرتبطة بـ entry_batch_code.
     *
     * @param  \Illuminate\Support\Collection<int, AccountEntry>  $entries
     */
    private function attachPerformedByUserNames($entries): void
    {
        $batchCodes = $entries->pluck('entry_batch_code')->filter()->unique()->values()->all();
        $userByBatch = [];

        if ($batchCodes !== []) {
            $bankRows = DB::table('bank_transactions')
                ->join('users', 'bank_transactions.user_id', '=', 'users.id')
                ->whereIn('bank_transactions.entry_batch_code', $batchCodes)
                ->select('bank_transactions.entry_batch_code', 'users.name')
                ->get();

            foreach ($bankRows as $row) {
                $userByBatch[$row->entry_batch_code] = $row->name;
            }

            $safeRows = DB::table('safe_transactions')
                ->join('users', 'safe_transactions.user_id', '=', 'users.id')
                ->whereIn('safe_transactions.entry_batch_code', $batchCodes)
                ->select('safe_transactions.entry_batch_code', 'users.name')
                ->get();

            foreach ($safeRows as $row) {
                $userByBatch[$row->entry_batch_code] = $row->name;
            }

            $opsPrefix = BankOperationalLedgerService::BATCH_PREFIX;
            $opsRefs = [];
            foreach ($batchCodes as $code) {
                if (! str_starts_with((string) $code, $opsPrefix)) {
                    continue;
                }
                $suffix = substr((string) $code, strlen($opsPrefix));
                $parts = explode('-', $suffix);
                if ($parts === []) {
                    continue;
                }
                $opsRefs[(string) $code] = end($parts);
            }

            if ($opsRefs !== []) {
                $detailRows = DB::table('bank_details')
                    ->join('users', 'bank_details.user_id', '=', 'users.id')
                    ->whereIn('bank_details.ref', array_values(array_unique(array_values($opsRefs))))
                    ->select('bank_details.ref', 'users.name')
                    ->get()
                    ->keyBy('ref');

                foreach ($opsRefs as $code => $ref) {
                    $detail = $detailRows->get($ref);
                    if ($detail) {
                        $userByBatch[$code] = $detail->name;
                    }
                }
            }
        }

        foreach ($entries as $entry) {
            $name = $entry->voucher?->user?->name
                ?? $entry->dailyEntry?->user?->name
                ?? ($entry->entry_batch_code ? ($userByBatch[$entry->entry_batch_code] ?? null) : null);

            $entry->setAttribute('user_name', $name);
        }
    }

    /**
     * معرفات الحساب المحدد وجميع الحسابات التابعة له (BFS على شجرة parent_id).
     *
     * @return int[]
     */
    private function descendantTreeAccountIdsIncludingSelf(int $rootId): array
    {
        $byParent = [];
        // Include soft-deleted children so consolidated statements still cover deleted subtrees.
        foreach (TreeAccount::withTrashed()->select('id', 'parent_id')->get() as $row) {
            $p = $row->parent_id;
            if (! isset($byParent[$p])) {
                $byParent[$p] = [];
            }
            $byParent[$p][] = (int) $row->id;
        }

        $queue = [$rootId];
        $out = [];
        for ($i = 0; $i < count($queue); $i++) {
            $id = $queue[$i];
            $out[] = $id;
            foreach ($byParent[$id] ?? [] as $cid) {
                $queue[] = $cid;
            }
        }

        return $out;
    }

    /**
     * Income Statement (قائمة الدخل)
     * Multi-step format per GAAP/IFRS best practices
     * Computes: Revenue (مبيعات + إيراد شحن محصل من العميل إن وُجد) → COGS → Gross Profit → Operating Expenses
     *         (يشمل مصروف شحن صادر/توزيع منفصل عن مصروفات المشتريات) → Operating Income
     *         → Other Income/Expenses → EBIT → Interest → EBT → Tax → Net Income
     * Inputs: month=YYYY-MM or date_from/date_to
     * Uses leaf accounts plus any parent that has direct postings when no child account has
     * entries in the same period (so revenue posted on a parent "مبيعات" header is included).
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

        $entryRows = AccountEntry::select('tree_account_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
            ->when(true, $withinPeriod)
            ->groupBy('tree_account_id')
            ->get();

        $idsWithEntries = $entryRows->pluck('tree_account_id');

        $accountsWithChildren = TreeAccount::with('children:id,parent_id')
            ->whereIn('id', $idsWithEntries)
            ->get()
            ->keyBy('id');

        $eligibleIds = [];
        foreach ($idsWithEntries as $aid) {
            $acc = $accountsWithChildren->get($aid);
            if (!$acc) {
                continue;
            }
            if ($acc->children->isEmpty()) {
                $eligibleIds[] = (int) $aid;
                continue;
            }
            if ($acc->children->pluck('id')->intersect($idsWithEntries)->isEmpty()) {
                $eligibleIds[] = (int) $aid;
            }
        }

        $accounts = TreeAccount::select('id', 'name', 'name_en', 'code', 'type', 'detail_type')
            ->whereIn('id', $eligibleIds)
            ->get()
            ->keyBy('id');

        $eligibleSet = array_fill_keys($eligibleIds, true);
        $entrySums = $entryRows->filter(function ($row) use ($eligibleSet) {
            return isset($eligibleSet[(int) $row->tree_account_id]);
        })->mapWithKeys(function ($row) {
            return [$row->tree_account_id => [
                'debit' => (float) $row->total_debit,
                'credit' => (float) $row->total_credit,
            ]];
        });

        $netByNature = function ($acc, $sum) {
            if (!$sum) return 0.0;
            $type = $acc->type ?? null;
            if (in_array($type, ['revenue', 'income', 'liability', 'equity', 'settlement'])) {
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
        $shippingRevenue = 0.0;
        $salesReturns = 0.0;
        $cogs = 0.0;
        $operatingExpenses = 0.0;
        $salesExpenses = 0.0;
        $freightOut = 0.0;
        $adminExpenses = 0.0;
        $purchaseExpenses = 0.0;
        $freightInExpense = 0.0;
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
                } elseif ($detail === 'shipping_revenue'
                    || $hasKeyword($name, [
                        'إيراد شحن', 'ايراد شحن',
                        'إيرادات الشحن', 'ايرادات الشحن',
                        'إيرادات شحن', 'ايرادات شحن',
                        'شحن محصل', 'شحن للعميل',
                        'shipping revenue', 'delivery revenue', 'handling revenue',
                    ])) {
                    /** إيراد شحن يُحصّل من العميل — منفصل عن مبيعات البضاعة (IFRS/GAAP: freight billed to customers). */
                    $shippingRevenue += $net;
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
                } elseif ($detail === 'freight_out'
                    || $hasKeyword($name, ['شحن صادر', 'مصروف شحن بيع', 'توصيل مبيعات', 'freight out', 'outbound freight', 'delivery expense'])) {
                    /** تكلفة الشحن للناقل/العميل النهائي — مصروف بيع/توزيع، لا تُخلط مع شحن المشتريات. */
                    $freightOut += $net;
                } elseif ($detail === 'sales_expense' || $hasKeyword($name, ['مصاريف مبيعات', 'مصروف مبيعات'])) {
                    $salesExpenses += $net;
                } elseif ($detail === 'admin' || $hasKeyword($name, ['عمومية', 'إدارية', 'إداري', 'general & admin', 'g&a'])) {
                    $adminExpenses += $net;
                } elseif ($detail === 'freight_in' || $hasKeyword($name, ['شحن مشتريات', 'freight in', 'purchase freight', 'شحن توريد'])) {
                    $freightInExpense += $net;
                } elseif ($detail === 'purchase_expense' || $hasKeyword($name, ['مصروف مشتريات', 'توريد'])) {
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

        $cogsFromLedger = $cogs;
        $cogsFromMovements = $this->netCogsFromCategoriesBalanceMovements($start, $end);
        if ($cogsFromLedger < 0.01) {
            $cogs = max(0, $cogsFromMovements);
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
        $totalRevenue = $netSales + $shippingRevenue;
        /** مجمل الربح = إجمالي الإيرادات التشغيلية (بما فيها شحن محصل) − تكلفة البضاعة المباعة. */
        $grossProfit = $totalRevenue - $cogs;
        $operatingExpensesTotal = $operatingExpenses + $salesExpenses + $freightOut + $adminExpenses + $depreciation + $purchaseExpenses + $freightInExpense;
        $operatingIncome = $grossProfit - $operatingExpensesTotal;
        $otherIncomeTotal = $capitalGains + $otherRevenues + $interestIncome;
        $otherExpensesTotal = $interestExpense;
        $earningsBeforeTax = $operatingIncome + $otherIncomeTotal - $otherExpensesTotal;
        $netProfitAfterTax = $earningsBeforeTax - $taxExpense;

        $revenueForMargins = abs($totalRevenue) >= 0.0001 ? $totalRevenue : $netSales;
        $grossMarginPercent = $revenueForMargins != 0 ? round(($grossProfit / $revenueForMargins) * 100, 2) : 0;
        $operatingMarginPercent = $revenueForMargins != 0 ? round(($operatingIncome / $revenueForMargins) * 100, 2) : 0;
        $netMarginPercent = $revenueForMargins != 0 ? round(($netProfitAfterTax / $revenueForMargins) * 100, 2) : 0;

        return response()->json([
            'date_from' => $start,
            'date_to' => $end,
            // Revenue section
            'sales' => round($sales, 2),
            'shipping_revenue' => round($shippingRevenue, 2),
            'sales_returns' => round($salesReturns, 2),
            'net_sales' => round($netSales, 2),
            'total_revenue' => round($totalRevenue, 2),
            // Cost of sales
            'opening_inventory' => round($openingInventory, 2),
            'closing_inventory' => round($closingInventory, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin_percent' => $grossMarginPercent,
            // Operating expenses
            'operating_expenses' => round($operatingExpenses, 2),
            'sales_expenses' => round($salesExpenses, 2),
            'freight_out_expense' => round($freightOut, 2),
            'admin_expenses' => round($adminExpenses, 2),
            'purchase_expenses' => round($purchaseExpenses, 2),
            'freight_in_expense' => round($freightInExpense, 2),
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
        $data = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);
        if (! empty($data['date_from']) && ! empty($data['date_to']) && $data['date_to'] < $data['date_from']) {
            return response()->json([
                'message' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية.',
                'errors' => ['date_to' => ['بعد أو يساوي من تاريخ']],
            ], 422);
        }

        $start = $request->date_from ?: date('Y-m-01');
        $end = $request->date_to ?: date('Y-m-d');

        $result = $this->productPerformanceReportService->computeForPeriod($start, $end);
        unset($result['by_category_id']);

        return response()->json($result, 200);
    }

    /**
     * Category Profitability Report (تقرير ربحية الصنف)
     * Same data as product-performance with category type, measurement unit, orders count
     */
    public function categoryProfitability(Request $request)
    {
        $data = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);
        if (! empty($data['date_from']) && ! empty($data['date_to']) && $data['date_to'] < $data['date_from']) {
            return response()->json([
                'message' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية.',
                'errors' => ['date_to' => ['بعد أو يساوي من تاريخ']],
            ], 422);
        }

        $start = $request->date_from ?: date('Y-m-01');
        $end = $request->date_to ?: date('Y-m-d');
        $data = $this->productPerformanceReportService->computeForPeriod($start, $end);
        if (! isset($data['data'])) {
            return response()->json($data, 200);
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
                'avg_cost' => $row['avg_unit_cost'] ?? $avgCost,
                'ref_unit_cost' => $row['ref_unit_cost'] ?? null,
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

    /**
     * Net COGS from categories_balance (same logic as product performance) when GL has no COGS lines.
     */
    private function netCogsFromCategoriesBalanceMovements(string $start, string $end): float
    {
        $wrAvgSubQuery = function () {
            return DB::table('warehouse_ratings')
                ->select('category_id', DB::raw('SUM(quantity * price) / NULLIF(SUM(quantity), 0) as wr_avg'))
                ->where('quantity', '>', 0)
                ->groupBy('category_id');
        };

        $costCase = 'CASE WHEN c.quantity > 0.0000001 AND IFNULL(c.total_price,0) != 0 THEN c.total_price / c.quantity WHEN IFNULL(c.unit_price,0) > 0.0000001 THEN c.unit_price WHEN IFNULL(wr.wr_avg,0) > 0.0000001 THEN wr.wr_avg ELSE 0 END';

        $shipCogsRows = DB::table('categories_balance as cb')
            ->join('categories as c', 'c.id', '=', 'cb.category_id')
            ->leftJoinSub($wrAvgSubQuery(), 'wr', function ($join) {
                $join->on('wr.category_id', '=', 'c.id');
            })
            ->where('cb.type', 'شحن طلب')
            ->where('cb.created_at', '>=', $start . ' 00:00:00')
            ->where('cb.created_at', '<=', $end . ' 23:59:59')
            ->groupBy('cb.category_id')
            ->select('cb.category_id', DB::raw("SUM(COALESCE(cb.cost_total, cb.quantity * ({$costCase}))) as cogs"))
            ->get()
            ->keyBy('category_id');

        $retCogsRows = DB::table('categories_balance as cb')
            ->join('categories as c', 'c.id', '=', 'cb.category_id')
            ->leftJoinSub($wrAvgSubQuery(), 'wr', function ($join) {
                $join->on('wr.category_id', '=', 'c.id');
            })
            ->where('cb.type', 'رفض استلام طلب')
            ->where('cb.created_at', '>=', $start . ' 00:00:00')
            ->where('cb.created_at', '<=', $end . ' 23:59:59')
            ->groupBy('cb.category_id')
            ->select('cb.category_id', DB::raw("SUM(COALESCE(cb.cost_total, cb.quantity * ({$costCase}))) as cogs"))
            ->get()
            ->keyBy('category_id');

        $movementKeys = array_unique(array_merge(
            array_keys($shipCogsRows->toArray()),
            array_keys($retCogsRows->toArray())
        ));

        $total = 0.0;
        foreach ($movementKeys as $cid) {
            $cid = (int) $cid;
            $ship = (float) (optional($shipCogsRows->get($cid))->cogs ?? 0);
            $ret = (float) (optional($retCogsRows->get($cid))->cogs ?? 0);
            $total += $ship - $ret;
        }

        return $total;
    }
}
