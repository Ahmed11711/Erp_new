<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\Purchase;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\Supplier;
use App\Models\TreeAccount;
/**
 * قيود المخزون الدائم: استلام مشتريات، عكسها، رصيد افتتاحي، وإرجاع تكلفة عند مرتجع بيع.
 */
class InventoryGlPostingService
{
    public function __construct(
        private AccountingService $accountingService
    ) {
    }

    /**
     * استلام مشتريات: من حـ المخزون / إلى حـ المورد (ذمة).
     */
    public function postPurchaseReceipt(float $amount, Supplier $supplier, string $description, ?int $userId = null): void
    {
        if ($amount <= 0.00001) {
            return;
        }
        $inventory = TreeAccount::resolveInventoryAccount();
        $supplierAccId = $this->ensureSupplierTreeAccountId($supplier);
        if (!$inventory || !$supplierAccId) {
            return;
        }

        $this->postTwoLineDailyEntry(
            $description,
            $inventory->id,
            $amount,
            0,
            $supplierAccId,
            0,
            $amount,
            'استلام مخزون (مشتريات)',
            'ذمة مورد — استلام بضاعة',
            $userId
        );
        $this->accountingService->updateAccountHierarchyBalances($inventory->id);
        $this->accountingService->updateAccountHierarchyBalances($supplierAccId);
    }

    /**
     * عكس استلام مشتريات (تعديل/حذف): دائن مخزون، مدين مورد.
     */
    public function reversePurchaseReceipt(float $amount, Supplier $supplier, string $description, ?int $userId = null): void
    {
        if ($amount <= 0.00001) {
            return;
        }
        $inventory = TreeAccount::resolveInventoryAccount();
        $supplierAccId = $this->ensureSupplierTreeAccountId($supplier);
        if (!$inventory || !$supplierAccId) {
            return;
        }

        $this->postTwoLineDailyEntry(
            $description,
            $inventory->id,
            0,
            $amount,
            $supplierAccId,
            $amount,
            0,
            'عكس استلام مخزون',
            'تخفيض ذمة مورد',
            $userId
        );
        $this->accountingService->updateAccountHierarchyBalances($inventory->id);
        $this->accountingService->updateAccountHierarchyBalances($supplierAccId);
    }

    /**
     * عكس قيد السداد السابق: أصل النقدية (مدين) / المورد (دائن) — عكس منطق processPurchasePayment.
     */
    public function reversePurchasePaymentGl(Purchase $purchase, Supplier $supplier, float $amount, ?int $userId = null): void
    {
        if ($amount <= 0.00001) {
            return;
        }
        $cashOrBankId = $this->resolvePurchasePaymentCreditTreeId($purchase);
        $supplierAccId = $this->ensureSupplierTreeAccountId($supplier);
        if (!$cashOrBankId || !$supplierAccId) {
            return;
        }

        $desc = 'عكس سداد مشتريات — ' . $purchase->invoice_number;
        $this->postTwoLineDailyEntry(
            $desc,
            $cashOrBankId,
            $amount,
            0,
            $supplierAccId,
            0,
            $amount,
            'إرجاع للخزينة/البنك (عكس سداد)',
            'زيادة ذمة مورد (عكس سداد)',
            $userId
        );
        $this->accountingService->updateAccountHierarchyBalances($cashOrBankId);
        $this->accountingService->updateAccountHierarchyBalances($supplierAccId);
    }

    /**
     * رصيد افتتاحي لصنف: من حـ المخزون / إلى حـ موازنة افتتاحية (حقوق ملكية).
     */
    public function postOpeningInventory(float $amount, string $description, ?int $userId = null): void
    {
        if ($amount <= 0.00001) {
            return;
        }
        $inventory = TreeAccount::resolveInventoryAccount();
        $offset = TreeAccount::resolveOpeningInventoryOffsetAccount();
        if (!$inventory || !$offset) {
            return;
        }

        $this->postTwoLineDailyEntry(
            $description,
            $inventory->id,
            $amount,
            0,
            $offset->id,
            0,
            $amount,
            'رصيد مخزون افتتاحي',
            'موازنة افتتاحية مخزون',
            $userId
        );
        $this->accountingService->updateAccountHierarchyBalances($inventory->id);
        $this->accountingService->updateAccountHierarchyBalances($offset->id);
    }

    /**
     * مرتجع بيع (إرجاع بضاعة للمخزون): من حـ المخزون / إلى حـ تكلفة المبيعات (عكس COGS).
     */
    public function postSalesReturnInventoryRestore(float $cogsAmount, string $description, ?int $userId = null): void
    {
        if ($cogsAmount <= 0.00001) {
            return;
        }
        $inventory = TreeAccount::resolveInventoryAccount();
        $cogs = TreeAccount::resolveCogsAccount();
        if (!$inventory || !$cogs) {
            return;
        }

        $this->postTwoLineDailyEntry(
            $description,
            $inventory->id,
            $cogsAmount,
            0,
            $cogs->id,
            0,
            $cogsAmount,
            'إرجاع مخزون — مرتجع مبيعات',
            'عكس تكلفة البضاعة المباعة',
            $userId
        );
        $this->accountingService->updateAccountHierarchyBalances($inventory->id);
        $this->accountingService->updateAccountHierarchyBalances($cogs->id);
    }

    private function ensureSupplierTreeAccountId(Supplier $supplier): ?int
    {
        if ($supplier->tree_account_id) {
            return (int) $supplier->tree_account_id;
        }
        $account = app(AccountLinkingService::class)->ensureSupplierAccount($supplier);

        return $account?->id;
    }

    /**
     * نفس حساب الدائن في processPurchasePayment (الخزينة/البنك/حساب خدمي).
     */
    public function resolvePurchasePaymentCreditTreeId(Purchase $purchase): ?int
    {
        $pt = $purchase->payment_type ?? 'bank';
        if ($pt === 'safe' && $purchase->safe_id) {
            $safe = Safe::find($purchase->safe_id);

            return $safe?->account_id ? (int) $safe->account_id : null;
        }
        if ($pt === 'service_account' && $purchase->service_account_id) {
            $svc = ServiceAccount::find($purchase->service_account_id);

            return $svc?->account_id ? (int) $svc->account_id : null;
        }
        if ($purchase->bank_id) {
            $bank = Bank::find($purchase->bank_id);

            return $bank?->asset_id ? (int) $bank->asset_id : null;
        }

        return null;
    }

    private function postTwoLineDailyEntry(
        string $description,
        int $account1Id,
        float $d1,
        float $c1,
        int $account2Id,
        float $d2,
        float $c2,
        string $note1,
        string $note2,
        ?int $userId
    ): void {
        $uid = $userId ?? auth()->id();
        $entryNumber = DailyEntry::getNextEntryNumber();
        $dailyEntry = DailyEntry::create([
            'date' => now(),
            'entry_number' => $entryNumber,
            'description' => $description,
            'user_id' => $uid,
        ]);
        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $account1Id,
            'debit' => $d1,
            'credit' => $c1,
            'notes' => $note1,
        ]);
        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $account2Id,
            'debit' => $d2,
            'credit' => $c2,
            'notes' => $note2,
        ]);
        AccountEntry::create([
            'tree_account_id' => $account1Id,
            'debit' => $d1,
            'credit' => $c1,
            'description' => $description . ' — ' . $note1,
            'daily_entry_id' => $dailyEntry->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        AccountEntry::create([
            'tree_account_id' => $account2Id,
            'debit' => $d2,
            'credit' => $c2,
            'description' => $description . ' — ' . $note2,
            'daily_entry_id' => $dailyEntry->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
