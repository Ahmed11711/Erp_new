<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\Purchase;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use App\Models\TreeAccount;
/**
 * قيود المخزون الدائم: استلام مشتريات، عكسها، رصيد افتتاحي، وإرجاع تكلفة عند مرتجع بيع.
 * يُوزّع المبلغ على حسابات فرعية للمخزون حسب ربط المخزن (stocks.asset_id) مع شجرة الحسابات.
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
     * قيد السداد: مدين المورد (تخفيض ذمة) / دائن النقدية.
     */
    public function postPurchasePaymentGl(Purchase $purchase, Supplier $supplier, float $amount, ?int $userId = null): void
    {
        if ($amount <= 0.00001) {
            return;
        }
        $cashOrBankId = $this->resolvePurchasePaymentCreditTreeId($purchase);
        $supplierAccId = $this->ensureSupplierTreeAccountId($supplier);
        if (! $cashOrBankId || ! $supplierAccId) {
            return;
        }

        $desc = 'سداد مشتريات — '.$purchase->invoice_number;
        $this->postTwoLineDailyEntry(
            $desc,
            $supplierAccId,
            $amount,
            0,
            $cashOrBankId,
            0,
            $amount,
            'سداد ذمة مورد',
            'صرف من خزينة/بنك',
            $userId
        );
        $this->accountingService->updateAccountHierarchyBalances($supplierAccId);
        $this->accountingService->updateAccountHierarchyBalances($cashOrBankId);
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
    public function postOpeningInventory(float $amount, string $description, ?int $userId = null, ?TreeAccount $inventoryAccountOverride = null): void
    {
        if ($amount <= 0.00001) {
            return;
        }
        $inventory = $inventoryAccountOverride ?? TreeAccount::resolveInventoryAccount();
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
     * جرد فعلي — نقص: مدين مصروف عجز / دائن مخزون.
     */
    public function postPhysicalCountLoss(float $amount, TreeAccount $inventoryAccount, string $description, ?int $userId = null): void
    {
        if ($amount <= 0.00001) {
            return;
        }
        $loss = $this->resolveAdjustmentLossAccount();
        if (! $loss) {
            return;
        }

        $this->postTwoLineDailyEntry(
            $description,
            $loss->id,
            $amount,
            0,
            $inventoryAccount->id,
            0,
            $amount,
            'عجز جرد مخزون',
            'تخفيض قيمة مخزون — جرد',
            $userId
        );
        $this->accountingService->updateAccountHierarchyBalances($loss->id);
        $this->accountingService->updateAccountHierarchyBalances($inventoryAccount->id);
    }

    /**
     * جرد فعلي — زيادة: مدين مخزون / دائن إيراد فروقات جرد.
     */
    public function postPhysicalCountGain(float $amount, TreeAccount $inventoryAccount, string $description, ?int $userId = null): void
    {
        if ($amount <= 0.00001) {
            return;
        }
        $gain = $this->resolveAdjustmentGainAccount();
        if (! $gain) {
            return;
        }

        $this->postTwoLineDailyEntry(
            $description,
            $inventoryAccount->id,
            $amount,
            0,
            $gain->id,
            0,
            $amount,
            'زيادة جرد مخزون',
            'زيادة قيمة مخزون — جرد',
            $userId
        );
        $this->accountingService->updateAccountHierarchyBalances($inventoryAccount->id);
        $this->accountingService->updateAccountHierarchyBalances($gain->id);
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
        if (! $inventory) {
            return;
        }

        $this->postSalesReturnInventoryRestoreByWarehouse(
            [$inventory->id => $cogsAmount],
            $description,
            $userId
        );
    }

    /**
     * مرتجع بيع — مدين عدة حسابات مخزون / دائن تكلفة المبيعات (قيد واحد متوازن).
     *
     * @param  array<int, float>  $inventoryDebitAmountsByTreeAccountId
     */
    public function postSalesReturnInventoryRestoreByWarehouse(array $inventoryDebitAmountsByTreeAccountId, string $description, ?int $userId = null): void
    {
        $cogs = TreeAccount::resolveCogsAccount();
        if (! $cogs) {
            return;
        }

        $clean = [];
        foreach ($inventoryDebitAmountsByTreeAccountId as $tid => $amt) {
            $a = max(0, (float) $amt);
            if ($a > 0.00001) {
                $clean[(int) $tid] = ($clean[(int) $tid] ?? 0) + $a;
            }
        }

        $sumDr = array_sum($clean);
        if ($sumDr <= 0.00001) {
            return;
        }

        $lines = [];
        foreach ($clean as $accId => $amt) {
            $lines[] = [
                'id' => $accId,
                'debit' => $amt,
                'credit' => 0.0,
                'note' => 'إرجاع مخزون — مرتجع مبيعات',
            ];
        }
        $lines[] = [
            'id' => $cogs->id,
            'debit' => 0.0,
            'credit' => $sumDr,
            'note' => 'عكس تكلفة البضاعة المباعة',
        ];

        $this->postBalancedJournal($description, $lines, $userId);

        foreach ($lines as $ln) {
            $this->accountingService->updateAccountHierarchyBalances($ln['id']);
        }
    }

    /**
     * شحن بضاعة: مدين تكلفة المبيعات / دائن حسابات مخزون حسب مخزن كل صنف (AccountEntry فقط — نفس مسار الشحن الحالي).
     *
     * @param  array<int, float>  $inventoryCreditAmountsByTreeAccountId
     */
    public function postCogsShipment(float $totalCogs, array $inventoryCreditAmountsByTreeAccountId, string $description): void
    {
        if ($totalCogs <= 0.00001) {
            return;
        }

        $cogsAcc = TreeAccount::resolveCogsAccount();
        if (! $cogsAcc) {
            return;
        }

        $clean = [];
        foreach ($inventoryCreditAmountsByTreeAccountId as $tid => $amt) {
            $a = max(0, (float) $amt);
            if ($a > 0.00001) {
                $clean[(int) $tid] = ($clean[(int) $tid] ?? 0) + $a;
            }
        }

        $sumCr = array_sum($clean);
        if ($sumCr <= 0.00001) {
            $fallback = TreeAccount::resolveInventoryAccount();
            if (! $fallback) {
                return;
            }
            $clean = [$fallback->id => $totalCogs];
        } elseif (count($clean) > 0 && abs($sumCr - $totalCogs) > 0.00001) {
            $k = array_key_first($clean);

            $clean[$k] = ($clean[$k] ?? 0) + ($totalCogs - $sumCr);
        }

        AccountEntry::create([
            'tree_account_id' => $cogsAcc->id,
            'debit' => $totalCogs,
            'credit' => 0,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($clean as $accId => $amt) {
            if ($amt <= 0.00001) {
                continue;
            }
            AccountEntry::create([
                'tree_account_id' => $accId,
                'debit' => 0,
                'credit' => $amt,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            try {
                $this->accountingService->updateAccountHierarchyBalances((int) $accId);
            } catch (\Throwable $e) {
                // تجاهل — نفس سلوك ship_order السابق
            }
        }

        try {
            $this->accountingService->updateAccountHierarchyBalances($cogsAcc->id);
        } catch (\Throwable $e) {
            // تجاهل
        }
    }

    /**
     * حساب عجز الجرد المخصص، أو أول حساب مصروف تفصيلي كبديل حتى يظهر التأثير في الحسابات.
     */
    private function resolveAdjustmentLossAccount(): ?TreeAccount
    {
        $loss = TreeAccount::resolveInventoryAdjustmentLossAccount();
        if ($loss) {
            return $loss;
        }

        return TreeAccount::query()
            ->where('type', 'expense')
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->orderBy('id')
            ->first();
    }

    /**
     * حساب زيادة الجرد المخصص، أو أول حساب إيراد تفصيلي كبديل.
     */
    private function resolveAdjustmentGainAccount(): ?TreeAccount
    {
        $gain = TreeAccount::resolveInventoryAdjustmentGainAccount();
        if ($gain) {
            return $gain;
        }

        return TreeAccount::query()
            ->where('type', 'revenue')
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->orderBy('id')
            ->first();
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

    /**
     * استلام مشتريات مع فصل تكلفة البضاعة عن شحن التوريد (لا يُرحّل الشحن إلى المخزون).
     * Dr Inventory (منتجات) + Dr Freight-in expense (شحن) / Cr Supplier (الإجمالي).
     */
    public function postPurchaseReceiptSplit(
        float $inventoryAmount,
        float $freightInAmount,
        Supplier $supplier,
        string $description,
        ?int $userId = null
    ): void {
        $inventoryAmount = max(0, $inventoryAmount);
        $freightInAmount = max(0, $freightInAmount);
        if ($inventoryAmount <= 0.00001 && $freightInAmount <= 0.00001) {
            return;
        }

        $byAcc = [];
        if ($inventoryAmount > 0.00001) {
            $inventory = TreeAccount::resolveInventoryAccount();
            if (! $inventory) {
                return;
            }
            $byAcc[$inventory->id] = $inventoryAmount;
        }

        $this->postPurchaseReceiptSplitByAccounts($byAcc, $freightInAmount, $supplier, $description, $userId);
    }

    /**
     * استلام مشتريات — مدين عدة حسابات مخزون حسب المخازن / شحن منفصل / دائن مورد.
     *
     * @param  array<int, float>  $inventoryDebitAmountsByTreeAccountId
     */
    public function postPurchaseReceiptSplitByAccounts(
        array $inventoryDebitAmountsByTreeAccountId,
        float $freightInAmount,
        Supplier $supplier,
        string $description,
        ?int $userId = null
    ): void {
        $freightInAmount = max(0, $freightInAmount);
        $clean = [];
        foreach ($inventoryDebitAmountsByTreeAccountId as $tid => $amt) {
            $a = max(0, (float) $amt);
            if ($a > 0.00001) {
                $clean[(int) $tid] = ($clean[(int) $tid] ?? 0) + $a;
            }
        }

        $inventoryTotal = array_sum($clean);
        if ($inventoryTotal <= 0.00001 && $freightInAmount <= 0.00001) {
            return;
        }

        $supplierAccId = $this->ensureSupplierTreeAccountId($supplier);
        if (! $supplierAccId) {
            return;
        }

        $freightIn = TreeAccount::resolveFreightInExpenseAccount();
        if ($freightInAmount > 0.00001 && ! $freightIn) {
            $freightIn = TreeAccount::ensureFreightInExpenseAccount();
        }

        $lines = [];
        foreach ($clean as $accId => $amt) {
            $lines[] = ['id' => $accId, 'debit' => $amt, 'credit' => 0.0, 'note' => 'استلام مخزون — تكلفة بضاعة فقط'];
        }
        if ($freightInAmount > 0.00001 && $freightIn) {
            $lines[] = ['id' => $freightIn->id, 'debit' => $freightInAmount, 'credit' => 0.0, 'note' => 'شحن مشتريات (منفصل عن المخزون)'];
        }
        $total = $inventoryTotal + $freightInAmount;
        $lines[] = ['id' => $supplierAccId, 'debit' => 0.0, 'credit' => $total, 'note' => 'ذمة مورد — إجمالي الفاتورة'];

        $this->postBalancedJournal($description, $lines, $userId);

        foreach ($lines as $ln) {
            $this->accountingService->updateAccountHierarchyBalances($ln['id']);
        }
    }

    public function reversePurchaseReceiptSplit(
        float $inventoryAmount,
        float $freightInAmount,
        Supplier $supplier,
        string $description,
        ?int $userId = null
    ): void {
        $inventoryAmount = max(0, $inventoryAmount);
        $freightInAmount = max(0, $freightInAmount);
        if ($inventoryAmount <= 0.00001 && $freightInAmount <= 0.00001) {
            return;
        }

        $byAcc = [];
        if ($inventoryAmount > 0.00001) {
            $inventory = TreeAccount::resolveInventoryAccount();
            if (! $inventory) {
                return;
            }
            $byAcc[$inventory->id] = $inventoryAmount;
        }

        $this->reversePurchaseReceiptSplitByAccounts($byAcc, $freightInAmount, $supplier, $description, $userId);
    }

    /**
     * عكس استلام مشتريات — دائن حسابات مخزون متعددة.
     *
     * @param  array<int, float>  $inventoryCreditAmountsByTreeAccountId
     */
    public function reversePurchaseReceiptSplitByAccounts(
        array $inventoryCreditAmountsByTreeAccountId,
        float $freightInAmount,
        Supplier $supplier,
        string $description,
        ?int $userId = null
    ): void {
        $freightInAmount = max(0, $freightInAmount);
        $clean = [];
        foreach ($inventoryCreditAmountsByTreeAccountId as $tid => $amt) {
            $a = max(0, (float) $amt);
            if ($a > 0.00001) {
                $clean[(int) $tid] = ($clean[(int) $tid] ?? 0) + $a;
            }
        }

        $inventoryTotal = array_sum($clean);
        if ($inventoryTotal <= 0.00001 && $freightInAmount <= 0.00001) {
            return;
        }

        $supplierAccId = $this->ensureSupplierTreeAccountId($supplier);
        if (! $supplierAccId) {
            return;
        }

        $freightIn = TreeAccount::resolveFreightInExpenseAccount();
        if ($freightInAmount > 0.00001 && ! $freightIn) {
            $freightIn = TreeAccount::ensureFreightInExpenseAccount();
        }

        $lines = [];
        foreach ($clean as $accId => $amt) {
            $lines[] = ['id' => $accId, 'debit' => 0.0, 'credit' => $amt, 'note' => 'عكس استلام مخزون'];
        }
        if ($freightInAmount > 0.00001 && $freightIn) {
            $lines[] = ['id' => $freightIn->id, 'debit' => 0.0, 'credit' => $freightInAmount, 'note' => 'عكس شحن مشتريات'];
        }
        $total = $inventoryTotal + $freightInAmount;
        $lines[] = ['id' => $supplierAccId, 'debit' => $total, 'credit' => 0.0, 'note' => 'تخفيض ذمة مورد'];

        $this->postBalancedJournal($description, $lines, $userId);

        foreach ($lines as $ln) {
            $this->accountingService->updateAccountHierarchyBalances($ln['id']);
        }
    }

    /**
     * استلام مشتريات — شحن التوريد يُقيد على مندوب/شركة الشحن إن وُجد.
     *
     * @param  array<int, float>  $inventoryDebitAmountsByTreeAccountId
     */
    public function postPurchaseReceiptSplitByAccountsWithFreightPayable(
        array $inventoryDebitAmountsByTreeAccountId,
        float $freightInAmount,
        Supplier $supplier,
        ?ShippingCompany $shippingCompany,
        string $description,
        ?int $userId = null
    ): void {
        $freightInAmount = max(0, $freightInAmount);
        $clean = [];
        foreach ($inventoryDebitAmountsByTreeAccountId as $tid => $amt) {
            $a = max(0, (float) $amt);
            if ($a > 0.00001) {
                $clean[(int) $tid] = ($clean[(int) $tid] ?? 0) + $a;
            }
        }

        $inventoryTotal = array_sum($clean);
        if ($inventoryTotal <= 0.00001 && $freightInAmount <= 0.00001) {
            return;
        }

        $supplierAccId = $this->ensureSupplierTreeAccountId($supplier);
        if (! $supplierAccId) {
            return;
        }

        $freightIn = TreeAccount::resolveFreightInExpenseAccount();
        if ($freightInAmount > 0.00001 && ! $freightIn) {
            $freightIn = TreeAccount::ensureFreightInExpenseAccount();
        }

        $shippingPayableId = $this->resolveShippingPayableTreeId($shippingCompany);

        $lines = [];
        foreach ($clean as $accId => $amt) {
            $lines[] = ['id' => $accId, 'debit' => $amt, 'credit' => 0.0, 'note' => 'استلام مخزون — تكلفة بضاعة'];
        }
        if ($freightInAmount > 0.00001 && $freightIn) {
            $lines[] = ['id' => $freightIn->id, 'debit' => $freightInAmount, 'credit' => 0.0, 'note' => 'شحن مشتريات'];
        }
        if ($inventoryTotal > 0.00001) {
            $lines[] = ['id' => $supplierAccId, 'debit' => 0.0, 'credit' => $inventoryTotal, 'note' => 'ذمة مورد — قيمة البضاعة'];
        }
        if ($freightInAmount > 0.00001) {
            $freightCreditId = $shippingPayableId ?: $supplierAccId;
            $freightNote = $shippingPayableId ? 'ذمة مندوب/شركة شحن — شحن التوريد' : 'ذمة مورد — شحن التوريد';
            $lines[] = ['id' => $freightCreditId, 'debit' => 0.0, 'credit' => $freightInAmount, 'note' => $freightNote];
        }

        $this->postBalancedJournal($description, $lines, $userId);
        foreach ($lines as $ln) {
            $this->accountingService->updateAccountHierarchyBalances($ln['id']);
        }
    }

    /**
     * @param  array<int, float>  $inventoryCreditAmountsByTreeAccountId
     */
    public function reversePurchaseReceiptSplitByAccountsWithFreightPayable(
        array $inventoryCreditAmountsByTreeAccountId,
        float $freightInAmount,
        Supplier $supplier,
        ?ShippingCompany $shippingCompany,
        string $description,
        ?int $userId = null
    ): void {
        $freightInAmount = max(0, $freightInAmount);
        $clean = [];
        foreach ($inventoryCreditAmountsByTreeAccountId as $tid => $amt) {
            $a = max(0, (float) $amt);
            if ($a > 0.00001) {
                $clean[(int) $tid] = ($clean[(int) $tid] ?? 0) + $a;
            }
        }

        $inventoryTotal = array_sum($clean);
        if ($inventoryTotal <= 0.00001 && $freightInAmount <= 0.00001) {
            return;
        }

        $supplierAccId = $this->ensureSupplierTreeAccountId($supplier);
        if (! $supplierAccId) {
            return;
        }

        $freightIn = TreeAccount::resolveFreightInExpenseAccount();
        if ($freightInAmount > 0.00001 && ! $freightIn) {
            $freightIn = TreeAccount::ensureFreightInExpenseAccount();
        }

        $shippingPayableId = $this->resolveShippingPayableTreeId($shippingCompany);

        $lines = [];
        foreach ($clean as $accId => $amt) {
            $lines[] = ['id' => $accId, 'debit' => 0.0, 'credit' => $amt, 'note' => 'عكس استلام مخزون'];
        }
        if ($freightInAmount > 0.00001 && $freightIn) {
            $lines[] = ['id' => $freightIn->id, 'debit' => 0.0, 'credit' => $freightInAmount, 'note' => 'عكس شحن مشتريات'];
        }
        if ($inventoryTotal > 0.00001) {
            $lines[] = ['id' => $supplierAccId, 'debit' => $inventoryTotal, 'credit' => 0.0, 'note' => 'تخفيض ذمة مورد — بضاعة'];
        }
        if ($freightInAmount > 0.00001) {
            $freightDebitId = $shippingPayableId ?: $supplierAccId;
            $lines[] = ['id' => $freightDebitId, 'debit' => $freightInAmount, 'credit' => 0.0, 'note' => 'عكس ذمة شحن'];
        }

        $this->postBalancedJournal($description, $lines, $userId);
        foreach ($lines as $ln) {
            $this->accountingService->updateAccountHierarchyBalances($ln['id']);
        }
    }

    /**
     * @param  array<int, float>  $inventoryDebitAmountsByTreeAccountId
     */
    public function reverseSalesReturnInventoryRestoreByWarehouse(
        array $inventoryDebitAmountsByTreeAccountId,
        string $description,
        ?int $userId = null
    ): void {
        $cogs = TreeAccount::resolveCogsAccount();
        if (! $cogs) {
            return;
        }

        $clean = [];
        foreach ($inventoryDebitAmountsByTreeAccountId as $tid => $amt) {
            $a = max(0, (float) $amt);
            if ($a > 0.00001) {
                $clean[(int) $tid] = ($clean[(int) $tid] ?? 0) + $a;
            }
        }

        $sum = array_sum($clean);
        if ($sum <= 0.00001) {
            return;
        }

        $lines = [];
        foreach ($clean as $accId => $amt) {
            $lines[] = ['id' => $accId, 'debit' => 0.0, 'credit' => $amt, 'note' => 'عكس إرجاع مخزون — مرتجع مبيعات'];
        }
        $lines[] = ['id' => $cogs->id, 'debit' => $sum, 'credit' => 0.0, 'note' => 'عكس تكلفة مبيعات'];

        $this->postBalancedJournal($description, $lines, $userId);
        foreach ($lines as $ln) {
            $this->accountingService->updateAccountHierarchyBalances($ln['id']);
        }
    }

    private function resolveShippingPayableTreeId(?ShippingCompany $company): ?int
    {
        if (! $company) {
            return null;
        }

        if ($company->tree_account_id) {
            return (int) $company->tree_account_id;
        }

        $account = app(AccountLinkingService::class)->ensureShippingCompanyFreightPayableAccount($company);

        return $account?->id ? (int) $account->id : null;
    }

    /**
     * قيد واحد لمطابقة أرصدة حسابات المخزون الفرعية مع مجموع تكلفة الأصناف (categories.total_price).
     *
     * @param  array<int, float>  $adjustmentsByAccountId  فرق لكل حساب: القيمة الفعلية من الأصناف − رصيد الحساب من القيود (مدين مخزون عند الزيادة).
     * @return array{daily_entry_id: int, entry_number: mixed, lines: array<int, array<string, mixed>>}
     */
    public function postInventoryAccountsTrueUpJournal(array $adjustmentsByAccountId, ?int $userId): array
    {
        $lines = [];
        foreach ($adjustmentsByAccountId as $accId => $delta) {
            $delta = round((float) $delta, 2);
            if (abs($delta) < 0.02) {
                continue;
            }
            $accId = (int) $accId;
            if ($delta > 0) {
                $lines[] = [
                    'id' => $accId,
                    'debit' => $delta,
                    'credit' => 0.0,
                    'note' => 'مطابقة مخزون — تكلفة أصناف مقابل رصيد الحساب',
                ];
            } else {
                $lines[] = [
                    'id' => $accId,
                    'debit' => 0.0,
                    'credit' => abs($delta),
                    'note' => 'مطابقة مخزون — تكلفة أصناف مقابل رصيد الحساب',
                ];
            }
        }

        if (count($lines) === 0) {
            throw new \InvalidArgumentException('لا توجد بنود للقيد.');
        }

        $net = 0.0;
        foreach ($lines as $ln) {
            $net += $ln['debit'] - $ln['credit'];
        }
        $net = round($net, 2);

        if (abs($net) >= 0.02) {
            if ($net > 0) {
                $gain = TreeAccount::resolveInventoryAdjustmentGainAccount()
                    ?? TreeAccount::resolveOpeningInventoryOffsetAccount();
                if (! $gain) {
                    throw new \RuntimeException('تعذر تحديد حساب طرف مقابل لتسوية المخزون (إيراد فروقات جرد).');
                }
                $lines[] = [
                    'id' => $gain->id,
                    'debit' => 0.0,
                    'credit' => $net,
                    'note' => 'طرف مقابل تسوية مخزون — صافي زيادة',
                ];
            } else {
                $loss = TreeAccount::resolveInventoryAdjustmentLossAccount()
                    ?? TreeAccount::resolveOpeningInventoryOffsetAccount();
                if (! $loss) {
                    throw new \RuntimeException('تعذر تحديد حساب طرف مقابل لتسوية المخزون (مصروف عجز جرد).');
                }
                $lines[] = [
                    'id' => $loss->id,
                    'debit' => abs($net),
                    'credit' => 0.0,
                    'note' => 'طرف مقابل تسوية مخزون — صافي نقصان',
                ];
            }
        }

        $desc = 'تسوية مخزون — مطابقة الحسابات مع التكلفة الفعلية للأصناف (' . now()->format('Y-m-d H:i') . ')';
        $dailyEntryId = $this->postBalancedJournal($desc, $lines, $userId);
        $entry = DailyEntry::query()->find($dailyEntryId);

        foreach ($lines as $ln) {
            $this->accountingService->updateAccountHierarchyBalances((int) $ln['id']);
        }

        $outLines = [];
        foreach ($lines as $ln) {
            $acc = TreeAccount::query()->find((int) $ln['id']);
            $outLines[] = [
                'account_id' => (int) $ln['id'],
                'account_code' => $acc->code ?? null,
                'account_name' => $acc->name ?? null,
                'debit' => $ln['debit'],
                'credit' => $ln['credit'],
                'note' => $ln['note'],
            ];
        }

        return [
            'daily_entry_id' => $dailyEntryId,
            'entry_number' => $entry->entry_number ?? null,
            'lines' => $outLines,
        ];
    }

    /**
     * @param array<int, array{id:int, debit:float, credit:float, note:string}> $lines
     */
    private function postBalancedJournal(string $description, array $lines, ?int $userId): int
    {
        $sumDr = 0.0;
        $sumCr = 0.0;
        foreach ($lines as $ln) {
            $sumDr += (float) $ln['debit'];
            $sumCr += (float) $ln['credit'];
        }
        if (abs($sumDr - $sumCr) > 0.02) {
            throw new \InvalidArgumentException('القيد غير متوازن: مدين ' . $sumDr . ' دائن ' . $sumCr);
        }

        $uid = $userId ?? auth()->id();
        $entryNumber = DailyEntry::getNextEntryNumber();
        $dailyEntry = DailyEntry::create([
            'date' => now(),
            'entry_number' => $entryNumber,
            'description' => $description,
            'user_id' => $uid,
        ]);

        foreach ($lines as $ln) {
            DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $ln['id'],
                'debit' => $ln['debit'],
                'credit' => $ln['credit'],
                'notes' => $ln['note'],
            ]);
            AccountEntry::create([
                'tree_account_id' => $ln['id'],
                'debit' => $ln['debit'],
                'credit' => $ln['credit'],
                'description' => $description . ' — ' . $ln['note'],
                'daily_entry_id' => $dailyEntry->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (int) $dailyEntry->id;
    }
}
