<?php

namespace App\Services\Processing;

use App\Models\Supplier;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\LedgerJournalService;
use Illuminate\Support\Facades\DB;

class ProcessingAccountingService
{
    public function __construct(
        private LedgerJournalService $journal,
        private AccountLinkingService $accountLinking,
    ) {
    }

    public function postDispatchReclassification(float $amount, int $sourceStockId, string $description, string $batchCode): int
    {
        if ($amount <= 0.00001) {
            throw new \InvalidArgumentException('مبلغ قيد الصرف يجب أن يكون أكبر من صفر');
        }

        $sourceAcc = $this->resolveStockAccount($sourceStockId);
        $atVendorAcc = $this->resolveMaterialsAtVendorAccount();

        $entry = $this->journal->postBalancedJournal(
            [
                ['account_id' => $atVendorAcc->id, 'debit' => $amount, 'credit' => 0, 'description' => 'مواد لدى مندوب — ' . $description],
                ['account_id' => $sourceAcc->id, 'debit' => 0, 'credit' => $amount, 'description' => 'صرف مواد للتشغيل الخارجي — ' . $description],
            ],
            'إذن صرف تشغيل خارجي — ' . $description,
            null,
            $batchCode,
            auth()->id(),
            now()
        );

        return (int) $entry->id;
    }

    /**
     * @param  array<int, array{debit_account_id: int, credit_account_id: int, amount: float, description: string}>  $legs
     */
    public function postReceiptJournal(array $legs, string $header, string $batchCode): int
    {
        $lines = [];
        foreach ($legs as $leg) {
            $amt = round((float) $leg['amount'], 4);
            if ($amt <= 0.00001) {
                continue;
            }
            $lines[] = ['account_id' => (int) $leg['debit_account_id'], 'debit' => $amt, 'credit' => 0, 'description' => $leg['description']];
            $lines[] = ['account_id' => (int) $leg['credit_account_id'], 'debit' => 0, 'credit' => $amt, 'description' => $leg['description']];
        }

        if ($lines === []) {
            throw new \InvalidArgumentException('لا توجد قيود محاسبية للاستلام');
        }

        $entry = $this->journal->postBalancedJournal(
            $lines,
            $header,
            null,
            $batchCode,
            auth()->id(),
            now()
        );

        return (int) $entry->id;
    }

    public function postInvoice(Supplier $supplier, float $amount, string $description, string $batchCode, bool $capitalizeToInventory, ?int $destinationStockId = null): int
    {
        if ($amount <= 0.00001) {
            throw new \InvalidArgumentException('مبلغ الفاتورة يجب أن يكون أكبر من صفر');
        }

        $supplierAcc = $this->ensureSupplierAccount($supplier);
        $debitAccId = $capitalizeToInventory
            ? ($destinationStockId ? $this->resolveStockAccount($destinationStockId)->id : $this->resolveMaterialsAtVendorAccount()->id)
            : $this->resolveServiceExpenseAccount()->id;

        $entry = $this->journal->postBalancedJournal(
            [
                ['account_id' => $debitAccId, 'debit' => $amount, 'credit' => 0, 'description' => 'تكلفة تشغيل — ' . $description],
                ['account_id' => $supplierAcc->id, 'debit' => 0, 'credit' => $amount, 'description' => 'ذمة مورد — ' . $description],
            ],
            'فاتورة تشغيل خارجي — ' . $description,
            null,
            $batchCode,
            auth()->id(),
            now()
        );

        return (int) $entry->id;
    }

    public function resolveMaterialsAtVendorAccount(): TreeAccount
    {
        $stock = ProcessingWarehouseResolver::ensureMaterialsAtVendorStock();
        $acc = TreeAccount::resolveInventoryAccountForStock($stock);
        if (! $acc) {
            $acc = DB::table('tree_accounts')->where('code', '1000224')->first();
            if ($acc) {
                return TreeAccount::query()->findOrFail((int) $acc->id);
            }
            throw new \RuntimeException('تعذر تهيئة حساب مخزون مواد لدى المندوب.');
        }

        return $acc;
    }

