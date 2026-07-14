<?php

namespace App\Services\Suppliers;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\Supplier;
use App\Models\SupplierPay;
use App\Models\TreeAccount;
use App\Services\Accounting\TreeAccountBalanceRebuildService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupplierDeletionService
{
    public function __construct(
        private readonly TreeAccountBalanceRebuildService $balanceRebuild,
    ) {}

    /**
     * @throws \InvalidArgumentException
     */
    public function delete(Supplier $supplier): void
    {
        $blockReason = $this->getBlockReason($supplier);
        if ($blockReason !== null) {
            throw new \InvalidArgumentException($blockReason);
        }

        DB::transaction(function () use ($supplier) {
            $locked = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $blockReason = $this->getBlockReason($locked);
            if ($blockReason !== null) {
                throw new \InvalidArgumentException($blockReason);
            }

            $treeAccountId = $locked->tree_account_id ? (int) $locked->tree_account_id : null;
            $parentId = $treeAccountId
                ? (int) TreeAccount::query()->whereKey($treeAccountId)->value('parent_id')
                : null;

            if (Schema::hasTable('cimmitments') && Schema::hasColumn('cimmitments', 'supplier_id')) {
                DB::table('cimmitments')
                    ->where('supplier_id', $locked->id)
                    ->update(['supplier_id' => null]);
            }

            $locked->delete();

            if ($treeAccountId) {
                $this->purgeTreeAccountLedger($treeAccountId);
                TreeAccount::query()->whereKey($treeAccountId)->forceDelete();

                if ($parentId) {
                    $this->balanceRebuild->rebuildAncestorChain($parentId);
                }
            }
        });
    }

    public function hasBalance(Supplier $supplier): bool
    {
        return abs((float) $supplier->balance) > 0.000001;
    }

    public function getBalanceWarning(Supplier $supplier): ?string
    {
        if (! $this->hasBalance($supplier)) {
            return null;
        }

        return 'المورد «'.$supplier->supplier_name.'» له رصيد '.number_format((float) $supplier->balance, 2).' — سيُحذف مع حسابه في الشجرة.';
    }

    public function getBlockReason(Supplier $supplier): ?string
    {
        if ($supplier->purchases()->exists()) {
            return 'لا يمكن حذف مورد مرتبط بفواتير مشتريات.';
        }

        if (SupplierPay::query()->where('supplier_id', $supplier->id)->exists()) {
            return 'لا يمكن حذف مورد له سجلات سداد.';
        }

        if (Schema::hasTable('vouchers') && DB::table('vouchers')->where('supplier_id', $supplier->id)->exists()) {
            return 'لا يمكن حذف مورد مرتبط بسندات قبض/صرف.';
        }

        if (Schema::hasTable('processing_orders')
            && DB::table('processing_orders')->where('supplier_id', $supplier->id)->exists()) {
            return 'لا يمكن حذف مورد مرتبط بأوامر تشغيل خارجي.';
        }

        if (Schema::hasTable('processing_invoices')
            && DB::table('processing_invoices')->where('supplier_id', $supplier->id)->exists()) {
            return 'لا يمكن حذف مورد مرتبط بفواتير تشغيل خارجي.';
        }

        if (! $supplier->tree_account_id) {
            return null;
        }

        $treeAccount = TreeAccount::query()->find((int) $supplier->tree_account_id);
        if ($treeAccount && $treeAccount->children()->exists()) {
            return 'لا يمكن حذف حساب المورد لوجود حسابات فرعية تحته.';
        }

        return null;
    }

    private function purgeTreeAccountLedger(int $treeAccountId): void
    {
        $dailyEntryIds = AccountEntry::query()
            ->where('tree_account_id', $treeAccountId)
            ->whereNotNull('daily_entry_id')
            ->pluck('daily_entry_id')
            ->all();

        AccountEntry::query()->where('tree_account_id', $treeAccountId)->delete();

        if (Schema::hasTable('daily_entry_items')) {
            DailyEntryItem::query()->where('account_id', $treeAccountId)->delete();
        }

        $dailyEntryIds = array_values(array_unique(array_map('intval', array_filter($dailyEntryIds))));
        if ($dailyEntryIds === []) {
            return;
        }

        AccountEntry::query()->whereIn('daily_entry_id', $dailyEntryIds)->delete();
        DailyEntryItem::query()->whereIn('daily_entry_id', $dailyEntryIds)->delete();
        DailyEntry::query()->whereIn('id', $dailyEntryIds)->delete();
    }

    /**
     * @param  list<int>  $supplierIds
     * @return array{deleted: list<int>, failed: list<array{id: int, message: string}>}
     */
    public function deleteMany(array $supplierIds): array
    {
        $results = [
            'deleted' => [],
            'failed' => [],
        ];

        foreach (array_values(array_unique(array_map('intval', $supplierIds))) as $id) {
            if ($id <= 0) {
                continue;
            }

            $supplier = Supplier::query()->find($id);
            if (! $supplier) {
                $results['failed'][] = [
                    'id' => $id,
                    'message' => 'المورد غير موجود',
                ];
                continue;
            }

            try {
                $this->delete($supplier);
                $results['deleted'][] = $id;
            } catch (\Throwable $e) {
                $results['failed'][] = [
                    'id' => $id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
