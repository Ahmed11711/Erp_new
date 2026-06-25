<?php

namespace App\Services\Suppliers;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\Supplier;
use App\Models\TreeAccount;
use App\Services\Accounting\TreeAccountBalanceRebuildService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupplierPurgeService
{
    /** @var array<string, int> */
    private array $counts = [];

    public function __construct(
        private readonly TreeAccountBalanceRebuildService $balanceRebuild,
    ) {}

    /**
     * @return array{supplier_count: int, purchase_count: int, processing_count: int}
     */
    public function preview(): array
    {
        $processingCount = 0;
        if (Schema::hasTable('processing_orders')) {
            $processingCount = (int) DB::table('processing_orders')->count();
        }

        return [
            'supplier_count' => (int) Supplier::query()->count(),
            'purchase_count' => (int) DB::table('purchases')->count(),
            'processing_count' => $processingCount,
        ];
    }

    /**
     * حذف جميع الموردين وحساباتهم في الشجرة والسجلات المرتبطة (مشتريات، سدادات، قيود).
     *
     * @return array<string, int>
     */
    public function purge(bool $includeProcessing = true): array
    {
        $this->counts = [];

        $includeProcessing = $includeProcessing && Schema::hasTable('processing_orders');

        $supplierIds = Supplier::query()->pluck('id');
        $supplierTreeAccountIds = $this->collectSupplierTreeAccountIds($supplierIds);

        DB::transaction(function () use ($supplierIds, $supplierTreeAccountIds, $includeProcessing) {
            if ($includeProcessing) {
                $this->purgeProcessingModule();
            }

            $this->purgeSupplierVouchersAndGl($supplierTreeAccountIds);
            $this->purgePurchasesAndInventoryDocs();
            $this->purgeSupplierFinancials();
            $this->nullifySupplierReferences($supplierIds);
            $this->deleteSupplierTreeAccounts($supplierTreeAccountIds);
            $this->deleteSuppliers($supplierIds);
        });

        $this->balanceRebuild->rebuildAll();

        return $this->counts;
    }

    /**
     * @param  Collection<int, int|string>  $supplierIds
     * @return list<int>
     */
    private function collectSupplierTreeAccountIds(Collection $supplierIds): array
    {
        if ($supplierIds->isEmpty()) {
            return [];
        }

        return Supplier::query()
            ->whereIn('id', $supplierIds)
            ->whereNotNull('tree_account_id')
            ->pluck('tree_account_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function purgeProcessingModule(): void
    {
        if (! Schema::hasTable('processing_orders')) {
            return;
        }

        $this->purgeProcessingDailyEntries();

        if (Schema::hasTable('supplier_pays') && Schema::hasColumn('supplier_pays', 'processing_invoice_id')) {
            $this->countDelete(
                'سدادات موردين (تشغيل خارجي)',
                DB::table('supplier_pays')->whereNotNull('processing_invoice_id')->delete()
            );
        }

        $tables = [
            'processing_activity_logs',
            'processing_invoice_lines',
            'processing_invoices',
            'processing_receipt_lines',
            'processing_receipts',
            'processing_dispatch_lines',
            'processing_dispatch_notes',
            'processing_material_balances',
            'processing_order_lines',
            'processing_orders',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $this->countDelete($table, DB::table($table)->delete());
            }
        }
    }

    private function purgeProcessingDailyEntries(): void
    {
        if (! Schema::hasTable('processing_dispatch_notes') && ! Schema::hasTable('processing_receipts') && ! Schema::hasTable('processing_invoices')) {
            return;
        }

        $dailyEntryIds = collect();
        foreach (['processing_dispatch_notes', 'processing_receipts', 'processing_invoices'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'daily_entry_id')) {
                $dailyEntryIds = $dailyEntryIds->merge(
                    DB::table($table)->whereNotNull('daily_entry_id')->pluck('daily_entry_id')
                );
            }
        }

        $this->deleteDailyEntriesByIds($dailyEntryIds->unique()->filter()->all(), 'قيود يومية (تشغيل خارجي)');
    }

    /**
     * @param  list<int>  $supplierTreeAccountIds
     */
    private function purgeSupplierVouchersAndGl(array $supplierTreeAccountIds): void
    {
        $voucherIds = DB::table('vouchers')
            ->where(function ($q) {
                $q->where('voucher_type', 'supplier')
                    ->orWhereNotNull('supplier_id');
            })
            ->pluck('id')
            ->all();

        if ($voucherIds !== []) {
            $dailyFromVouchers = AccountEntry::query()
                ->whereIn('voucher_id', $voucherIds)
                ->whereNotNull('daily_entry_id')
                ->pluck('daily_entry_id')
                ->all();

            $this->countDelete(
                'قيود دفتر (سندات موردين)',
                AccountEntry::query()->whereIn('voucher_id', $voucherIds)->delete()
            );

            $this->deleteDailyEntriesByIds($dailyFromVouchers, 'قيود يومية (سندات موردين)');
            $this->countDelete('سندات قبض/صرف (موردين)', DB::table('vouchers')->whereIn('id', $voucherIds)->delete());
        }

        if ($supplierTreeAccountIds !== []) {
            $dailyFromSuppliers = AccountEntry::query()
                ->whereIn('tree_account_id', $supplierTreeAccountIds)
                ->whereNotNull('daily_entry_id')
                ->pluck('daily_entry_id')
                ->all();

            $this->countDelete(
                'قيود دفتر (حسابات موردين)',
                AccountEntry::query()->whereIn('tree_account_id', $supplierTreeAccountIds)->delete()
            );

            $this->countDelete(
                'بنود قيود يومية (حسابات موردين)',
                DailyEntryItem::query()->whereIn('account_id', $supplierTreeAccountIds)->delete()
            );

            $this->deleteDailyEntriesByIds($dailyFromSuppliers, 'قيود يومية (حسابات موردين)');
        }

        $this->purgeOrphanDailyEntries();
    }

    private function purgePurchasesAndInventoryDocs(): void
    {
        $purchaseIds = DB::table('purchases')->pluck('id')->all();

        if ($purchaseIds === []) {
            $this->counts['فواتير مشتريات'] = 0;

            return;
        }

        if (Schema::hasTable('warehouse_ratings') && Schema::hasColumn('warehouse_ratings', 'invoice_id')) {
            $this->countDelete(
                'warehouse_ratings (مشتريات)',
                DB::table('warehouse_ratings')->whereIn('invoice_id', $purchaseIds)->delete()
            );
        }

        if (Schema::hasTable('categories_balance')) {
            $invoiceNumbers = DB::table('purchases')->whereIn('id', $purchaseIds)->pluck('invoice_number')->filter()->unique();
            if ($invoiceNumbers->isNotEmpty()) {
                $this->countDelete(
                    'categories_balance (مشتريات)',
                    DB::table('categories_balance')
                        ->whereIn('invoice_number', $invoiceNumbers)
                        ->whereIn('type', ['فواتير مشتريات', 'حذف فواتير مشتريات', 'تعديل فواتير مشتريات'])
                        ->delete()
                );
            }
        }

        if (Schema::hasTable('inventory_movements')) {
            $this->countDelete(
                'inventory_movements (مشتريات)',
                DB::table('inventory_movements')
                    ->where(function ($q) use ($purchaseIds) {
                        $q->where(function ($inner) use ($purchaseIds) {
                            $inner->where('reference_type', 'purchase')
                                ->whereIn('reference_id', $purchaseIds);
                        })->orWhere(function ($inner) use ($purchaseIds) {
                            $inner->where('reference_type', 'purchase_delete')
                                ->whereIn('reference_id', $purchaseIds);
                        });
                    })
                    ->delete()
            );
        }

        if (Schema::hasTable('stock_movements')) {
            $this->countDelete(
                'stock_movements (مشتريات)',
                DB::table('stock_movements')
                    ->where('reference_type', 'purchase')
                    ->whereIn('reference_id', $purchaseIds)
                    ->delete()
            );
        }

        if (Schema::hasTable('stock_transactions')) {
            $stockTxnIds = DB::table('stock_transactions')
                ->where('reference_type', 'purchase')
                ->whereIn('reference_id', $purchaseIds)
                ->pluck('id')
                ->all();

            if ($stockTxnIds !== []) {
                if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'stock_transaction_id')) {
                    DB::table('stock_movements')->whereIn('stock_transaction_id', $stockTxnIds)->delete();
                }
                if (Schema::hasTable('stock_transaction_items')) {
                    $this->countDelete(
                        'stock_transaction_items',
                        DB::table('stock_transaction_items')->whereIn('stock_transaction_id', $stockTxnIds)->delete()
                    );
                }
                $this->countDelete(
                    'stock_transactions (مشتريات)',
                    DB::table('stock_transactions')->whereIn('id', $stockTxnIds)->delete()
                );
            }
        }

        if (Schema::hasTable('shipments') && Schema::hasColumn('shipments', 'purchase_id')) {
            $this->countDelete(
                'shipments (مشتريات)',
                DB::table('shipments')->whereIn('purchase_id', $purchaseIds)->delete()
            );
        }

        if (Schema::hasTable('invoice_categories')) {
            $this->countDelete(
                'invoice_categories',
                DB::table('invoice_categories')->whereIn('purchase_id', $purchaseIds)->delete()
            );
        }

        if (Schema::hasTable('purchases_trackings')) {
            $this->countDelete(
                'purchases_trackings',
                DB::table('purchases_trackings')->whereIn('invoice_id', $purchaseIds)->delete()
            );
        }

        if (Schema::hasTable('supplier_balance')) {
            $this->countDelete(
                'supplier_balance (فواتير)',
                DB::table('supplier_balance')->whereIn('invoice_id', $purchaseIds)->delete()
            );
        }

        if (Schema::hasTable('document_sequences')) {
            DB::table('document_sequences')->where('prefix', 'PUR')->update(['last_number' => 0]);
        }

        $this->countDelete('فواتير مشتريات', DB::table('purchases')->delete());
    }

    private function purgeSupplierFinancials(): void
    {
        if (Schema::hasTable('supplier_balance')) {
            $this->countDelete(
                'supplier_balance (متبقي)',
                DB::table('supplier_balance')->delete()
            );
        }

        if (Schema::hasTable('supplier_pays')) {
            $this->countDelete('supplier_pays', DB::table('supplier_pays')->delete());
        }
    }

    /**
     * @param  Collection<int, int|string>  $supplierIds
     */
    private function nullifySupplierReferences(Collection $supplierIds): void
    {
        if ($supplierIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('cimmitments') && Schema::hasColumn('cimmitments', 'supplier_id')) {
            $this->countUpdate(
                'cimmitments (فك ربط مورد)',
                DB::table('cimmitments')->whereIn('supplier_id', $supplierIds)->update(['supplier_id' => null])
            );
        }
    }

    /**
     * @param  list<int>  $supplierTreeAccountIds
     */
    private function deleteSupplierTreeAccounts(array $supplierTreeAccountIds): void
    {
        if ($supplierTreeAccountIds === []) {
            return;
        }

        $this->countDelete(
            'tree_accounts (موردين)',
            TreeAccount::query()->whereIn('id', $supplierTreeAccountIds)->delete()
        );
    }

    /**
     * @param  Collection<int, int|string>  $supplierIds
     */
    private function deleteSuppliers(Collection $supplierIds): void
    {
        if ($supplierIds->isEmpty()) {
            $this->counts['suppliers'] = 0;

            return;
        }

        $this->countDelete('suppliers', Supplier::query()->whereIn('id', $supplierIds)->delete());
    }

    /**
     * @param  list<int|string>  $dailyEntryIds
     */
    private function deleteDailyEntriesByIds(array $dailyEntryIds, string $label): void
    {
        $dailyEntryIds = array_values(array_unique(array_map('intval', array_filter($dailyEntryIds))));
        if ($dailyEntryIds === []) {
            return;
        }

        AccountEntry::query()->whereIn('daily_entry_id', $dailyEntryIds)->delete();
        DailyEntryItem::query()->whereIn('daily_entry_id', $dailyEntryIds)->delete();
        $this->countDelete($label, DailyEntry::query()->whereIn('id', $dailyEntryIds)->delete());
    }

    private function purgeOrphanDailyEntries(): void
    {
        $orphanIds = DB::table('daily_entries as de')
            ->leftJoin('daily_entry_items as dei', 'dei.daily_entry_id', '=', 'de.id')
            ->leftJoin('account_entries as ae', 'ae.daily_entry_id', '=', 'de.id')
            ->whereNull('dei.id')
            ->whereNull('ae.id')
            ->pluck('de.id')
            ->all();

        $this->deleteDailyEntriesByIds($orphanIds, 'قيود يومية يتيمة');
    }

    private function countDelete(string $label, int $count): void
    {
        $this->counts[$label] = ($this->counts[$label] ?? 0) + $count;
    }

    private function countUpdate(string $label, int $count): void
    {
        $this->counts[$label] = ($this->counts[$label] ?? 0) + $count;
    }
}