    public function resolveScrapExpenseAccount(): TreeAccount
    {
        // نُميّز حساب التشغيل الخارجي عبر detail_type فقط حتى لا يتصادم مع أكواد حسابات أخرى مستخدمة.
        $acc = TreeAccount::query()->where('detail_type', 'subcontract_scrap')->first();

        if (! $acc) {
            $acc = $this->ensureScrapExpenseAccount();
        }

        return $acc;
    }

    public function resolveServiceExpenseAccount(): TreeAccount
    {
        $acc = TreeAccount::query()->where('detail_type', 'subcontract_service')->first();

        if (! $acc) {
            $acc = $this->ensureServiceExpenseAccount();
        }

        return $acc;
    }

    private function ensureScrapExpenseAccount(): TreeAccount
    {
        $existing = TreeAccount::query()->where('detail_type', 'subcontract_scrap')->first();
        if ($existing) {
            return $existing;
        }

        $parent = $this->resolveProcessingExpenseParent();

        return TreeAccount::query()->create([
            'name' => 'خسائر تشغيل خارجي (تالف)',
            'name_en' => 'Subcontract scrap / damage',
            'code' => $this->freeChildCode($parent),
            'type' => 'expense',
            'detail_type' => 'subcontract_scrap',
            'parent_id' => (int) $parent->id,
            'level' => (int) ($parent->level ?? 2) + 1,
            'balance' => 0,
        ]);
    }

    private function ensureServiceExpenseAccount(): TreeAccount
    {
        $existing = TreeAccount::query()->where('detail_type', 'subcontract_service')->first();
        if ($existing) {
            return $existing;
        }

        $parent = $this->resolveProcessingExpenseParent();

        return TreeAccount::query()->create([
            'name' => 'مصاريف تشغيل خارجي',
            'name_en' => 'Subcontract service expense',
            'code' => $this->freeChildCode($parent),
            'type' => 'expense',
            'detail_type' => 'subcontract_service',
            'parent_id' => (int) $parent->id,
            'level' => (int) ($parent->level ?? 2) + 1,
            'balance' => 0,
        ]);
    }

    /**
     * الحساب الأب لمصروفات التشغيل الخارجي — نُفضّل «مصروفات التشغيل (50003)» ثم «المصروفات (5000)».
     */
    private function resolveProcessingExpenseParent(): TreeAccount
    {
        $parent = TreeAccount::query()->where('code', '50003')->where('type', 'expense')->first()
            ?? TreeAccount::query()->where('code', '5000')->where('type', 'expense')->first()
            ?? TreeAccount::query()->where('type', 'expense')->orderBy('id')->first();

        if (! $parent) {
            throw new \RuntimeException('لا يوجد حساب مصروفات أب لتهيئة حسابات التشغيل الخارجي.');
        }

        return $parent;
    }

    /**
     * توليد كود فرعي غير مستخدم أسفل الحساب الأب.
     */
    private function freeChildCode(TreeAccount $parent): string
    {
        $base = (string) $parent->code;
        for ($i = 1; $i <= 999; $i++) {
            $code = $base . $i;
            if (! TreeAccount::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        return $base . substr((string) time(), -4);
    }

    public function resolveStockAccount(int $stockId): TreeAccount
    {
        $stock = \App\Models\Stock::query()->findOrFail($stockId);
        $acc = TreeAccount::resolveInventoryAccountForStock($stock);
        if (! $acc) {
            throw new \RuntimeException('المخزن #' . $stockId . ' غير مرتبط بحساب مخزون.');
        }

        return $acc;
    }

    public function ensureSupplierAccount(Supplier $supplier): TreeAccount
    {
        $acc = $this->accountLinking->ensureSupplierAccount($supplier);
        if (! $acc) {
            throw new \RuntimeException('تعذر ربط حساب المعالج الخارجي في شجرة الحسابات.');
        }

        return $acc;
    }
}
