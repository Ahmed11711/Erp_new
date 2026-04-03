<?php

namespace App\Services\Accounting;

use App\Models\TreeAccount;
use App\Models\AccountEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountingService
{
    /**
     * Process cash transaction with double-entry validation
     * 
     * @param array $transactionData
     * @return array
     */
    public function processCashTransaction(array $transactionData): array
    {
        try {
            DB::beginTransaction();

            // Validate required fields
            $this->validateTransactionData($transactionData);

            // Get cash account
            $cashAccount = TreeAccount::findOrFail($transactionData['cash_account_id']);
            
            // Validate that cash account is actually a cash/bank account
            if (!in_array($cashAccount->type, ['asset']) || !str_contains(strtolower($cashAccount->name), 'خزينة') && !str_contains(strtolower($cashAccount->name), 'بنك')) {
                throw new \Exception('الحساب المحدد ليس حساب خزينة أو بنك');
            }

            // Create double entries
            $entries = $this->createDoubleEntries($transactionData, $cashAccount);

            // Update account hierarchy balances
            $this->updateAccountHierarchyBalances($cashAccount->id);
            $this->updateAccountHierarchyBalances($transactionData['account_id']);

            DB::commit();

            return [
                'success' => true,
                'message' => 'تمت عملية الدفع بنجاح',
                'entries' => $entries
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cash transaction failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'فشلت عملية الدفع: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate transaction data
     */
    private function validateTransactionData(array $data): void
    {
        $requiredFields = ['cash_account_id', 'account_id', 'amount', 'description'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \Exception("حقل {$field} مطلوب");
            }
        }

        if ($data['amount'] <= 0) {
            throw new \Exception('المبلغ يجب أن يكون أكبر من صفر');
        }

        // Validate accounts exist
        if (!TreeAccount::find($data['cash_account_id'])) {
            throw new \Exception('حساب الخزينة غير موجود');
        }

        if (!TreeAccount::find($data['account_id'])) {
            throw new \Exception('الحساب المستهدف غير موجود');
        }
    }

    /**
     * Create double entries for transaction
     */
    private function createDoubleEntries(array $transactionData, TreeAccount $cashAccount): array
    {
        $amount = $transactionData['amount'];
        $description = $transactionData['description'];
        $targetAccount = TreeAccount::findOrFail($transactionData['account_id']);

        $entries = [];

        // Cash account entry (credit for cash out, debit for cash in)
        $cashEntry = AccountEntry::create([
            'tree_account_id' => $cashAccount->id,
            'debit' => $transactionData['transaction_type'] === 'cash_in' ? $amount : 0,
            'credit' => $transactionData['transaction_type'] === 'cash_out' ? $amount : 0,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Target account entry (debit for cash out, credit for cash in)
        $targetEntry = AccountEntry::create([
            'tree_account_id' => $targetAccount->id,
            'debit' => $transactionData['transaction_type'] === 'cash_out' ? $amount : 0,
            'credit' => $transactionData['transaction_type'] === 'cash_in' ? $amount : 0,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Link entries if voucher or daily entry is provided
        if (isset($transactionData['voucher_id'])) {
            $cashEntry->voucher_id = $transactionData['voucher_id'];
            $targetEntry->voucher_id = $transactionData['voucher_id'];
        }

        if (isset($transactionData['daily_entry_id'])) {
            $cashEntry->daily_entry_id = $transactionData['daily_entry_id'];
            $targetEntry->daily_entry_id = $transactionData['daily_entry_id'];
        }

        $cashEntry->save();
        $targetEntry->save();

        return [$cashEntry, $targetEntry];
    }

    /**
     * Update account hierarchy balances
     * This ensures that parent accounts reflect the sum of their children
     */
    public function updateAccountHierarchyBalances(int $accountId): void
    {
        $account = TreeAccount::findOrFail($accountId);
        
        // Calculate current balance from entries
        $totalDebit = AccountEntry::where('tree_account_id', $accountId)->sum('debit');
        $totalCredit = AccountEntry::where('tree_account_id', $accountId)->sum('credit');
        
        // Update account balance
        $account->debit_balance = $totalDebit;
        $account->credit_balance = $totalCredit;
        $account->balance = $totalDebit - $totalCredit;
        $account->save();

        // Update parent accounts recursively
        if ($account->parent_id) {
            $this->updateParentAccountBalance($account->parent_id);
        }
    }

    /**
     * بعد تعديل أرصدة حساب طرفي يدوياً (مع وجود قيود + أرصدة قديمة غير مُمثَّلة في account_entries)،
     * حدّث الحسابات الأب فقط دون إعادة حساب الطرفي من مجموع القيود — وإلا تُمسح أرصدة legacy.
     */
    public function propagateBalancesUpFromLeaf(int $leafAccountId): void
    {
        $account = TreeAccount::findOrFail($leafAccountId);
        if ($account->parent_id) {
            $this->updateParentAccountBalance($account->parent_id);
        }
    }

    /**
     * Update parent account balance based on own entries + children (best practice: parent = own + children)
     * يضمن أن الحساب الفرعي يؤثر في الحساب الأب وجميع المستويات الأعلى في الشجرة
     */
    private function updateParentAccountBalance(int $parentId): void
    {
        $parent = TreeAccount::findOrFail($parentId);
        
        // 1. Parent's direct entries (الحساب الأب قد يكون له قيود مباشرة)
        $directDebit = AccountEntry::where('tree_account_id', $parentId)->sum('debit');
        $directCredit = AccountEntry::where('tree_account_id', $parentId)->sum('credit');
        
        // 2. Sum of all direct children's balances (الأبناء قد يكون لهم أرصدة محدثة)
        $children = TreeAccount::where('parent_id', $parentId)->get();
        $childrenDebit = $children->sum('debit_balance');
        $childrenCredit = $children->sum('credit_balance');
        
        // 3. Total = own entries + children (المجموع = القيود المباشرة + أرصدة الأبناء)
        $totalDebit = $directDebit + $childrenDebit;
        $totalCredit = $directCredit + $childrenCredit;
        
        $parent->debit_balance = $totalDebit;
        $parent->credit_balance = $totalCredit;
        $parent->balance = $totalDebit - $totalCredit;
        $parent->save();

        // Continue up the hierarchy (الاستمرار للأعلى في الشجرة)
        if ($parent->parent_id) {
            $this->updateParentAccountBalance($parent->parent_id);
        }
    }

    /**
     * Recalculate all account hierarchy balances from scratch (bottom-up)
     * إعادة حساب جميع أرصدة الشجرة من الصفر - مفيد لتصحيح سلامة البيانات
     * يعالج الحسابات الطرفية فقط، والأباء يتم تحديثهم تلقائياً عند التصاعد
     */
    public function recalculateAllHierarchyBalances(): array
    {
        $updated = 0;
        $errors = [];

        try {
            // Leaf accounts only - parents get updated when we propagate up
            $leafAccounts = TreeAccount::whereDoesntHave('children')->get();
            
            foreach ($leafAccounts as $account) {
                try {
                    $this->updateAccountHierarchyBalances($account->id);
                    $updated++;
                } catch (\Exception $e) {
                    $errors[] = "حساب {$account->id} ({$account->name}): " . $e->getMessage();
                }
            }

            return [
                'success' => empty($errors),
                'updated_count' => $updated,
                'total_accounts' => TreeAccount::count(),
                'errors' => $errors,
                'message' => empty($errors)
                    ? "تم تحديث جميع الأرصدة بنجاح ({$updated} حساب طرفي)"
                    : "تم التحديث مع بعض الأخطاء"
            ];
        } catch (\Exception $e) {
            Log::error('Recalculate hierarchy failed: ' . $e->getMessage());
            return [
                'success' => false,
                'updated_count' => $updated,
                'errors' => [$e->getMessage()],
                'message' => 'فشل إعادة الحساب: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate trial balance equality (مطابق لأفضل أنظمة المحاسبة)
     * - التحقق من توازن الرصيد الافتتاحي والختامي والحركة
     * - استخدام tolerance صغير (0.01) لتجنب أخطاء الفاصلة العائمة
     */
    public function validateTrialBalance(array $trialBalanceData): array
    {
        $tolerance = 0.01;
        $data = collect($trialBalanceData);

        $closingDebit = round($data->sum('closing_debit'), 2);
        $closingCredit = round($data->sum('closing_credit'), 2);
        $closingDiff = abs($closingDebit - $closingCredit);

        $openingDebit = round($data->sum('opening_debit'), 2);
        $openingCredit = round($data->sum('opening_credit'), 2);
        $openingDiff = abs($openingDebit - $openingCredit);

        $movementDebit = round($data->sum('movement_debit'), 2);
        $movementCredit = round($data->sum('movement_credit'), 2);
        $movementDiff = abs($movementDebit - $movementCredit);

        $isBalanced = $closingDiff <= $tolerance && $movementDiff <= $tolerance;

        return [
            'is_balanced' => $isBalanced,
            'total_debit' => $closingDebit,
            'total_credit' => $closingCredit,
            'difference' => $closingDiff,
            'opening' => [
                'debit' => $openingDebit,
                'credit' => $openingCredit,
                'difference' => $openingDiff,
                'balanced' => $openingDiff <= $tolerance,
            ],
            'movement' => [
                'debit' => $movementDebit,
                'credit' => $movementCredit,
                'difference' => $movementDiff,
                'balanced' => $movementDiff <= $tolerance,
            ],
            'closing' => [
                'debit' => $closingDebit,
                'credit' => $closingCredit,
                'difference' => $closingDiff,
                'balanced' => $closingDiff <= $tolerance,
            ],
            'message' => $isBalanced ? 'ميزان المراجعة متوازن' : 'ميزان المراجعة غير متوازن - يرجى مراجعة القيود',
        ];
    }

    /**
     * Get account hierarchy with calculated balances
     */
    public function getAccountHierarchyWithBalances(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $accounts = TreeAccount::with(['children', 'parent'])
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        return $accounts->map(function ($account) use ($dateFrom, $dateTo) {
            return $this->calculateAccountBalances($account, $dateFrom, $dateTo);
        })->toArray();
    }

    /**
     * Calculate balances for account and its children
     */
    private function calculateAccountBalances(TreeAccount $account, ?string $dateFrom, ?string $dateTo): array
    {
        // Get direct entries for this account
        $entriesQuery = AccountEntry::where('tree_account_id', $account->id);
        
        if ($dateFrom) {
            $entriesQuery->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $entriesQuery->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $directDebit = $entriesQuery->sum('debit');
        $directCredit = $entriesQuery->sum('credit');

        // Calculate children balances
        $childrenData = [];
        $childrenDebit = 0;
        $childrenCredit = 0;

        if ($account->children->isNotEmpty()) {
            foreach ($account->children as $child) {
                $childData = $this->calculateAccountBalances($child, $dateFrom, $dateTo);
                $childrenData[] = $childData;
                $childrenDebit += $childData['total_debit'];
                $childrenCredit += $childData['total_credit'];
            }
        }

        $totalDebit = $directDebit + $childrenDebit;
        $totalCredit = $directCredit + $childrenCredit;

        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'name_en' => $account->name_en,
            'type' => $account->type,
            'level' => $account->level,
            'direct_debit' => $directDebit,
            'direct_credit' => $directCredit,
            'children_debit' => $childrenDebit,
            'children_credit' => $childrenCredit,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => $totalDebit - $totalCredit,
            'children' => $childrenData
        ];
    }

    /**
     * Validate income/revenue account structure
     */
    public function validateIncomeStructure(): array
    {
        $incomeAccounts = TreeAccount::where('type', 'revenue')
            ->orWhere('type', 'income')
            ->get();

        $issues = [];
        
        foreach ($incomeAccounts as $account) {
            // Check if income accounts have correct structure
            if ($account->debit_balance > $account->credit_balance) {
                $issues[] = [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'issue' => 'حساب الإيرادات لديه رصيد مدين أكبر من الدائن',
                    'suggestion' => 'يجب مراجعة القيود المحاسبية لهذا الحساب'
                ];
            }
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'total_income_accounts' => $incomeAccounts->count()
        ];
    }
}
