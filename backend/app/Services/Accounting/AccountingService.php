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
     * Update parent account balance based on children
     */
    private function updateParentAccountBalance(int $parentId): void
    {
        $parent = TreeAccount::findOrFail($parentId);
        
        // Calculate sum of all direct children
        $children = TreeAccount::where('parent_id', $parentId)->get();
        
        $totalDebit = $children->sum('debit_balance');
        $totalCredit = $children->sum('credit_balance');
        
        // Update parent balance
        $parent->debit_balance = $totalDebit;
        $parent->credit_balance = $totalCredit;
        $parent->balance = $totalDebit - $totalCredit;
        $parent->save();

        // Continue up the hierarchy
        if ($parent->parent_id) {
            $this->updateParentAccountBalance($parent->parent_id);
        }
    }

    /**
     * Validate trial balance equality
     */
    public function validateTrialBalance(array $trialBalanceData): array
    {
        $totalDebit = collect($trialBalanceData)->sum('closing_debit');
        $totalCredit = collect($trialBalanceData)->sum('closing_credit');
        $difference = abs($totalDebit - $totalCredit);

        return [
            'is_balanced' => $difference == 0,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'difference' => $difference,
            'message' => $difference == 0 ? 'ميزان المراجعة متوازن' : 'ميزان المراجعة غير متوازن'
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
