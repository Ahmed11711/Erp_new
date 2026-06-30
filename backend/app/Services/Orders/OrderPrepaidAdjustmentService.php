<?php

namespace App\Services\Orders;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Order;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Services\Accounting\BankOperationalLedgerService;
use App\Services\Accounting\OrderPaymentSourceLedgerService;
use App\Services\Accounting\SafeOperationalLedgerService;
use App\Services\Accounting\SalesOrderAccountingService;
use App\Services\Shipping\OrderFinancialStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * عكس الدفعة المقدمة أو نقلها بين بنك/خزينة/حساب خدمي مع تسوية GL والأرصدة التشغيلية.
 */
final class OrderPrepaidAdjustmentService
{
    private const MOVEMENT_TYPE = 'الطلبات';

    public function __construct(
        private OrderEditAccountingService $orderEditAccounting,
        private SalesOrderAccountingService $salesOrderAccounting,
        private OrderFinancialStateService $financialState,
        private OrderCancellationAccountingService $cancellationAccounting,
    ) {
    }

    public function reversePrepaid(Order $order, int $userId, ?string $note = null): Order
    {
        $this->assertCanAdjust($order);

        $before = $this->orderEditAccounting->snapshotBeforeEdit($order);
        $oldPrepaid = (float) ($before['prepaid_amount'] ?? 0);
        if ($oldPrepaid <= 0.009) {
            throw new \InvalidArgumentException('لا يوجد مبلغ تحت الحساب على هذا الطلب');
        }

        $oldSource = $this->resolveOperationalPrepaidSource($order);
        $oldPaymentType = $this->resolvePaymentType($before, $oldSource);
        $oldSourceId = $oldSource['id'] ?? null;

        OrderEditAccountingService::$suppressPrepaidDeltaJournal = true;

        try {
            $order->prepaid_amount = 0;
            $order->net_total = round((float) ($order->net_total ?? 0) + $oldPrepaid, 2);
            $order->prepaid_payment_type = null;
            $order->bank_id = null;
            $order->save();

            $this->orderEditAccounting->reconcileAfterEdit(
                (int) $order->id,
                $before,
                $userId,
                $oldPaymentType,
                $oldSourceId,
            );

            $this->insertTracking(
                $order,
                'عكس مبلغ تحت الحساب (' . number_format($oldPrepaid, 2) . ')' . ($note ? ' — ' . $note : ''),
                $userId,
            );
        } finally {
            OrderEditAccountingService::$suppressPrepaidDeltaJournal = false;
        }

        return $order->fresh();
    }

    public function changePrepaidSource(
        Order $order,
        int $userId,
        string $paymentType,
        int $sourceId,
        ?string $note = null,
    ): Order {
        $this->assertCanAdjust($order);

        $paymentType = trim($paymentType);
        if (! in_array($paymentType, ['bank', 'safe', 'service_account'], true)) {
            throw new \InvalidArgumentException('نوع مصدر الدفع غير صالح');
        }

        $this->assertSourceExists($paymentType, $sourceId);

        $amount = round((float) ($order->prepaid_amount ?? 0), 2);
        if ($amount <= 0.009) {
            throw new \InvalidArgumentException('لا يوجد مبلغ تحت الحساب على هذا الطلب');
        }

        $before = $this->orderEditAccounting->snapshotBeforeEdit($order);
        $oldSource = $this->resolveOperationalPrepaidSource($order);
        $oldPaymentType = $this->resolvePaymentType($before, $oldSource);
        $oldSourceId = $oldSource['id'] ?? null;

        if ($oldPaymentType === $paymentType && $oldSourceId !== null && (int) $oldSourceId === $sourceId) {
            throw new \InvalidArgumentException('مصدر الدفع الجديد مطابق للمصدر الحالي');
        }

        if ($oldSource === null) {
            throw new \InvalidArgumentException('تعذّر تحديد مصدر الدفع الحالي — استخدم «عكس الدفعة» أو عدّل الطلب من شاشة التعديل');
        }

        OrderEditAccountingService::$suppressPrepaidDeltaJournal = true;

        try {
            if ($oldSource !== null) {
                // أرصدة تشغيلية فقط — قيود ORD-PREPAID تُعاد بناؤها في refreshOrderRecognition
                $this->recordOperationalBalanceOnly(
                    $oldPaymentType,
                    (int) $oldSource['id'],
                    -$amount,
                    $userId,
                    'نقل دفعة مقدمة — إرجاع من مصدر سابق — طلب رقم ' . $order->id,
                    (string) $order->id,
                );
            }

            $order->prepaid_payment_type = $paymentType;
            $order->bank_id = $paymentType === 'bank' ? $sourceId : null;
            $order->save();

            $this->mergeRequestForCashResolution($paymentType, $sourceId);
            $this->salesOrderAccounting->refreshOrderRecognition($order->fresh(), true);

            $this->recordOperationalBalanceOnly(
                $paymentType,
                $sourceId,
                $amount,
                $userId,
                'نقل دفعة مقدمة — إيداع في مصدر جديد — طلب رقم ' . $order->id,
                (string) $order->id,
            );

            $this->financialState->syncFromOrder($order->fresh());

            $label = $this->sourceLabel($paymentType, $sourceId);
            $this->insertTracking(
                $order,
                'تغيير مصدر مبلغ تحت الحساب إلى ' . $label . ($note ? ' — ' . $note : ''),
                $userId,
            );
        } finally {
            OrderEditAccountingService::$suppressPrepaidDeltaJournal = false;
        }

        return $order->fresh();
    }

