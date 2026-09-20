<?php

namespace App\Services\Processing;

use App\Enums\InventoryMovementType;
use App\Enums\ProcessingDocumentStatus;
use App\Enums\ProcessingOrderStatus;
use App\Models\AccountEntry;
use App\Models\Category;
use App\Models\CategoryCostHistory;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\InventoryMovement;
use App\Models\ProcessingDispatchNote;
use App\Models\ProcessingInvoice;
use App\Models\ProcessingMaterialBalance;
use App\Models\ProcessingOrder;
use App\Models\ProcessingOrderLine;
use App\Models\ProcessingReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\CategoryInventoryCostService;
use App\Services\Inventory\InventoryMovementLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حذف أمر تشغيل خارجي وعكس المخزون والقيود وذمة المورد كأن الأمر لم يُنفَّذ.
 */
class ProcessingOrderReversalService
{
    public function __construct(
        private InventoryMovementLedgerService $ledger,
        private AccountingService $accountingService,
        private ProcessingActivityLogger $activityLogger,
    ) {
    }

    /**
     * @return array{message: string, order_id: int, deleted_by: int}
     */
    public function deleteAndReverse(int $orderId, int $deletedByUserId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($orderId, $deletedByUserId, $reason) {
            $order = ProcessingOrder::query()
                ->lockForUpdate()
                ->with([
                    'lines',
                    'dispatchNotes',
                    'receipts.lines',
                    'invoices',
                    'materialBalances',
                ])
                ->find($orderId);

            if (! $order) {
                throw new \RuntimeException('أمر التشغيل غير موجود أو تم حذفه مسبقاً.');
            }

            if ($order->status === ProcessingOrderStatus::Cancelled->value || $order->cancelled_at) {
                throw new \RuntimeException('أمر التشغيل ملغى مسبقاً.');
            }

            $this->assertNoPayments($order);

            $actor = User::query()->find($deletedByUserId)?->name ?? 'النظام';
            $reasonText = trim((string) ($reason ?: 'حذف أمر تشغيل خارجي'));

            foreach ($order->receipts()->orderByDesc('id')->get() as $receipt) {
                $this->reverseReceipt($receipt, $order, $deletedByUserId, $actor);
            }

            foreach ($order->invoices()->orderByDesc('id')->get() as $invoice) {
                $this->reverseInvoice($invoice, $order, $deletedByUserId, $actor);
            }

            foreach ($order->dispatchNotes()->orderByDesc('id')->get() as $dispatch) {
                $this->reverseDispatch($dispatch, $order, $deletedByUserId, $actor);
            }

            ProcessingMaterialBalance::query()
                ->where('processing_order_id', $order->id)
                ->delete();

            foreach ($order->lines as $line) {
                $line->dispatched_qty = 0;
                $line->received_good_qty = 0;
                $line->received_damaged_qty = 0;
                $line->received_rejected_qty = 0;
                $line->save();
            }

            $order->status = ProcessingOrderStatus::Cancelled->value;
            $order->cancelled_at = now();
            $order->cancellation_reason = $reasonText;
            $order->total_dispatched_qty = 0;
            $order->total_received_qty = 0;
            $order->total_service_cost = 0;
            $order->save();
            $order->delete();

            $this->activityLogger->logOrder($order, 'deleted', null, [
                'reason' => $reasonText,
                'deleted_by' => $deletedByUserId,
            ]);

            return [
                'message' => 'تم حذف أمر التشغيل وعكس المخزون والقيود بنجاح.',
                'order_id' => $orderId,
                'deleted_by' => $deletedByUserId,
            ];
        });
    }

    private function assertNoPayments(ProcessingOrder $order): void
    {
        foreach ($order->invoices as $invoice) {
            if ((float) $invoice->paid_amount > 0.00001) {
                throw new \RuntimeException(
                    'لا يمكن حذف الأمر لوجود سداد على فاتورة التشغيل «' . $invoice->invoice_number . '». ألغِ السداد أولاً.'
                );
            }

            if (
                Schema::hasTable('supplier_pays')
                && Schema::hasColumn('supplier_pays', 'processing_invoice_id')
                && DB::table('supplier_pays')->where('processing_invoice_id', $invoice->id)->exists()
            ) {
                throw new \RuntimeException(
                    'لا يمكن حذف الأمر لوجود حركات سداد مرتبطة بفاتورة «' . $invoice->invoice_number . '».'
                );
            }
        }
    }

    private function reverseReceipt(
        ProcessingReceipt $receipt,
        ProcessingOrder $order,
        int $userId,
        string $actor
    ): void {
        $receipt->loadMissing('lines');

        if ($receipt->status === ProcessingDocumentStatus::Posted->value) {
            $movements = InventoryMovement::query()
                ->where('reference_type', 'processing_receipt')
                ->where('reference_id', $receipt->id)
                ->where('reference_type', '!=', 'processing_order_reversal')
                ->orderByDesc('id')
                ->get();

            foreach ($movements as $mov) {
                $this->reverseInventoryMovement(
                    $mov,
                    'processing_order_reversal',
                    (int) $order->id,
                    'عكس استلام — ' . $receipt->receipt_number,
                    $actor
                );
            }

            foreach ($receipt->lines as $line) {
                $this->restoreDestinationCostFromHistory($receipt, $line);
                $orderLine = ProcessingOrderLine::query()->lockForUpdate()->find($line->processing_order_line_id);
                if ($orderLine) {
                    $orderLine->received_good_qty = max(0, (float) $orderLine->received_good_qty - (float) $line->good_qty);
                    $orderLine->received_damaged_qty = max(0, (float) $orderLine->received_damaged_qty - (float) $line->damaged_qty);
                    $orderLine->received_rejected_qty = max(0, (float) $orderLine->received_rejected_qty - (float) $line->rejected_qty);
                    $orderLine->save();
                }

                $consumed = (float) $line->good_qty + (float) $line->damaged_qty + (float) $line->rejected_qty;
                $bal = ProcessingMaterialBalance::query()->where([
                    'processing_order_id' => $order->id,
                    'category_id' => $line->category_id,
                ])->first();
                if ($bal) {
                    $bal->qty_at_vendor = (float) $bal->qty_at_vendor + $consumed;
                    $bal->save();
                }
            }

            if ($receipt->daily_entry_id) {
                $entry = DailyEntry::query()->find((int) $receipt->daily_entry_id);
                if ($entry) {
                    $this->reverseSingleDailyEntry($entry, (int) $order->id, $userId, 'عكس استلام تشغيل خارجي');
                }
            }
        }

        $receipt->status = ProcessingDocumentStatus::Cancelled->value;
        $receipt->save();
        $receipt->delete();

        $this->activityLogger->log('processing_receipt', (int) $receipt->id, 'reversed');
    }

    private function restoreDestinationCostFromHistory(ProcessingReceipt $receipt, $line): void
    {
        if (! $line->destination_category_id) {
            return;
        }

        $history = CategoryCostHistory::query()
            ->where('source_type', 'processing_receipt')
            ->where('source_id', $receipt->id)
            ->where('source_line_id', $line->id)
            ->orderByDesc('id')
            ->first();

        if (! $history) {
            return;
        }

        $cat = Category::query()->lockForUpdate()->find((int) $history->category_id);
        if (! $cat) {
            return;
        }

        $qty = (float) ($cat->quantity ?? 0);
        $oldQty = (float) ($history->old_qty ?? 0);

        if (abs($qty - $oldQty) <= 0.0001) {
            $cat->unit_price = round((float) $history->old_unit_cost, 4);
            $cat->total_price = round((float) $history->old_total_cost, 4);
            $cat->save();
        } else {
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $cat->id);
        }
    }

    private function reverseInvoice(
        ProcessingInvoice $invoice,
        ProcessingOrder $order,
        int $userId,
        string $actor
    ): void {
        if (in_array($invoice->status, ['posted', 'partially_paid', 'paid'], true)) {
            $amount = (float) $invoice->grand_total;
            $supplier = Supplier::query()->lockForUpdate()->find($invoice->supplier_id);
            if ($supplier && $amount > 0.00001) {
                $before = (float) $supplier->balance;
                $supplier->last_balance = $before;
                $supplier->balance = $before - $amount;
                $supplier->save();

                if (Schema::hasTable('supplier_balance')) {
                    DB::table('supplier_balance')->insert([
                        'invoice_id' => null,
                        'supplierpay_id' => null,
                        'processing_invoice_id' => $invoice->id,
                        'balance_before' => $before,
                        'balance_after' => (float) $supplier->balance,
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $order->total_service_cost = max(0, (float) $order->total_service_cost - $amount);
            $order->save();

            if ($invoice->daily_entry_id) {
                $entry = DailyEntry::query()->find((int) $invoice->daily_entry_id);
                if ($entry) {
                    $this->reverseSingleDailyEntry($entry, (int) $order->id, $userId, 'عكس فاتورة تشغيل خارجي');
                }
            }
        }

        $invoice->status = 'cancelled';
        $invoice->save();
        $invoice->delete();

        $this->activityLogger->log('processing_invoice', (int) $invoice->id, 'reversed', null, [
            'by' => $actor,
        ]);
    }

    private function reverseDispatch(
        ProcessingDispatchNote $dispatch,
        ProcessingOrder $order,
        int $userId,
        string $actor
    ): void {
        $dispatch->loadMissing('lines');

        if ($dispatch->status === ProcessingDocumentStatus::Posted->value) {
            $movements = InventoryMovement::query()
                ->where('reference_type', 'processing_dispatch_note')
                ->where('reference_id', $dispatch->id)
                ->orderByDesc('id')
                ->get();

            foreach ($movements as $mov) {
                $this->reverseInventoryMovement(
                    $mov,
                    'processing_order_reversal',
                    (int) $order->id,
                    'عكس صرف — ' . $dispatch->dispatch_number,
                    $actor
                );
            }

            foreach ($dispatch->lines as $dLine) {
                $orderLine = ProcessingOrderLine::query()->lockForUpdate()->find($dLine->processing_order_line_id);
                if ($orderLine) {
                    $orderLine->dispatched_qty = max(0, (float) $orderLine->dispatched_qty - (float) $dLine->quantity);
                    $orderLine->save();
                }
            }

            if ($dispatch->daily_entry_id) {
                $entry = DailyEntry::query()->find((int) $dispatch->daily_entry_id);
                if ($entry) {
                    $this->reverseSingleDailyEntry($entry, (int) $order->id, $userId, 'عكس إذن صرف تشغيل خارجي');
                }
            }
        }

        $dispatch->status = ProcessingDocumentStatus::Cancelled->value;
        $dispatch->save();
        $dispatch->delete();

        $this->activityLogger->log('processing_dispatch_note', (int) $dispatch->id, 'reversed');
    }

    private function reverseInventoryMovement(
        InventoryMovement $mov,
        string $refType,
        int $refId,
        string $reason,
        string $actor
    ): void {
        $cat = Category::query()->lockForUpdate()->find((int) $mov->category_id);
        if (! $cat) {
            return;
        }

        $qty = (float) $mov->quantity;
        if ($qty <= 0.0000001) {
            return;
        }

        $uc = (float) $mov->unit_cost;
        $tc = (float) $mov->total_cost;
        $type = InventoryMovementType::tryFrom((string) $mov->movement_type)
            ?? InventoryMovementType::ManualAdjustment;

        if ($mov->direction === 'out') {
            $this->ledger->recordInbound(
                $cat,
                $type,
                $qty,
                $uc,
                $tc,
                true,
                $refType,
                $refId,
                $reason,
                null,
                $actor
            );
        } else {
            $this->ledger->recordOutbound(
                $cat,
                $type,
                $qty,
                $uc,
                $tc,
                true,
                $refType,
                $refId,
                $reason,
                null,
                $actor,
                true
            );
        }
    }

    private function reverseSingleDailyEntry(
        DailyEntry $original,
        int $orderId,
        int $userId,
        string $label
    ): void {
        $items = DailyEntryItem::query()->where('daily_entry_id', $original->id)->get();
        if ($items->isEmpty()) {
            return;
        }

        // تجنب عكس القيد مرتين
        $already = DailyEntry::query()
            ->where('description', 'like', 'عكس — ' . $original->description . '%')
            ->exists();
        if ($already) {
            return;
        }

        $reversal = DailyEntry::create([
            'date' => now(),
            'entry_number' => DailyEntry::getNextEntryNumber(),
            'description' => 'عكس — ' . $original->description,
            'user_id' => $userId,
        ]);

        $affected = [];
        foreach ($items as $line) {
            DailyEntryItem::create([
                'daily_entry_id' => $reversal->id,
                'account_id' => $line->account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'notes' => $label . ' #' . $orderId,
            ]);

            AccountEntry::create([
                'tree_account_id' => $line->account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'description' => $reversal->description,
                'daily_entry_id' => $reversal->id,
            ]);

            $affected[(int) $line->account_id] = true;
        }

        foreach (array_keys($affected) as $accountId) {
            $this->accountingService->updateAccountHierarchyBalances($accountId);
        }
    }
}
