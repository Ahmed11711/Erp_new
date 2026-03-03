<?php

namespace App\Services\Accounting;

use App\Models\Cimmitment;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Models\Setting;
use App\Services\Accounting\AccountLinkingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * خدمة معالجة القيود المحاسبية للالتزامات
 * 
 * وفق القيد المزدوج:
 * - عند إنشاء التزام: مدين (حساب المصروف) / دائن (حساب الالتزام)
 * - عند السداد: مدين (حساب الالتزام) / دائن (الخزينة أو البنك)
 */
class CommitmentAccountingService
{
    public function __construct(
        protected AccountingService $accountingService,
        protected AccountLinkingService $accountLinkingService
    ) {}

    /**
     * تسجيل قيد محاسبي عند إنشاء التزام جديد
     * مدين: حساب المصروف/التكلفة
     * دائن: حساب الالتزام (المورد أو التزامات عامة)
     */
    public function recordCommitmentEntry(Cimmitment $commitment): array
    {
        try {
            DB::beginTransaction();

            $expenseAccount = TreeAccount::find($commitment->expense_account_id);
            $liabilityAccount = TreeAccount::find($commitment->liability_account_id);

            if (!$expenseAccount || !$liabilityAccount) {
                throw new \Exception('يجب تحديد حساب المصروف وحساب الالتزام');
            }

            $amount = (float) $commitment->deserved_amount;
            $description = "التزام: {$commitment->name} - {$commitment->getPayeeDisplayNameAttribute()}";

            // قيد إنشاء الالتزام: مدين المصروف / دائن الالتزام
            $expenseEntry = AccountEntry::create([
                'tree_account_id' => $expenseAccount->id,
                'debit' => $amount,
                'credit' => 0,
                'description' => $description,
                'cimmitment_id' => $commitment->id,
            ]);

            $liabilityEntry = AccountEntry::create([
                'tree_account_id' => $liabilityAccount->id,
                'debit' => 0,
                'credit' => $amount,
                'description' => $description,
                'cimmitment_id' => $commitment->id,
            ]);

            $this->accountingService->updateAccountHierarchyBalances($expenseAccount->id);
            $this->accountingService->updateAccountHierarchyBalances($liabilityAccount->id);

            DB::commit();

            return [
                'success' => true,
                'entries' => [$expenseEntry, $liabilityEntry],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CommitmentAccountingService::recordCommitmentEntry failed', [
                'commitment_id' => $commitment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * تسجيل قيد السداد عند دفع جزء أو كل الالتزام
     * مدين: حساب الالتزام
     * دائن: حساب الخزينة/البنك
     */
    public function recordPaymentEntry(
        Cimmitment $commitment,
        float $amount,
        int $cashAccountId,
        string $description = null
    ): array {
        try {
            DB::beginTransaction();

            $liabilityAccount = TreeAccount::findOrFail($commitment->liability_account_id);
            $cashAccount = TreeAccount::findOrFail($cashAccountId);

            $remaining = $commitment->remaining_amount;
            if ($amount > $remaining) {
                throw new \Exception("المبلغ المدخل ({$amount}) يتجاوز المبلغ المتبقي ({$remaining})");
            }

            $desc = $description ?? "سداد التزام: {$commitment->name}";

            // قيد السداد: مدين الالتزام / دائن الخزينة
            $liabilityEntry = AccountEntry::create([
                'tree_account_id' => $liabilityAccount->id,
                'debit' => $amount,
                'credit' => 0,
                'description' => $desc,
                'cimmitment_id' => $commitment->id,
            ]);

            $cashEntry = AccountEntry::create([
                'tree_account_id' => $cashAccount->id,
                'debit' => 0,
                'credit' => $amount,
                'description' => $desc,
                'cimmitment_id' => $commitment->id,
            ]);

            $commitment->paid_amount = (float) $commitment->paid_amount + $amount;
            $commitment->status = $commitment->remaining_amount <= 0
                ? Cimmitment::STATUS_PAID
                : Cimmitment::STATUS_PARTIAL;
            $commitment->save();

            $this->accountingService->updateAccountHierarchyBalances($liabilityAccount->id);
            $this->accountingService->updateAccountHierarchyBalances($cashAccount->id);

            DB::commit();

            return [
                'success' => true,
                'entries' => [$liabilityEntry, $cashEntry],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CommitmentAccountingService::recordPaymentEntry failed', [
                'commitment_id' => $commitment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * الحصول على حساب الالتزامات الافتراضي من الإعدادات
     */
    public function getDefaultLiabilityAccount(): ?TreeAccount
    {
        $accountId = Setting::where('key', 'commitment_liability_parent_account_id')->value('value');
        return $accountId ? TreeAccount::find($accountId) : null;
    }

    /**
     * الحصول على حساب المصروفات الافتراضي للالتزامات
     */
    public function getDefaultExpenseAccount(): ?TreeAccount
    {
        $accountId = Setting::where('key', 'commitment_expense_parent_account_id')->value('value');
        return $accountId ? TreeAccount::find($accountId) : null;
    }
}