    public function canAdjust(Order $order): bool
    {
        try {
            $this->assertCanAdjust($order);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public function ineligibilityReason(Order $order): ?string
    {
        try {
            $this->assertCanAdjust($order);

            return null;
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    private function assertCanAdjust(Order $order): void
    {
        $prepaid = round((float) ($order->prepaid_amount ?? 0), 2);
        if ($prepaid <= 0.009) {
            throw new \InvalidArgumentException('لا يوجد مبلغ تحت الحساب على هذا الطلب');
        }

        if (in_array((string) $order->order_status, ['ملغي', 'أرشيف'], true)) {
            throw new \InvalidArgumentException('لا يمكن تعديل الدفعة المقدمة لطلب ملغي أو مؤرشف');
        }

        if ($this->cancellationAccounting->isPrepaidHeldByCollectionIntermediary($order)) {
            $cashSource = $this->resolveOperationalPrepaidSource($order);
            if ($cashSource === null) {
                throw new \InvalidArgumentException(
                    'الدفعة مسجّلة على ذمة شركة تحصيل/شحن بدون مصدر نقدي (بنك/خزينة) — استخدم تسوية الشركة أو إلغاء الطلب'
                );
            }
        }
    }

    /**
     * @return array{type: string, id: int}|null
     */
    private function resolveOperationalPrepaidSource(Order $order): ?array
    {
        $type = trim((string) ($order->prepaid_payment_type ?? ''));
        $bankId = $order->bank_id;

        if ($type === 'bank' && $bankId) {
            return ['type' => 'bank', 'id' => (int) $bankId];
        }

        if ($type === 'safe' && $bankId) {
            return ['type' => 'safe', 'id' => (int) $bankId];
        }

        if ($type === 'service_account' && $bankId) {
            return ['type' => 'service_account', 'id' => (int) $bankId];
        }

        if ($bankId && in_array($type, ['', 'bank'], true)) {
            return ['type' => 'bank', 'id' => (int) $bankId];
        }

        if ($bankId && ! in_array($type, ['pending', 'none'], true)) {
            return ['type' => $type !== '' ? $type : 'bank', 'id' => (int) $bankId];
        }

        $ref = (string) $order->id;

        if (\Illuminate\Support\Facades\Schema::hasTable('safe_details')) {
            $safeRow = DB::table('safe_details')
                ->where('ref', $ref)
                ->where('type', self::MOVEMENT_TYPE)
                ->selectRaw('safe_id, SUM(balance_after - balance_before) as net')
                ->groupBy('safe_id')
                ->havingRaw('ABS(SUM(balance_after - balance_before)) > 0.009')
                ->orderByDesc('net')
                ->first();

            if ($safeRow) {
                return ['type' => 'safe', 'id' => (int) $safeRow->safe_id];
            }
        }

        $safeFromTxn = \App\Models\SafeTransaction::query()
            ->where('notes', 'like', '%(مرجع: '.$ref.')%')
            ->orderByDesc('id')
            ->value('to_safe_id');

        if (! $safeFromTxn) {
            $safeFromTxn = \App\Models\SafeTransaction::query()
                ->where('notes', 'like', '%طلب رقم '.$order->id.'%')
                ->orderByDesc('id')
                ->value('to_safe_id');
        }

        if ($safeFromTxn) {
            return ['type' => 'safe', 'id' => (int) $safeFromTxn];
        }

        $bankRow = DB::table('bank_details')
            ->where('ref', $ref)
            ->where('type', self::MOVEMENT_TYPE)
            ->selectRaw('bank_id, SUM(balance_after - balance_before) as net')
            ->groupBy('bank_id')
            ->havingRaw('ABS(SUM(balance_after - balance_before)) > 0.009')
            ->orderByDesc('net')
            ->first();

        if ($bankRow) {
            return ['type' => 'bank', 'id' => (int) $bankRow->bank_id];
        }

        $bankRowLoose = DB::table('bank_details')
            ->where('ref', $ref)
            ->selectRaw('bank_id, SUM(balance_after - balance_before) as net')
            ->groupBy('bank_id')
            ->havingRaw('ABS(SUM(balance_after - balance_before)) > 0.009')
            ->orderByDesc('net')
            ->first();

        if ($bankRowLoose) {
            return ['type' => 'bank', 'id' => (int) $bankRowLoose->bank_id];
        }

        return $this->resolvePrepaidCashSourceFromJournal($order);
    }

    /**
     * عندما لا يُخزَّن prepaid_payment_type على الطلب لكن القيد يُظهر إيداعاً على بنك/خزينة.
     *
     * @return array{type: string, id: int}|null
     */
    private function resolvePrepaidCashSourceFromJournal(Order $order): ?array
    {
        $pendingAccountId = null;
        try {
            $pendingAccountId = TreeAccount::ensureUnallocatedPrepaidReceiptsAccount()->id;
        } catch (\Throwable) {
            $pendingAccountId = null;
        }

        $patterns = [
            'ORD-PREPAID-' . $order->id . '-%',
            'ORD-OPS-' . $order->id . '-%',
            'PARTCOLLECT-' . $order->id . '-%',
        ];

        $cashAccountIds = AccountEntry::query()
            ->where('order_id', $order->id)
            ->where(function ($query) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $query->orWhere('entry_batch_code', 'like', $pattern);
                }
            })
            ->where('debit', '>', 0.009)
            ->orderByDesc('id')
            ->pluck('tree_account_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($cashAccountIds as $accountId) {
            if ($pendingAccountId !== null && $accountId === (int) $pendingAccountId) {
                continue;
            }

            $resolved = $this->mapTreeAccountToPaymentSource($accountId);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @return array{type: string, id: int}|null
     */
    private function mapTreeAccountToPaymentSource(int $treeAccountId): ?array
    {
        $bank = Bank::query()->where('asset_id', $treeAccountId)->first();
        if ($bank) {
            return ['type' => 'bank', 'id' => (int) $bank->id];
        }

        $safe = Safe::query()->where('account_id', $treeAccountId)->first();
        if ($safe) {
            return ['type' => 'safe', 'id' => (int) $safe->id];
        }

        $service = ServiceAccount::query()->where('account_id', $treeAccountId)->first();
        if ($service) {
            return ['type' => 'service_account', 'id' => (int) $service->id];
        }

        return null;
    }

    /**
     * @param  array{prepaid_payment_type?: string|null, bank_id?: mixed}  $before
     * @param  array{type: string, id: int}|null  $operational
     */
    private function resolvePaymentType(array $before, ?array $operational): string
    {
        if ($operational !== null) {
            return $operational['type'];
        }

        $stored = trim((string) ($before['prepaid_payment_type'] ?? ''));

        return in_array($stored, ['bank', 'safe', 'service_account'], true) ? $stored : 'bank';
    }

    private function assertSourceExists(string $paymentType, int $sourceId): void
    {
        $exists = match ($paymentType) {
            'safe' => Safe::query()->whereKey($sourceId)->exists(),
            'service_account' => ServiceAccount::query()->whereKey($sourceId)->exists(),
            default => Bank::query()->whereKey($sourceId)->exists(),
        };

        if (! $exists) {
            throw new \InvalidArgumentException('مصدر الدفع المحدد غير موجود');
        }
    }

    private function mergeRequestForCashResolution(string $paymentType, int $sourceId): void
    {
        $payload = [
            'payment_type' => $paymentType,
            'bank_id' => null,
            'bank' => null,
            'safe_id' => null,
            'service_account_id' => null,
        ];

        if ($paymentType === 'bank') {
            $payload['bank_id'] = $sourceId;
            $payload['bank'] = $sourceId;
        } elseif ($paymentType === 'safe') {
            $payload['safe_id'] = $sourceId;
        } else {
            $payload['service_account_id'] = $sourceId;
        }

        if (app()->bound('request')) {
            request()->merge($payload);
        } else {
            app()->instance('request', Request::create('/', 'POST', $payload));
        }
    }

    /**
     * تحديث رصيد البنك/الخزينة/الحساب الخدمي فقط (بدون قيود ORD-OPS إضافية).
     */
    private function recordOperationalBalanceOnly(
        string $paymentType,
        int $sourceId,
        float $signedAmount,
        int $userId,
        string $details,
        string $ref,
    ): void {
        if (abs($signedAmount) < 0.009) {
            return;
        }

        $date = now()->toDateString();
        $type = self::MOVEMENT_TYPE;

        if ($paymentType === 'safe') {
            $safe = Safe::find($sourceId);
            if ($safe) {
                app(SafeOperationalLedgerService::class)->recordOperationalMovement(
                    $safe,
                    $signedAmount,
                    $details,
                    $ref,
                    $type,
                    $userId,
                    $date,
                );
            }

            return;
        }

        if ($paymentType === 'service_account') {
            $svc = ServiceAccount::find($sourceId);
            if ($svc) {
                $svc->update(['balance' => round((float) $svc->balance + $signedAmount, 2)]);
            }

            return;
        }

        $bank = Bank::find($sourceId);
        if ($bank) {
            app(BankOperationalLedgerService::class)->recordOperationalMovement(
                $bank,
                $signedAmount,
                $details,
                $ref,
                $type,
                $userId,
                $date,
            );
        }
    }

    private function recordOperationalMovement(
        Order $order,
        string $paymentType,
        int $sourceId,
        float $signedAmount,
        int $userId,
        string $details,
    ): void {
        if (abs($signedAmount) < 0.009) {
            return;
        }

        $ledger = app(OrderPaymentSourceLedgerService::class);
        $ref = (string) $order->id;
        $createdAt = now()->toDateString();

        if ($paymentType === 'safe') {
            $safe = Safe::find($sourceId);
            if ($safe) {
                $ledger->recordSafeMovement(
                    $safe,
                    $signedAmount,
                    $order,
                    $details,
                    $ref,
                    self::MOVEMENT_TYPE,
                    $userId,
                    $createdAt,
                );
            }

            return;
        }

        if ($paymentType === 'service_account') {
            $svc = ServiceAccount::find($sourceId);
            if ($svc) {
                $ledger->recordServiceAccountMovement(
                    $svc,
                    $signedAmount,
                    $order,
                    $details,
                    $ref,
                    self::MOVEMENT_TYPE,
                    $userId,
                    $createdAt,
                );
            }

            return;
        }

        $bank = Bank::find($sourceId);
        if ($bank) {
            $ledger->recordBankMovement(
                $bank,
                $signedAmount,
                $order,
                $details,
                $ref,
                self::MOVEMENT_TYPE,
                $userId,
                $createdAt,
            );
        }
    }

    private function sourceLabel(string $paymentType, int $sourceId): string
    {
        return match ($paymentType) {
            'safe' => Safe::find($sourceId)?->name ?? 'خزينة',
            'service_account' => ServiceAccount::find($sourceId)?->name ?? 'حساب خدمي',
            default => Bank::find($sourceId)?->name ?? 'بنك',
        };
    }

    private function insertTracking(Order $order, string $action, int $userId): void
    {
        $now = now();
        DB::table('trackings')->insert([
            'order_id' => $order->id,
            'action' => $action,
            'user_id' => $userId,
            'date' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
