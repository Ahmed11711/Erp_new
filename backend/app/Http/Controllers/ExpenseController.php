<?php

namespace App\Http\Controllers;
use App\Models\Bank;
use App\Models\Safe;
use App\Models\Expense;
use App\Models\ExpenseLine;
use App\Models\ExpenseKind;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Services\Expenses\ExpensePurgeService;
use App\Services\Accounting\ExpenseDebitOperationalSyncService;
use Illuminate\Http\Request;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $itemsPerPage = (int) ($request->input('itemsPerPage') ?: 50);

        $paginator = Expense::with([
            'kind.treeAccount',
            'treeAccount',
            'lines.kind.treeAccount',
            'lines.treeAccount',
            'bank.asset',
            'safe.account',
            'serviceAccount.account',
            'user:id,name',
        ])
            ->orderByDesc('id')
            ->paginate($itemsPerPage);

        $this->hydrateExpenseLedgerContext($paginator->getCollection());

        return response()->json($paginator);
    }

    public function store(Request $request)
    {
        $lines = $this->parseExpenseLinesInput($request);
        $hasSplitLines = count($lines) > 0;
        if ($hasSplitLines) {
            // FormData يرسل lines كسلسلة JSON — ندمجها كمصفوفة قبل التحقق
            $request->merge(['lines' => $lines]);
        }

        $rules = [
            'payment_type' => 'nullable|in:safe,bank,service_account',
            'bank_id' => 'nullable|exists:banks,id',
            'safe_id' => 'nullable|exists:safes,id',
            'service_account_id' => 'nullable|exists:service_accounts,id',
            'expens_statement' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'required|string',
            'address' => 'required|string',
        ];

        if ($hasSplitLines) {
            $rules['lines'] = 'required|array|min:1';
            $rules['lines.*.expense_type'] = 'required|in:مصروف ادارى,مصروف تسويق,مصروف تشغيل';
            $rules['lines.*.tree_account_id'] = 'required|integer|exists:tree_accounts,id';
            $rules['lines.*.amount'] = 'required|numeric|min:0.01';
            $rules['lines.*.statement'] = 'required|string|max:500';
        } else {
            $rules['expense_type'] = 'required|in:مصروف ادارى,مصروف تسويق,مصروف تشغيل';
            $rules['tree_account_id'] = 'required|integer|exists:tree_accounts,id';
        }

        $request->validate($rules);

        $paymentType = $request->payment_type ?? 'bank';
        $bankId = $request->bank_id;
        $safeId = $request->safe_id;
        $serviceAccountId = $request->service_account_id;

        if ($paymentType === 'bank' && !$bankId) {
            return response()->json(['message' => 'يجب اختيار البنك'], 422);
        }
        if ($paymentType === 'safe' && !$safeId) {
            return response()->json(['message' => 'يجب اختيار الخزينة'], 422);
        }
        if ($paymentType === 'service_account' && !$serviceAccountId) {
            return response()->json(['message' => 'يجب اختيار الحساب الخدمي'], 422);
        }

        $amount = round((float) $request->amount, 2);

        if ($hasSplitLines) {
            $linesTotal = round(array_sum(array_map(fn ($l) => (float) $l['amount'], $lines)), 2);
            if (abs($linesTotal - $amount) > 0.009) {
                return response()->json([
                    'message' => 'مجموع بنود التقسيم (' . $linesTotal . ') يجب أن يساوي المبلغ الإجمالي (' . $amount . ')',
                ], 422);
            }
            try {
                foreach ($lines as $lineRow) {
                    $this->resolveLeafTreeAccount((int) $lineRow['tree_account_id']);
                }
            } catch (\Exception $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        } else {
            try {
                $this->resolveLeafTreeAccount((int) $request->tree_account_id);
            } catch (\Exception $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $img_name = '';
        if ($request->hasFile('expense_image')) {
            $img = $request->file('expense_image');
            $img_name = time() . '.' . $img->extension();
            $img->move(public_path('images'), $img_name);
        }

        $headerType = $hasSplitLines ? (string) $lines[0]['expense_type'] : (string) $request->expense_type;
        $headerTreeAccountId = $hasSplitLines
            ? (int) $lines[0]['tree_account_id']
            : (int) $request->tree_account_id;

        DB::beginTransaction();
        try {
            $expense = Expense::create([
                'expense_type' => $headerType,
                'payment_type' => $paymentType,
                'bank_id' => $paymentType === 'bank' ? $bankId : null,
                'safe_id' => $paymentType === 'safe' ? $safeId : null,
                'service_account_id' => $paymentType === 'service_account' ? $serviceAccountId : null,
                'user_id' => auth()->user()->id,
                'kind_id' => null,
                'tree_account_id' => $headerTreeAccountId,
                'expens_statement' => request('expens_statement'),
                'amount' => $amount,
                'note' => request('note'),
                'address' => request('address'),
                'created_at' => $request->created_at,
                'expense_image' => $img_name,
            ]);

            $debitPostings = [];
            $bankDetailLabel = $expense->expense_type;
            $entryDate = $request->created_at ?? date('Y-m-d');

            if ($hasSplitLines) {
                foreach ($lines as $idx => $lineRow) {
                    $debitTree = $this->resolveLeafTreeAccount((int) $lineRow['tree_account_id']);
                    ExpenseLine::create([
                        'expense_id' => $expense->id,
                        'expense_type' => $lineRow['expense_type'],
                        'kind_id' => null,
                        'tree_account_id' => $debitTree->id,
                        'amount' => $lineRow['amount'],
                        'statement' => $lineRow['statement'] ?? null,
                        'sort_order' => $idx,
                    ]);
                    $debitPostings[] = [
                        'tree_account_id' => $debitTree->id,
                        'amount' => (float) $lineRow['amount'],
                        'label' => $debitTree->name,
                        'statement' => $lineRow['statement'] ?? '',
                    ];
                }
                $bankDetailLabel = 'مصروف مقسّم (' . count($lines) . ' بنود)';
            } else {
                $debitTree = $this->resolveLeafTreeAccount($headerTreeAccountId);
                ExpenseLine::create([
                    'expense_id' => $expense->id,
                    'expense_type' => $headerType,
                    'kind_id' => null,
                    'tree_account_id' => $debitTree->id,
                    'amount' => $amount,
                    'statement' => request('expens_statement'),
                    'sort_order' => 0,
                ]);
                $debitPostings[] = [
                    'tree_account_id' => $debitTree->id,
                    'amount' => $amount,
                    'label' => $debitTree->name,
                    'statement' => (string) request('expens_statement'),
                ];
                $bankDetailLabel = $expense->expense_type . ' - ' . $debitTree->name;
            }

            $creditTreeId = null;
            $sourceName = '';

            if ($paymentType === 'safe') {
                $safe = Safe::find($safeId);
                if (!$safe || !$safe->account_id) {
                    throw new \Exception('الخزينة غير مرتبطة بحساب في شجرة الحسابات');
                }
                $creditTreeId = $safe->account_id;
                $sourceName = $safe->name;
                $safe->decrement('balance', $amount);
            } elseif ($paymentType === 'service_account') {
                $svc = ServiceAccount::find($serviceAccountId);
                if (!$svc || !$svc->account_id) {
                    throw new \Exception('الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات');
                }
                $creditTreeId = $svc->account_id;
                $sourceName = $svc->name;
                $svc->decrement('balance', $amount);
            } else {
                $bank = Bank::find($bankId);
                if (!$bank) {
                    throw new \Exception('البنك غير موجود');
                }
                $balanceBefore = (float) $bank->balance;
                $bank->decrement('balance', $amount);
                DB::table('bank_details')->insert([
                    'bank_id' => $bankId,
                    'details' => $bankDetailLabel,
                    'ref' => $expense->expense_number,
                    'type' => 'المصروفات',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $bank->fresh()->balance,
                    'date' => $request->created_at ?? date('Y-m-d'),
                    'created_at' => now(),
                    'user_id' => auth()->user()->id
                ]);
                if ($bank->asset_id) {
                    $creditTreeId = $bank->asset_id;
                    $sourceName = $bank->name;
                }
            }

            $this->createExpenseAccountingEntry($debitPostings, $amount, $creditTreeId, $sourceName, $expense->expense_number);

            $this->applyExpenseDebitOperationalSync($debitPostings, $expense->expense_number, $entryDate, false);

            DB::commit();

            return response()->json($expense->load(['lines.treeAccount', 'treeAccount', 'kind.treeAccount']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Expense store failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * إنشاء القيد المحاسبي للمصروف (مدين واحد أو عدة مدين) ودائن مصدر الدفع.
     *
     * @param  array<int, array{tree_account_id:int, amount:float, label?:string}>  $debitPostings
     */
    protected function createExpenseAccountingEntry(array $debitPostings, $amount, $creditTreeId, $sourceName, $ref)
    {
        if (! $creditTreeId) {
            Log::error("Expense accounting: creditTreeId is null for ref={$ref}");
            throw new \Exception('مصدر الدفع غير مرتبط بحساب في شجرة الحسابات (مثلاً ربط البنك بحساب أصول أو اختيار خزينة مرتبطة).');
        }

        if ($debitPostings === []) {
            throw new \Exception('لا توجد بنود مدين للمصروف');
        }

        $batchCode = 'EXP-' . now()->format('YmdHis');
        $accountingService = app(\App\Services\Accounting\AccountingService::class);
        $affectedAccountIds = [];

        foreach ($debitPostings as $posting) {
            $lineAmount = (float) $posting['amount'];
            $label = trim((string) ($posting['label'] ?? ''));
            $lineStatement = trim((string) ($posting['statement'] ?? ''));
            if ($lineStatement !== '') {
                $desc = 'مصروف - ' . $lineStatement . ' - ' . $sourceName . ' - ' . $ref;
            } elseif ($label !== '') {
                $desc = 'مصروف - ' . $label . ' - ' . $sourceName . ' - ' . $ref;
            } else {
                $desc = 'مصروف - ' . $sourceName . ' - ' . $ref;
            }

            AccountEntry::create([
                'tree_account_id' => (int) $posting['tree_account_id'],
                'debit' => $lineAmount,
                'credit' => 0,
                'description' => $desc,
                'order_id' => null,
                'entry_batch_code' => $batchCode,
            ]);
            $affectedAccountIds[] = (int) $posting['tree_account_id'];
        }

        AccountEntry::create([
            'tree_account_id' => $creditTreeId,
            'debit' => 0,
            'credit' => $amount,
            'description' => 'صرف مصروف - ' . $ref,
            'order_id' => null,
            'entry_batch_code' => $batchCode,
        ]);

        $affectedAccountIds[] = (int) $creditTreeId;
        foreach (array_unique($affectedAccountIds) as $accountId) {
            $accountingService->updateAccountHierarchyBalances($accountId);
        }
    }

    /**
     * @return array<int, array{expense_type:string, kind_id:int, amount:float}>
     */
    protected function parseExpenseLinesInput(Request $request): array
    {
        $raw = $request->input('lines');
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                return [];
            }
            $raw = $decoded;
        }

        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $lineAmount = round((float) ($row['amount'] ?? 0), 2);
            if ($lineAmount <= 0) {
                continue;
            }
            $normalized[] = [
                'expense_type' => (string) ($row['expense_type'] ?? ''),
                'tree_account_id' => (int) ($row['tree_account_id'] ?? 0),
                'kind_id' => (int) ($row['kind_id'] ?? 0),
                'amount' => $lineAmount,
                'statement' => trim((string) ($row['statement'] ?? '')),
            ];
        }

        return $normalized;
    }

    public function editExpense($id, Request $request)
    {
        $lines = $this->parseExpenseLinesInput($request);
        $hasSplitLines = count($lines) > 0;
        if ($hasSplitLines) {
            $request->merge(['lines' => $lines]);
        }

        $rules = [
            'payment_type' => 'nullable|in:safe,bank,service_account',
            'bank_id' => 'nullable|exists:banks,id',
            'safe_id' => 'nullable|exists:safes,id',
            'service_account_id' => 'nullable|exists:service_accounts,id',
            'expens_statement' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'required|string',
            'address' => 'nullable|string',
            'created_at' => 'nullable|string',
        ];

        if ($hasSplitLines) {
            $rules['lines'] = 'required|array|min:1';
            $rules['lines.*.expense_type'] = 'required|in:مصروف ادارى,مصروف تسويق,مصروف تشغيل';
            $rules['lines.*.tree_account_id'] = 'required|integer|exists:tree_accounts,id';
            $rules['lines.*.amount'] = 'required|numeric|min:0.01';
            $rules['lines.*.statement'] = 'required|string|max:500';
        } else {
            $rules['expense_type'] = 'required|in:مصروف ادارى,مصروف تسويق,مصروف تشغيل';
            $rules['tree_account_id'] = 'required|integer|exists:tree_accounts,id';
        }

        $request->validate($rules);

        $paymentType = $request->payment_type ?? 'bank';
        $bankId = $request->bank_id;
        $safeId = $request->safe_id;
        $serviceAccountId = $request->service_account_id;

        if ($paymentType === 'bank' && ! $bankId) {
            return response()->json(['message' => 'يجب اختيار البنك'], 422);
        }
        if ($paymentType === 'safe' && ! $safeId) {
            return response()->json(['message' => 'يجب اختيار الخزينة'], 422);
        }
        if ($paymentType === 'service_account' && ! $serviceAccountId) {
            return response()->json(['message' => 'يجب اختيار الحساب الخدمي'], 422);
        }

        $amount = round((float) $request->amount, 2);

        if ($hasSplitLines) {
            $linesTotal = round(array_sum(array_map(fn ($l) => (float) $l['amount'], $lines)), 2);
            if (abs($linesTotal - $amount) > 0.009) {
                return response()->json([
                    'message' => 'مجموع بنود التقسيم (' . $linesTotal . ') يجب أن يساوي المبلغ الإجمالي (' . $amount . ')',
                ], 422);
            }
            try {
                foreach ($lines as $lineRow) {
                    $this->resolveLeafTreeAccount((int) $lineRow['tree_account_id']);
                }
            } catch (\Exception $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        } else {
            try {
                $this->resolveLeafTreeAccount((int) $request->tree_account_id);
            } catch (\Exception $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        DB::beginTransaction();
        try {
            $expense = Expense::with('lines.treeAccount')->find($id);
            if (! $expense) {
                throw new \Exception('المصروف غير موجود');
            }
            if ((int) $expense->status === 1 || (float) $expense->amount < 0) {
                throw new \Exception('لا يمكن تعديل هذا المصروف');
            }

            $oldDebitPostings = $this->buildDebitPostingsFromExpense($expense);
            $entryDateOld = $expense->created_at
                ? (is_string($expense->created_at) ? substr($expense->created_at, 0, 10) : $expense->created_at->format('Y-m-d'))
                : date('Y-m-d');
            $this->reverseExpenseDebitOperationalSync($oldDebitPostings, $expense->expense_number, $entryDateOld);

            $affectedAccountIds = $this->reverseExpenseGlEntries($expense->expense_number);
            $this->restoreExpensePaymentSource($expense);

            $imgName = $expense->expense_image;
            if ($request->hasFile('expense_image')) {
                $img = $request->file('expense_image');
                $imgName = time() . '.' . $img->extension();
                $img->move(public_path('images'), $imgName);
            }

            $headerType = $hasSplitLines ? (string) $lines[0]['expense_type'] : (string) $request->expense_type;
            $headerTreeAccountId = $hasSplitLines
                ? (int) $lines[0]['tree_account_id']
                : (int) $request->tree_account_id;
            $entryDate = $request->created_at ?? date('Y-m-d');

            $expense->update([
                'expense_type' => $headerType,
                'payment_type' => $paymentType,
                'bank_id' => $paymentType === 'bank' ? $bankId : null,
                'safe_id' => $paymentType === 'safe' ? $safeId : null,
                'service_account_id' => $paymentType === 'service_account' ? $serviceAccountId : null,
                'kind_id' => null,
                'tree_account_id' => $headerTreeAccountId,
                'expens_statement' => $request->expens_statement,
                'amount' => $amount,
                'note' => $request->note,
                'address' => $request->address ?: $request->expens_statement,
                'expense_image' => $imgName,
                'created_at' => $request->created_at ?: $expense->created_at,
            ]);

            ExpenseLine::where('expense_id', $expense->id)->delete();

            $debitPostings = [];
            $bankDetailLabel = $expense->expense_type;

            if ($hasSplitLines) {
                foreach ($lines as $idx => $lineRow) {
                    $debitTree = $this->resolveLeafTreeAccount((int) $lineRow['tree_account_id']);
                    ExpenseLine::create([
                        'expense_id' => $expense->id,
                        'expense_type' => $lineRow['expense_type'],
                        'kind_id' => null,
                        'tree_account_id' => $debitTree->id,
                        'amount' => $lineRow['amount'],
                        'statement' => $lineRow['statement'] ?? null,
                        'sort_order' => $idx,
                    ]);
                    $debitPostings[] = [
                        'tree_account_id' => $debitTree->id,
                        'amount' => (float) $lineRow['amount'],
                        'label' => $debitTree->name,
                        'statement' => $lineRow['statement'] ?? '',
                    ];
                }
                $bankDetailLabel = 'تعديل مصروف مقسّم (' . count($lines) . ' بنود)';
            } else {
                $debitTree = $this->resolveLeafTreeAccount($headerTreeAccountId);
                ExpenseLine::create([
                    'expense_id' => $expense->id,
                    'expense_type' => $headerType,
                    'kind_id' => null,
                    'tree_account_id' => $debitTree->id,
                    'amount' => $amount,
                    'statement' => $request->expens_statement,
                    'sort_order' => 0,
                ]);
                $debitPostings[] = [
                    'tree_account_id' => $debitTree->id,
                    'amount' => $amount,
                    'label' => $debitTree->name,
                    'statement' => (string) $request->expens_statement,
                ];
                $bankDetailLabel = 'تعديل ' . $expense->expense_type . ' - ' . $debitTree->name;
            }

            [$creditTreeId, $sourceName] = $this->applyExpensePaymentSource(
                $paymentType,
                $bankId,
                $safeId,
                $serviceAccountId,
                $amount,
                $bankDetailLabel,
                $expense->expense_number,
                $entryDate
            );

            $this->createExpenseAccountingEntry(
                $debitPostings,
                $amount,
                $creditTreeId,
                $sourceName,
                $expense->expense_number
            );

            $this->applyExpenseDebitOperationalSync($debitPostings, $expense->expense_number, $entryDate, false);

            $accountingService = app(\App\Services\Accounting\AccountingService::class);
            foreach ($debitPostings as $posting) {
                $affectedAccountIds[] = (int) $posting['tree_account_id'];
            }
            if ($creditTreeId) {
                $affectedAccountIds[] = (int) $creditTreeId;
            }
            foreach (array_unique($affectedAccountIds) as $accountId) {
                $accountingService->updateAccountHierarchyBalances($accountId);
            }

            DB::commit();

            return response()->json(
                $expense->fresh()->load(['lines.treeAccount', 'treeAccount', 'kind.treeAccount', 'bank.asset', 'safe.account', 'serviceAccount.account']),
                200
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Expense edit failed: ' . $e->getMessage());

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * @return array{0:?int,1:string}
     */
    protected function applyExpensePaymentSource(
        string $paymentType,
        $bankId,
        $safeId,
        $serviceAccountId,
        float $amount,
        string $bankDetailLabel,
        string $expenseRef,
        string $entryDate
    ): array {
        $creditTreeId = null;
        $sourceName = '';

        if ($paymentType === 'safe') {
            $safe = Safe::find($safeId);
            if (! $safe || ! $safe->account_id) {
                throw new \Exception('الخزينة غير مرتبطة بحساب في شجرة الحسابات');
            }
            $creditTreeId = $safe->account_id;
            $sourceName = $safe->name;
            $safe->decrement('balance', $amount);
        } elseif ($paymentType === 'service_account') {
            $svc = ServiceAccount::find($serviceAccountId);
            if (! $svc || ! $svc->account_id) {
                throw new \Exception('الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات');
            }
            $creditTreeId = $svc->account_id;
            $sourceName = $svc->name;
            $svc->decrement('balance', $amount);
        } else {
            $bank = Bank::find($bankId);
            if (! $bank) {
                throw new \Exception('البنك غير موجود');
            }
            $balanceBefore = (float) $bank->balance;
            $bank->decrement('balance', $amount);
            DB::table('bank_details')->insert([
                'bank_id' => $bankId,
                'details' => $bankDetailLabel,
                'ref' => $expenseRef,
                'type' => 'المصروفات',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $bank->fresh()->balance,
                'date' => $entryDate,
                'created_at' => now(),
                'user_id' => auth()->user()->id,
            ]);
            if ($bank->asset_id) {
                $creditTreeId = $bank->asset_id;
                $sourceName = $bank->name;
            }
        }

        if (! $creditTreeId) {
            throw new \Exception('مصدر الدفع غير مرتبط بحساب في شجرة الحسابات');
        }

        return [$creditTreeId, $sourceName];
    }

    protected function restoreExpensePaymentSource(Expense $expense): void
    {
        $amount = round((float) $expense->amount, 2);
        if ($amount <= 0) {
            return;
        }

        $paymentType = $expense->payment_type ?? ($expense->safe_id ? 'safe' : ($expense->service_account_id ? 'service_account' : 'bank'));

        if ($paymentType === 'safe' && $expense->safe_id) {
            Safe::whereKey($expense->safe_id)->increment('balance', $amount);

            return;
        }

        if ($paymentType === 'service_account' && $expense->service_account_id) {
            ServiceAccount::whereKey($expense->service_account_id)->increment('balance', $amount);

            return;
        }

        if ($expense->bank_id) {
            $bank = Bank::find($expense->bank_id);
            if (! $bank) {
                return;
            }
            $balanceBefore = (float) $bank->balance;
            $bank->increment('balance', $amount);
            DB::table('bank_details')->insert([
                'bank_id' => $expense->bank_id,
                'details' => 'عكس تعديل مصروف - ' . $expense->expense_number,
                'ref' => $expense->expense_number,
                'type' => 'المصروفات',
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $bank->fresh()->balance,
                'date' => date('Y-m-d'),
                'created_at' => now(),
                'user_id' => auth()->id(),
            ]);
        }
    }

    public function deleteExpense($id, Request $request)
    {
        DB::beginTransaction();
        try {
            $oldExpense = Expense::with('lines.treeAccount', 'treeAccount')->find($id);
            if (!$oldExpense) {
                throw new \Exception('المصروف غير موجود');
            }

            $oldDebitPostings = $this->buildDebitPostingsFromExpense($oldExpense);
            $entryDateOld = $oldExpense->created_at
                ? (is_string($oldExpense->created_at) ? substr($oldExpense->created_at, 0, 10) : $oldExpense->created_at->format('Y-m-d'))
                : date('Y-m-d');
            $this->reverseExpenseDebitOperationalSync($oldDebitPostings, $oldExpense->expense_number, $entryDateOld);

            $affectedAccountIds = $this->reverseExpenseGlEntries($oldExpense->expense_number);

            $oldExpense->ref = $oldExpense->expense_number;
            $oldExpense->status = 1;
            $oldExpense->save();

            $expense = Expense::create([
                'expense_type' => $oldExpense->expense_type,
                'payment_type' => $oldExpense->payment_type ?? 'bank',
                'bank_id' => $oldExpense->bank_id,
                'safe_id' => $oldExpense->safe_id,
                'service_account_id' => $oldExpense->service_account_id,
                'user_id' => auth()->user()->id,
                'kind_id' => $oldExpense->kind_id,
                'tree_account_id' => $oldExpense->tree_account_id,
                'expens_statement' => $oldExpense->expens_statement,
                'amount' => -$oldExpense->amount,
                'note' => $oldExpense->note,
                'address' => $oldExpense->address,
                'ref' => $oldExpense->expense_number,
                'status' => 1,
                'expense_image' => ''
            ]);

            $debitLabel = $this->debitAccountNameForExpense($oldExpense);
            $paid = (double) -$oldExpense->amount;

            if ($oldExpense->bank_id) {
                $bank = Bank::find($oldExpense->bank_id);
                if ($bank) {
                    $balance = (double) $bank->balance;
                    $bank->balance = $balance - $paid;
                    $bank->save();
                    DB::table('bank_details')->insert([
                        'bank_id' => $oldExpense->bank_id,
                        'details' => ' حذف ' . $oldExpense->expense_type . ' - ' . $debitLabel . ' الخاص برقم ' . $oldExpense->expense_number,
                        'ref' => $oldExpense->expense_number,
                        'type' => 'المصروفات',
                        'amount' => $paid,
                        'balance_before' => $balance,
                        'balance_after' => $bank->balance,
                        'date' => date('Y-m-d'),
                        'created_at' => now(),
                        'user_id' => auth()->user()->id
                    ]);
                }
            } elseif ($oldExpense->safe_id) {
                Safe::where('id', $oldExpense->safe_id)->increment('balance', abs($paid));
            } elseif ($oldExpense->service_account_id) {
                ServiceAccount::where('id', $oldExpense->service_account_id)->increment('balance', abs($paid));
            }

            $accountingService = app(\App\Services\Accounting\AccountingService::class);
            foreach ($affectedAccountIds as $accountId) {
                $accountingService->updateAccountHierarchyBalances($accountId);
            }

            DB::commit();
            return response()->json($expense, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Expense delete failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function purgePreview(ExpensePurgeService $purgeService)
    {
        if (! has_permission('expenses.purge_all') && ! has_permission('system.rbac')) {
            return response()->json(['message' => 'ليس لديك صلاحية حذف جميع المصروفات'], 403);
        }

        return response()->json($purgeService->preview(), 200);
    }

    public function purgeAll(Request $request, ExpensePurgeService $purgeService)
    {
        if (! has_permission('expenses.purge_all') && ! has_permission('system.rbac')) {
            return response()->json(['message' => 'ليس لديك صلاحية حذف جميع المصروفات'], 403);
        }

        $request->validate([
            'confirm' => 'required|accepted',
        ]);

        $preview = $purgeService->preview();
        if ($preview['total_expense_count'] === 0) {
            return response()->json(['message' => 'لا توجد مصروفات للحذف'], 422);
        }

        try {
            $counts = $purgeService->purge();

            return response()->json([
                'message' => 'تم حذف جميع المصروفات وقيودها المحاسبية بنجاح',
                'preview' => $preview,
                'counts' => $counts,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Expense purge failed: ' . $e->getMessage());

            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = Expense::query();
        if($request->filled('date_from') && $request->filled('date_to')){
            $search->whereBetween('created_at', [
                $request->input('date_from') . ' 00:00:00',
                $request->input('date_to') . ' 23:59:59',
            ]);
        } elseif ($request->filled('date_from')) {
            $search->whereDate('created_at', '>=', $request->input('date_from'));
        } elseif ($request->filled('date_to')) {
            $search->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $paginator = $search->with([
            'kind.treeAccount',
            'treeAccount',
            'lines.kind',
            'lines.treeAccount',
            'bank.asset',
            'safe.account',
            'serviceAccount.account',
            'user:id,name',
        ])
            ->orderBy('id', 'desc')
            ->paginate($itemsPerPage);

        $this->hydrateExpenseLedgerContext($paginator->getCollection());

        return response()->json($paginator, 200);
    }

    public function show($id)
    {
        $expense = Expense::with([
            'kind.treeAccount',
            'treeAccount',
            'lines.kind.treeAccount',
            'lines.treeAccount',
            'bank.asset',
            'safe.account',
            'serviceAccount.account',
            'user:id,name',
        ])->find($id);

        if (!$expense) {
            return response()->json(['message' => 'not found'], 404);
        }

        $this->hydrateExpenseLedgerContext(collect([$expense]));

        return response()->json($expense, 200);
    }

    /**
     * يربط كل مصروف بحساب المصروف في الشجرة (المدين في القيد) حسب نوع المصروف.
     */
    protected function hydrateExpenseLedgerContext(Collection $expenses): void
    {
        if ($expenses->isEmpty()) {
            return;
        }

        foreach ($expenses as $expense) {
            if ($expense->relationLoaded('lines') && $expense->lines->count() > 0) {
                foreach ($expense->lines as $line) {
                    if ($line->tree_account_id && $line->relationLoaded('treeAccount') && $line->treeAccount) {
                        $line->setRelation('debit_tree_account', $line->treeAccount);
                        continue;
                    }
                    $lineKind = $line->relationLoaded('kind') ? $line->kind : null;
                    $line->setRelation(
                        'debit_tree_account',
                        TreeAccount::resolveExpenseDebitForKind(
                            $lineKind instanceof ExpenseKind ? $lineKind : null,
                            (string) ($line->expense_type ?? $expense->expense_type ?? '')
                        )
                    );
                }
                $expense->setRelation('debit_tree_account', $expense->lines->count() === 1 ? $expense->lines->first()?->debit_tree_account : null);
                $expense->setAttribute('debit_tree_accounts_split', $expense->lines->count() > 1);
                continue;
            }

            if ($expense->tree_account_id) {
                $acc = $expense->relationLoaded('treeAccount') && $expense->treeAccount
                    ? $expense->treeAccount
                    : TreeAccount::query()->find($expense->tree_account_id);
                $expense->setRelation('debit_tree_account', $acc);
                $expense->setAttribute('debit_tree_accounts_split', false);
                continue;
            }

            $kind = $expense->relationLoaded('kind') ? $expense->kind : null;
            $expense->setRelation(
                'debit_tree_account',
                TreeAccount::resolveExpenseDebitForKind(
                    $kind instanceof ExpenseKind ? $kind : null,
                    (string) ($expense->expense_type ?? '')
                )
            );
            $expense->setAttribute('debit_tree_accounts_split', false);
        }
    }

    protected function resolveLeafTreeAccount(int $treeAccountId): TreeAccount
    {
        $account = TreeAccount::query()->find($treeAccountId);
        if (! $account) {
            throw new \Exception('الحساب المختار غير موجود في شجرة الحسابات');
        }
        if ($account->children()->exists()) {
            throw new \Exception('يجب اختيار حساب فرعي (ورقة) وليس حساباً رئيسياً: ' . $account->name);
        }

        return $account;
    }

    /**
     * @return array<int, array{tree_account_id:int, amount:float, label?:string}>
     */
    protected function buildDebitPostingsFromExpense(Expense $expense): array
    {
        $postings = [];
        $lines = $expense->relationLoaded('lines') ? $expense->lines : $expense->lines()->with('treeAccount', 'kind')->get();

        if ($lines->count() > 0) {
            foreach ($lines as $line) {
                if ($line->tree_account_id) {
                    $postings[] = [
                        'tree_account_id' => (int) $line->tree_account_id,
                        'amount' => (float) $line->amount,
                        'label' => $line->treeAccount?->name ?? '',
                        'statement' => (string) ($line->statement ?? ''),
                    ];
                    continue;
                }
                $kind = $line->kind;
                $debitTree = TreeAccount::resolveExpenseDebitForKind(
                    $kind instanceof ExpenseKind ? $kind : null,
                    (string) ($line->expense_type ?? $expense->expense_type ?? '')
                );
                if ($debitTree) {
                    $postings[] = [
                        'tree_account_id' => $debitTree->id,
                        'amount' => (float) $line->amount,
                        'label' => $kind?->expense_kind ?? $debitTree->name,
                        'statement' => (string) ($line->statement ?? ''),
                    ];
                }
            }

            return $postings;
        }

        if ($expense->tree_account_id) {
            $acc = TreeAccount::query()->find($expense->tree_account_id);
            if ($acc) {
                $postings[] = [
                    'tree_account_id' => $acc->id,
                    'amount' => (float) $expense->amount,
                    'label' => $acc->name,
                ];
            }

            return $postings;
        }

        $kind = $expense->kind;
        $debitTree = TreeAccount::resolveExpenseDebitForKind(
            $kind instanceof ExpenseKind ? $kind : null,
            (string) ($expense->expense_type ?? '')
        );
        if ($debitTree) {
            $postings[] = [
                'tree_account_id' => $debitTree->id,
                'amount' => (float) $expense->amount,
                'label' => $kind?->expense_kind ?? $debitTree->name,
            ];
        }

        return $postings;
    }

    protected function debitLineRef(string $expenseRef, int $treeAccountId): string
    {
        return $expenseRef . '-DR-' . $treeAccountId;
    }

    /**
     * @param  array<int, array{tree_account_id:int, amount:float, label?:string}>  $debitPostings
     */
    protected function applyExpenseDebitOperationalSync(
        array $debitPostings,
        string $expenseRef,
        string $entryDate,
        bool $reverse
    ): void {
        $sync = app(ExpenseDebitOperationalSyncService::class);
        foreach ($debitPostings as $posting) {
            $lineRef = $this->debitLineRef($expenseRef, (int) $posting['tree_account_id']);
            $label = trim((string) ($posting['label'] ?? ''));
            $lineStatement = trim((string) ($posting['statement'] ?? ''));
            if ($lineStatement !== '') {
                $details = 'مصروف - ' . $lineStatement . ' - ' . $expenseRef;
            } elseif ($label !== '') {
                $details = 'مصروف - ' . $label . ' - ' . $expenseRef;
            } else {
                $details = 'مصروف - ' . $expenseRef;
            }
            $sync->syncDebitLine(
                (int) $posting['tree_account_id'],
                (float) $posting['amount'],
                $details,
                $lineRef,
                $entryDate,
                $reverse
            );
        }
    }

    /**
     * @param  array<int, array{tree_account_id:int, amount:float, label?:string}>  $debitPostings
     */
    protected function reverseExpenseDebitOperationalSync(
        array $debitPostings,
        string $expenseRef,
        string $entryDate
    ): void {
        $this->applyExpenseDebitOperationalSync($debitPostings, $expenseRef, $entryDate, true);
    }

    protected function debitAccountNameForExpense(Expense $expense): string
    {
        $postings = $this->buildDebitPostingsFromExpense($expense);
        if ($postings === []) {
            return $expense->kind?->expense_kind ?? '—';
        }
        if (count($postings) === 1) {
            return (string) ($postings[0]['label'] ?? '—');
        }

        return 'تقسيم (' . count($postings) . ' بنود)';
    }


    protected function reverseExpenseGlEntries(string $expenseNumber): array
    {
        $oldEntries = AccountEntry::where('entry_batch_code', 'LIKE', 'EXP-%')
            ->where(function ($q) use ($expenseNumber) {
                $q->where('description', 'LIKE', '% - ' . $expenseNumber)
                  ->orWhere('description', 'LIKE', '%- ' . $expenseNumber);
            })
            ->get();

        if ($oldEntries->isEmpty()) {
            Log::info("No GL entries found to reverse for expense {$expenseNumber}");
            return [];
        }

        $reversalBatchCode = 'EXP-REV-' . now()->format('YmdHis');
        $affectedAccountIds = [];

        foreach ($oldEntries as $entry) {
            AccountEntry::create([
                'tree_account_id' => $entry->tree_account_id,
                'debit' => $entry->credit,
                'credit' => $entry->debit,
                'description' => 'عكس - ' . $entry->description,
                'order_id' => null,
                'entry_batch_code' => $reversalBatchCode,
            ]);
            $affectedAccountIds[] = $entry->tree_account_id;
        }

        return array_unique($affectedAccountIds);
    }

}
