<?php

namespace App\Services\Accounting;

use App\Models\Bank;
use App\Models\Order;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\Shipment;
use App\Models\ShippingCompany;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;

/**
 * Outbound courier cost: Dr freight-out expense, Cr courier payable or cash.
 * Does not post to the customer account (customer shipping revenue is on the sales invoice).
 */
class ShippingCourierAccountingService
{
    public function __construct(
        private LedgerJournalService $ledgerJournalService
    ) {
    }

    /**
     * @param  bool  $paidImmediately  If true, credit bank/safe/service cash account immediately
     * @param  string|null  $paymentType  bank|safe|service_account when paying immediately
     */
    public function recordShipmentCourierCost(
        Order $order,
        ShippingCompany $company,
        float $cost,
        bool $paidImmediately = false,
        ?string $paymentType = null,
        ?int $bankId = null,
        ?int $safeId = null,
        ?int $serviceAccountId = null,
        ?int $userId = null
    ): ?Shipment {
        if ($cost <= 0.00001) {
            return null;
        }

        $expense = TreeAccount::ensureFreightOutExpenseAccount();

        $uid = $userId ?? auth()->id();

        return DB::transaction(function () use ($order, $company, $cost, $paidImmediately, $paymentType, $bankId, $safeId, $serviceAccountId, $expense, $uid) {
            $payableId = $company->tree_account_id
                ? (int) $company->tree_account_id
                : null;

            if (! $paidImmediately && ! $payableId) {
                $payableId = TreeAccount::ensureShippingCourierPayableAccount()->id;
            }

            $order->courier_shipping_cost = round($cost, 2);
            $order->save();

            $desc = 'شحن صادر — طلب رقم ' . $order->id . ' — ' . $company->name;
            $batchCode = 'SHIP-COST-' . $order->id . '-' . now()->format('YmdHis');

            $creditAccountId = null;
            if ($paidImmediately) {
                $creditAccountId = $this->resolveCashAccountId($paymentType ?? 'bank', $bankId, $safeId, $serviceAccountId);
                if (!$creditAccountId) {
                    throw new \RuntimeException('تعذر تحديد حساب الصندوق/البنك للسداد.');
                }
            } else {
                $creditAccountId = (int) $payableId;
            }

            $dailyEntry = $this->ledgerJournalService->postBalancedJournal(
                [
                    [
                        'account_id' => $expense->id,
                        'debit' => $cost,
                        'credit' => 0,
                        'description' => $desc . ' — مصروف توصيل',
                    ],
                    [
                        'account_id' => $creditAccountId,
                        'debit' => 0,
                        'credit' => $cost,
                        'description' => $desc . ' — ' . ($paidImmediately ? 'صرف نقدي' : 'ذمة شركة شحن'),
                    ],
                ],
                $desc,
                $order->id,
                $batchCode,
                $uid
            );

            return Shipment::create([
                'order_id' => $order->id,
                'purchase_id' => null,
                'shipping_company_id' => $company->id,
                'cost' => round($cost, 2),
                'payment_status' => $paidImmediately ? 'paid' : 'unpaid',
                'paid_at' => $paidImmediately ? now() : null,
                'daily_entry_id' => $dailyEntry->id,
                'notes' => null,
            ]);
        });
    }

    private function resolveCashAccountId(string $paymentType, ?int $bankId, ?int $safeId, ?int $serviceAccountId): ?int
    {
        if ($paymentType === 'safe' && $safeId) {
            $safe = Safe::find($safeId);

            return $safe?->account_id ? (int) $safe->account_id : null;
        }
        if ($paymentType === 'service_account' && $serviceAccountId) {
            $svc = ServiceAccount::find($serviceAccountId);

            return $svc?->account_id ? (int) $svc->account_id : null;
        }
        if ($bankId) {
            $bank = Bank::find($bankId);

            return $bank?->asset_id ? (int) $bank->asset_id : null;
        }

        return null;
    }
}
