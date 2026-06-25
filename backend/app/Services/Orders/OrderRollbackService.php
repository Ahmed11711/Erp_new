<?php

namespace App\Services\Orders;

use App\Enums\OrderCollectionStatus;
use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderRollbackTarget;
use App\Enums\OrderSettlementStatus;
use App\Models\Bank;
use App\Models\Category;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\OrderRollbackAudit;
use App\Models\Shipment;
use App\Models\shippingCompanyDetails;
use App\Models\User;
use App\Services\Accounting\BankOperationalLedgerService;
use App\Services\Accounting\SafeOperationalLedgerService;
use App\Services\Shipping\OrderFinancialStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OrderRollbackService
{
    /** @var list<string> */
    public const ELIGIBLE_STATUSES = ['تم التسليم', 'تم التحصيل', 'تم الاستلام'];

    private const MOVEMENT_TYPE = 'الطلبات';

    public function __construct(
        private OrderRollbackAccountingService $accounting,
        private OrderRollbackInventoryService $inventory,
        private OrderStatusHistoryService $statusHistory,
    ) {
    }

    public function isEligible(Order $order): bool
    {
        return in_array($order->order_status, self::ELIGIBLE_STATUSES, true);
    }

    /**
     * @return array{
     *   order_id: int,
     *   current_status: string,
     *   target_status: string,
     *   target_key: string,
     *   operations: list<array{key: string, label: string, checked: bool}>
     * }
     */
    public function preview(Order $order, OrderRollbackTarget $target): array
    {
        $order->loadMissing(['order_details', 'order_products']);

        return [
            'order_id' => (int) $order->id,
            'current_status' => (string) $order->order_status,
            'target_status' => $target->orderStatusLabel(),
            'target_key' => $target->value,
            'operations' => $this->buildOperationChecklist($order),
        ];
    }

    /**
     * @return array{
     *   message: string,
     *   order_id: int,
     *   previous_status: string,
     *   new_status: string,
     *   audit_id: int
     * }
     */
    public function execute(Order $order, OrderRollbackTarget $target, string $reason, int $userId): array
    {
        if (! $this->isEligible($order)) {
            throw new \RuntimeException(
                'لا يمكن إعادة فتح الطلب من الحالة الحالية: ' . $order->order_status
            );
        }

        if (trim($reason) === '') {
            throw new \RuntimeException('سبب إعادة الفتح مطلوب.');
        }

        $order->loadMissing(['order_details', 'order_products']);
        $previousStatus = (string) $order->order_status;
        $actor = User::query()->find($userId)?->name ?? 'النظام';

        return DB::transaction(function () use ($order, $target, $reason, $userId, $previousStatus, $actor) {
            $locked = Order::query()->lockForUpdate()->with(['order_details', 'order_products'])->findOrFail($order->id);

            if (! $this->isEligible($locked)) {
                throw new \RuntimeException('تغيّرت حالة الطلب — أعد تحميل الصفحة.');
            }

            $reversedAccounting = [];
            $reversedInventory = [];
            $reversedOperations = [];

            if ($this->wasCollected($locked)) {
                $reversedAccounting = array_merge(
                    $reversedAccounting,
                    $this->accounting->reverseCollectionGl($locked, $userId, $reason)
                );
                $reversedInventory = array_merge(
                    $reversedInventory,
                    $this->inventory->reverseCollectInventory($locked, $actor)
                );
                $reversedOperations = array_merge(
                    $reversedOperations,
                    $this->reverseCollectedCompanyCustomerBalance($locked, $userId)
                );
            }

            if ($this->wasDelivered($locked, $previousStatus)) {
                $reversedAccounting = array_merge(
                    $reversedAccounting,
                    $this->accounting->reverseDeliveryTransfer($locked, $userId, $reason)
                );
            }

            if ($previousStatus === 'تم الاستلام') {
                $reversedOperations = array_merge(
                    $reversedOperations,
                    $this->reverseMaintenanceReceipt($locked, $actor)
                );
            }

            $reversedOperations = array_merge(
                $reversedOperations,
                $this->reverseShippingCompanyOperationalBalances($locked, $actor)
            );

            $reversedInventory = array_merge(
                $reversedInventory,
                $this->inventory->reverseShipInventory($locked, $actor)
            );

            $reversedAccounting = array_merge(
                $reversedAccounting,
                $this->accounting->reverseCogsEntries($locked, $userId, $reason)
            );

            $reversedAccounting = array_merge(
                $reversedAccounting,
                $this->accounting->reverseCourierCost($locked, $userId, $reason)
            );

            $reversedOperations = array_merge(
                $reversedOperations,
                $this->markShipmentsReopened($locked, $reason)
            );

            $reversedOperations = array_merge(
                $reversedOperations,
                $this->reverseOperationalCollectionCash($locked, $userId, $reason)
            );

            $this->resetOrderDetails($locked->order_details, $target);
            $newStatus = $target->orderStatusLabel();

            $this->statusHistory->record(
                (int) $locked->id,
                $previousStatus,
                $newStatus,
                $reason,
                $userId,
            );

            OrderStatusHistoryService::$suppressAutoRecord = true;
            $locked->order_status = $newStatus;
            if ($locked->shipping_partner_status) {
                $locked->shipping_partner_status = 'reopened';
            }
            $locked->save();
            OrderStatusHistoryService::$suppressAutoRecord = false;

            app(OrderFinancialStateService::class)->syncFromOrder($locked->fresh(['order_details']));

            Note::create([
                'order_id' => $locked->id,
                'user_id' => $userId,
                'note' => $reason,
                'added_from' => 'إعادة فتح الطلب',
                'is_problem' => false,
            ]);

            DB::table('trackings')->insert([
                'order_id' => $locked->id,
                'action' => 'إعادة فتح الطلب — ' . $previousStatus . ' → ' . $newStatus,
                'date' => now()->toDateString(),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $audit = OrderRollbackAudit::create([
                'order_id' => $locked->id,
                'previous_status' => $previousStatus,
                'target_status' => $newStatus,
                'reason' => $reason,
                'performed_by' => $userId,
                'reversed_accounting' => $reversedAccounting,
                'reversed_inventory' => $reversedInventory,
                'reversed_operations' => $reversedOperations,
            ]);

            $this->notifyAdminIfNonAdminActor($locked, $previousStatus, $newStatus, $reason, $userId);

            return [
                'message' => 'تم إعادة فتح الطلب بنجاح.',
                'order_id' => (int) $locked->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'audit_id' => (int) $audit->id,
            ];
        });
    }

    /**
     * @return list<array{key: string, label: string, checked: bool}>
     */
    private function buildOperationChecklist(Order $order): array
    {
        $ops = [];

        if ($this->wasCollected($order)) {
            $ops[] = ['key' => 'collection', 'label' => 'عكس التحصيل (ذمم + نقدية + شركات الشحن)', 'checked' => true];
            $ops[] = ['key' => 'collect_inventory', 'label' => 'عكس حركات مخزون التحصيل/الاستلام', 'checked' => true];
        }

        if ($this->wasDelivered($order, $order->order_status)) {
            $ops[] = ['key' => 'delivery', 'label' => 'عكس قيود تأكيد التسليم (DELIVERY-*)', 'checked' => true];
        }

        if ($order->order_status === 'تم الاستلام') {
            $ops[] = ['key' => 'maintenance_receipt', 'label' => 'عكس استلام الصيانة', 'checked' => true];
        }

        $ops[] = ['key' => 'shipping_details', 'label' => 'عكس سطور شركات الشحن التشغيلية', 'checked' => true];
        $ops[] = ['key' => 'ship_inventory', 'label' => 'إرجاع المخزون المخصوم عند الشحن', 'checked' => true];
        $ops[] = ['key' => 'cogs', 'label' => 'عكس قيود تكلفة البضاعة المباعة', 'checked' => true];

        if (Shipment::query()->where('order_id', $order->id)->whereNull('reopened_at')->exists()) {
            $ops[] = ['key' => 'courier_shipment', 'label' => 'إلغاء/إعادة فتح سجل الشحنة + عكس تكلفة المندوب', 'checked' => true];
        } elseif ((float) ($order->courier_shipping_cost ?? 0) > 0) {
            $ops[] = ['key' => 'courier_cost', 'label' => 'عكس قيود تكلفة المندوب (SHIP-COST-*)', 'checked' => true];
        }

        $ops[] = ['key' => 'status_history', 'label' => 'تسجيل في سجل تاريخ الحالات', 'checked' => true];
        $ops[] = ['key' => 'audit_log', 'label' => 'إنشاء سجل تدقيق كامل للعملية', 'checked' => true];

        return $ops;
    }

    private function wasCollected(Order $order): bool
    {
        if ($order->order_status === 'تم التحصيل') {
            return true;
        }

        return shippingCompanyDetails::query()
            ->where('order_id', $order->id)
            ->where('status', 'تم التحصيل')
            ->exists();
    }

    private function wasDelivered(Order $order, string $status): bool
    {
        if (in_array($status, ['تم التسليم', 'تم التحصيل'], true)) {
            return true;
        }

        return shippingCompanyDetails::query()
            ->where('order_id', $order->id)
            ->whereIn('status', ['تم التسليم', 'تم التحصيل'])
            ->exists()
            || filled($order->order_details?->delivery_batch_code);
    }

    /**
     * عكس الرصيد التشغيلي (مديونية) على شركات الشحن/التحصيل لهذا الطلب.
     *
     * @return list<array<string, mixed>>
     */
    private function reverseShippingCompanyOperationalBalances(Order $order, string $actor): array
    {
        $ops = [];
        $shippingDate = $order->order_details?->shipping_date ?? now()->toDateString();

        $rows = shippingCompanyDetails::query()
            ->where('order_id', $order->id)
            ->whereNotIn('status', ['إعادة فتح', 'إلغاء طلب', 'رفض استلام'])
            ->get();

        if ($rows->isEmpty()) {
            return $ops;
        }

        /** @var array<int, float> $netByCompany */
        $netByCompany = [];
        foreach ($rows as $row) {
            $companyId = (int) $row->shipping_company_id;
            $netByCompany[$companyId] = ($netByCompany[$companyId] ?? 0.0) + (float) $row->amount;
        }

        foreach ($netByCompany as $companyId => $net) {
            $net = round((float) $net, 3);
            if (abs($net) < 0.009) {
                continue;
            }

            DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                $companyId,
                $order->id,
                $shippingDate,
                'إعادة فتح طلب',
                -$net,
                $actor,
                now(),
            ]);

            $ops[] = [
                'type' => 'shipping_company_net_reversal',
                'shipping_company_id' => $companyId,
                'net_reversed' => -$net,
                'status' => 'إعادة فتح طلب',
            ];
        }

        foreach ($rows as $row) {
            $row->status = 'إعادة فتح';
            $row->is_done = 1;
            $row->collect_date = null;
            $row->save();
        }

        return $ops;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reverseCollectedCompanyCustomerBalance(Order $order, int $userId): array
    {
        $ops = [];

        if ($order->customer_type !== 'شركة' || ! $order->company_id) {
            return $ops;
        }

        $net = (float) ($order->net_total ?? 0);
        if ($net <= 0.0001) {
            return $ops;
        }

        DB::statement('CALL update_customer_company_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
            $order->company_id,
            number_format($net, 3, '.', ''),
            null,
            (string) $order->id,
            'إعادة فتح طلب — عكس تحصيل — طلب رقم ' . $order->id,
            self::MOVEMENT_TYPE,
            $userId,
            now(),
        ]);

        $ops[] = [
            'type' => 'customer_company_balance',
            'company_id' => (int) $order->company_id,
            'amount' => $net,
        ];

        return $ops;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reverseMaintenanceReceipt(Order $order, string $actor): array
    {
        $ops = [];
        $products = OrderProduct::query()->where('order_id', $order->id)->get();

        foreach ($products as $op) {
            $rows = DB::table('categories_balance')
                ->where('ref', $order->id)
                ->where('type', 'صيانة')
                ->where('category_id', $op->category_id)
                ->get();

            foreach ($rows as $row) {
                $cat = Category::query()->lockForUpdate()->find((int) $row->category_id);
                if ($cat && (float) $row->quantity > 0) {
                    $cat->quantity = max(0, (float) $cat->quantity - (float) $row->quantity);
                    $cat->save();
                }

                DB::table('categories_balance')->insert([
                    'invoice_number' => 0,
                    'category_id' => (int) $row->category_id,
                    'type' => 'عكس استلام صيانة',
                    'quantity' => -(float) $row->quantity,
                    'balance_before' => $cat?->quantity ?? 0,
                    'balance_after' => $cat?->quantity ?? 0,
                    'price' => 0,
                    'total_price' => 0,
                    'by' => $actor,
                    'ref' => $order->id,
                    'created_at' => now(),
                ]);

                $ops[] = [
                    'type' => 'maintenance_receipt_reversal',
                    'category_id' => (int) $row->category_id,
                    'quantity' => (float) $row->quantity,
                ];
            }
        }

        if ($order->order_details) {
            $order->order_details->receiving_date = null;
            $order->order_details->save();
        }

        return $ops;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function markShipmentsReopened(Order $order, string $reason): array
    {
        $ops = [];
        $shipments = Shipment::query()->where('order_id', $order->id)->whereNull('reopened_at')->get();

        foreach ($shipments as $shipment) {
            $shipment->reopened_at = now();
            $shipment->reopen_reason = $reason;
            $shipment->payment_status = 'cancelled';
            $shipment->save();

            $ops[] = [
                'type' => 'shipment_reopened',
                'shipment_id' => (int) $shipment->id,
                'shipping_company_id' => (int) $shipment->shipping_company_id,
            ];
        }

        return $ops;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reverseOperationalCollectionCash(Order $order, int $userId, string $reason): array
    {
        if (! $this->wasCollected($order)) {
            return [];
        }

        $ops = [];
        $ref = (string) $order->id;
        $details = 'إعادة فتح طلب — عكس تحصيل تشغيلي — طلب رقم ' . $order->id;

        $bankRows = DB::table('bank_details')
            ->where('ref', $ref)
            ->where('type', self::MOVEMENT_TYPE)
            ->where('details', 'like', '%تحصيل%')
            ->get();

        $bankTotals = [];
        foreach ($bankRows as $row) {
            $bankId = (int) $row->bank_id;
            $bankTotals[$bankId] = ($bankTotals[$bankId] ?? 0) + ((float) $row->balance_after - (float) $row->balance_before);
        }

        foreach ($bankTotals as $bankId => $net) {
            $net = round((float) $net, 2);
            if (abs($net) < 0.009) {
                continue;
            }

            $bank = Bank::find($bankId);
            if (! $bank) {
                continue;
            }

            app(BankOperationalLedgerService::class)->recordOperationalMovement(
                $bank,
                -$net,
                $details,
                $ref,
                self::MOVEMENT_TYPE,
                $userId,
                now()->toDateString(),
            );

            $ops[] = [
                'type' => 'bank_operational_reversal',
                'bank_id' => $bankId,
                'amount' => -$net,
            ];
        }

        $safeRows = collect();
        if (Schema::hasTable('safe_details')) {
            $safeRows = DB::table('safe_details')
                ->where('ref', $ref)
                ->where('type', self::MOVEMENT_TYPE)
                ->where('details', 'like', '%تحصيل%')
                ->get();
        }

        $safeTotals = [];
        foreach ($safeRows as $row) {
            $safeId = (int) $row->safe_id;
            $safeTotals[$safeId] = ($safeTotals[$safeId] ?? 0) + ((float) $row->balance_after - (float) $row->balance_before);
        }

        foreach ($safeTotals as $safeId => $net) {
            $net = round((float) $net, 2);
            if (abs($net) < 0.009) {
                continue;
            }

            $safe = \App\Models\Safe::find($safeId);
            if (! $safe) {
                continue;
            }

            app(SafeOperationalLedgerService::class)->recordOperationalMovement(
                $safe,
                -$net,
                $details,
                $ref,
                self::MOVEMENT_TYPE,
                $userId,
                now()->toDateString(),
            );

            $ops[] = [
                'type' => 'safe_operational_reversal',
                'safe_id' => $safeId,
                'amount' => -$net,
            ];
        }

        return $ops;
    }

    private function notifyAdminIfNonAdminActor(
        Order $order,
        string $previousStatus,
        string $newStatus,
        string $reason,
        int $actorUserId,
    ): void {
        $actor = User::query()->find($actorUserId);
        if (! $actor) {
            return;
        }

        $dept = trim((string) ($actor->department ?? ''));
        if (in_array($dept, ['Admin', 'admin'], true)) {
            return;
        }

        $admin = User::query()->whereIn('department', ['Admin', 'admin'])->first();
        if (! $admin || (int) $admin->id === $actorUserId) {
            return;
        }

        Notification::create([
            'send_from' => $actorUserId,
            'send_to' => $admin->id,
            'type' => 'إعادة فتح طلب',
            'ref' => $order->id,
            'order_id' => $order->id,
            'note' => 'إعادة فتح طلب رقم ' . $order->id
                . ' — من «' . $previousStatus . '» إلى «' . $newStatus . '»'
                . ' — السبب: ' . $reason,
        ]);
    }

    private function resetOrderDetails(?OrderDetails $od, OrderRollbackTarget $target): void
    {
        if (! $od) {
            return;
        }

        $od->delivery_date = null;
        $od->collection_date = null;
        $od->shipping_date = null;
        $od->delivered_by_user_id = null;
        $od->delivery_batch_code = null;
        $od->liability_transferred_at = null;
        $od->liability_holder_type = null;
        $od->liability_holder_id = null;
        $od->shipping_receivable_amount = null;
        $od->collection_receivable_amount = null;
        $od->delivery_status = OrderDeliveryStatus::Pending->value;
        $od->collection_status = OrderCollectionStatus::NotRequired->value;
        $od->settlement_status = OrderSettlementStatus::NotApplicable->value;
        $od->amount_to_collect = 0;
        $od->remaining_amount = 0;
        $od->status_date = now()->format('Y-m-d');
        $od->reviewed = 0;

        if ($target === OrderRollbackTarget::New) {
            $od->confirm_date = null;
            $od->need_by_date = null;
        }

        $od->save();
    }
}
