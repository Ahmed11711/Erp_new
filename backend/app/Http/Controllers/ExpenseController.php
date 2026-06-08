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
use Illuminate\Http\Request;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ExpenseController extends Controller
{
    public function index()
    {
        $data = Expense::with([
            'kind.treeAccount',
            'lines.kind.treeAccount',
            'bank.asset',
            'safe.account',
            'serviceAccount.account',
        ])->get();
        $this->hydrateExpenseLedgerContext($data);
        return response()->json($data);
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
            $rules['lines.*.kind_id'] = 'required|integer|exists:expense_kinds,id';
            $rules['lines.*.amount'] = 'required|numeric|min:0.01';
        } else {
            $rules['expense_type'] = 'required|in:مصروف ادارى,مصروف تسويق,مصروف تشغيل';
            $rules['kind_id'] = 'required|numeric|exists:expense_kinds,id';
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
        }

        $img_name = '';
        if ($request->hasFile('expense_image')) {
            $img = $request->file('expense_image');
            $img_name = time() . '.' . $img->extension();
            $img->move(public_path('images'), $img_name);
        }

        $headerType = $hasSplitLines ? (string) $lines[0]['expense_type'] : (string) $request->expense_type;
        $headerKindId = $hasSplitLines ? (int) $lines[0]['kind_id'] : (int) $request->kind_id;

        DB::beginTransaction();
        try {
            $expense = Expense::create([
                'expense_type' => $headerType,
                'payment_type' => $paymentType,
                'bank_id' => $paymentType === 'bank' ? $bankId : null,
                'safe_id' => $paymentType === 'safe' ? $safeId : null,
                'service_account_id' => $paymentType === 'service_account' ? $serviceAccountId : null,
                'user_id' => auth()->user()->id,
                'kind_id' => $headerKindId,
                'expens_statement' => request('expens_statement'),
                'amount' => $amount,
                'note' => request('note'),
                'address' => request('address'),
                'created_at' => $request->created_at,
                'expense_image' => $img_name,
            ]);

            $debitPostings = [];
            $bankDetailLabel = $expense->expense_type;

            if ($hasSplitLines) {
                foreach ($lines as $idx => $lineRow) {
                    $kind = ExpenseKind::find($lineRow['kind_id']);
                    if (! $kind) {
                        throw new \Exception('فئة المصروف غير موجودة');
                    }
                    ExpenseLine::create([
                        'expense_id' => $expense->id,
                        'expense_type' => $lineRow['expense_type'],
                        'kind_id' => $lineRow['kind_id'],
                        'amount' => $lineRow['amount'],
                        'sort_order' => $idx,
                    ]);
                    $debitTree = TreeAccount::resolveExpenseDebitForKind($kind, $lineRow['expense_type']);
                    if (! $debitTree) {
                        throw new \Exception('لم يُعثر على حساب مصروف في شجرة الحسابات لنوع: ' . $lineRow['expense_type']);
                    }
                    $debitPostings[] = [
                        'tree_account_id' => $debitTree->id,
                        'amount' => (float) $lineRow['amount'],
                        'label' => $kind->expense_kind,
                    ];
                }
                $bankDetailLabel = 'مصروف مقسّم (' . count($lines) . ' بنود)';
            } else {
                $expenseKind = ExpenseKind::find($expense->kind_id);
                $debitTree = TreeAccount::resolveExpenseDebitForKind($expenseKind, $expense->expense_type);
                if (! $debitTree) {
                    throw new \Exception('لم يُعثر على حساب مصروف في شجرة الحسابات لنوع: ' . $expense->expense_type);
                }
                $debitPostings[] = [
                    'tree_account_id' => $debitTree->id,
                    'amount' => $amount,
                    'label' => $expenseKind->expense_kind ?? $expense->expense_type,
                ];
                $bankDetailLabel = $expense->expense_type . ' - ' . ($expenseKind->expense_kind ?? '');
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

            DB::commit();

            return response()->json($expense->load(['lines.kind.treeAccount', 'kind.treeAccount']), 201);
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
            $desc = $label !== ''
                ? 'مصروف - ' . $label . ' - ' . $sourceName . ' - ' . $ref
                : 'مصروف - ' . $sourceName . ' - ' . $ref;

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
                'kind_id' => (int) ($row['kind_id'] ?? 0),
                'amount' => $lineAmount,
            ];
        }

        return $normalized;
    }

    public function editExpense($id, Request $request)
    {
        $request->validate([
            "expense_type" => "in:مصروف ادارى,مصروف تسويق,مصروف تشغيل",
            'bank_id' => 'nullable|exists:banks,id',
            'kind_id' => 'required|numeric|exists:expense_kinds,id',
            'expens_statement' => 'required|string',
            'amount' => 'required|numeric',
            'note' => 'required|string',
            'address' => 'required|string'
        ]);

        $img_name = '';
        if ($request->hasFile('expense_image')) {
            $img = $request->file('expense_image');
            $img_name = time() . '.' . $img->extension();
            $img->move(public_path('images'), $img_name);
        }

        DB::beginTransaction();
        try {
            $oldExpense = Expense::find($id);
            if (!$oldExpense) {
                throw new \Exception('المصروف غير موجود');
            }

            $affectedAccountIds = $this->reverseExpenseGlEntries($oldExpense->expense_number);

            $oldExpense->ref = $oldExpense->expense_number;
            $oldExpense->status = 0;
            $oldExpense->save();

            $expense = Expense::create([
                'expense_type' => request('expense_type'),
                'bank_id' => request('bank_id'),
                'user_id' => auth()->user()->id,
                'kind_id' => request('kind_id'),
                'expens_statement' => request('expens_statement'),
                'amount' => request('amount'),
                'note' => request('note'),
                'address' => request('address'),
                'ref' => $oldExpense->expense_number,
                'status' => 0,
                'expense_image' => $img_name,
            ]);

            $expenseKind = ExpenseKind::find($expense->kind_id);
            $paid = (double) $request->amount - $oldExpense->amount;

            $creditTreeId = null;
            $sourceName = '';

            if ($request->bank_id) {
                $bank = Bank::find($request->bank_id);
                if ($bank) {
                    $balance = (double) $bank->balance;
                    $bank->balance = $balance - $paid;
                    $bank->save();
                    DB::table('bank_details')->insert([
                        'bank_id' => $request->bank_id,
                        'details' => ' تعديل ' . $expense->expense_type . ' - ' . $expenseKind->expense_kind . ' الخاص برقم ' . $oldExpense->expense_number,
                        'ref' => $expense->expense_number,
                        'type' => 'المصروفات',
                        'amount' => $paid,
                        'balance_before' => $balance,
                        'balance_after' => $bank->balance,
                        'date' => date('Y-m-d'),
                        'created_at' => now(),
                        'user_id' => auth()->user()->id
                    ]);
                    if ($bank->asset_id) {
                        $creditTreeId = $bank->asset_id;
                        $sourceName = $bank->name;
                    }
                }
            }

            $debitTree = TreeAccount::resolveExpenseDebitForKind($expenseKind, $expense->expense_type);
            if (! $debitTree) {
                throw new \Exception('لم يُعثر على حساب مصروف في شجرة الحسابات لنوع: ' . $expense->expense_type);
            }
            $this->createExpenseAccountingEntry(
                [[
                    'tree_account_id' => $debitTree->id,
                    'amount' => (double) $request->amount,
                    'label' => $expenseKind->expense_kind ?? $expense->expense_type,
                ]],
                (double) $request->amount,
                $creditTreeId,
                $sourceName,
                $expense->expense_number
            );

            $accountingService = app(\App\Services\Accounting\AccountingService::class);
            foreach ($affectedAccountIds as $accountId) {
                $accountingService->updateAccountHierarchyBalances($accountId);
            }

            DB::commit();
            return response()->json($expense, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Expense edit failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function deleteExpense($id, Request $request)
    {
        DB::beginTransaction();
        try {
            $oldExpense = Expense::find($id);
            if (!$oldExpense) {
                throw new \Exception('المصروف غير موجود');
            }

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
                'expens_statement' => $oldExpense->expens_statement,
                'amount' => -$oldExpense->amount,
                'note' => $oldExpense->note,
                'address' => $oldExpense->address,
                'ref' => $oldExpense->expense_number,
                'status' => 1,
                'expense_image' => ''
            ]);

            $expenseKind = ExpenseKind::find($oldExpense->kind_id);
            $paid = (double) -$oldExpense->amount;

            if ($oldExpense->bank_id) {
                $bank = Bank::find($oldExpense->bank_id);
                if ($bank) {
                    $balance = (double) $bank->balance;
                    $bank->balance = $balance - $paid;
                    $bank->save();
                    DB::table('bank_details')->insert([
                        'bank_id' => $oldExpense->bank_id,
                        'details' => ' حذف ' . $oldExpense->expense_type . ' - ' . $expenseKind->expense_kind . ' الخاص برقم ' . $oldExpense->expense_number,
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

    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = Expense::query();
        if($request->has('date_to') && $request->has('date_from')){
            $search->whereBetween('created_at', [
                $request->input('date_from'),
                $request->input('date_to')
            ]);
        }

        $paginator = $search->with([
            'kind.treeAccount',
            'lines.kind',
            'bank.asset',
            'safe.account',
            'serviceAccount.account',
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
            'lines.kind.treeAccount',
            'bank.asset',
            'safe.account',
            'serviceAccount.account',
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
            if ($expense->relationLoaded('lines') && $expense->lines->count() > 1) {
                $expense->setRelation('debit_tree_account', null);
                $expense->setAttribute('debit_tree_accounts_split', true);
                continue;
            }

            $kid = (int) $expense->kind_id;
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
