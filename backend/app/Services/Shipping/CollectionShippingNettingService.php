<?php

namespace App\Services\Shipping;

use App\Enums\CollectionProviderType;
use App\Enums\OrderCollectionStatus;
use App\Enums\OrderSettlementStatus;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\ShippingCompany;
use App\Models\shippingCompanyDetails;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\LedgerJournalService;
use App\Services\Accounting\ReceivableTreeAccountGuard;
use Illuminate\Support\Facades\DB;

/**
 * تسوية مزدوجة (مقاصّة) لشركة تحصيل تورّد «قيمة البضاعة فقط» وتحتفظ بالشحن للمندوب:
 *
 *  - قبض قيمة البضاعة نقداً        →  مدين: الخزنة/البنك،   دائن: ذمم شركة التحصيل
 *  - إطفاء مستحق الشحن للمندوب     →  مدين: مستحقات الشحن،   دائن: ذمم شركة التحصيل
 *
 * قيمة البضاعة  = net_total - shipping_cost   (نقد فعلي يدخل الخزنة)
 * قيمة الشحن    = المتبقّي على الطلب - قيمة البضاعة  (يُطفأ من حساب مستحقات الشحن، مقاصّة بلا حركة خزنة)
 *
 * النتيجة: تُقفَل ذمة التحصيل بالكامل للطلبات المحددة، ويقلّ مستحق الشحن للمندوب/الشركة.
 */
class CollectionShippingNettingService
{
    public function __construct(
        private readonly LedgerJournalService $ledgerJournalService,
        private readonly CollectionReceivableAccountResolver $receivableResolver,
        private readonly ReceivableTreeAccountGuard $receivableGuard,
        private readonly AccountLinkingService $accountLinkingService,
    ) {
    }

    /**
     * @param  array<int>  $orderIds
     * @return array{
     *     order_count:int,
     *     product_total:float,
     *     shipping_total:float,
     *     grand_total:float,
     *     daily_entry_id:int,
     *     lines: array<int, array{order_id:int, product:float, shipping:float, outstanding:float}>
     * }
     */
    public function settle(
        int $collectionCompanyId,
        array $orderIds,
        int $cashAccountId,
        string $date,
        string $mode = 'netting',
        ?string $notes = null,
        ?int $userId = null
    ): array {
        $cc = CollectionCompany::find($collectionCompanyId);
        if (! $cc) {
            throw new \InvalidArgumentException('شركة التحصيل غير موجودة.');
        }

        // حساب ذمم شركة التحصيل (دائن عند التخفيض)
        $this->accountLinkingService->ensureCollectionCompanyAccount($cc);
        $cc->refresh();
        $receivableAccountId = $this->receivableResolver->receivableAccountIdForProvider(
            CollectionProviderType::CollectionCompany->value,
            (int) $cc->id
        );
        if (! $receivableAccountId) {
            throw new \InvalidArgumentException('لا يوجد حساب ذمم مرتبط بشركة التحصيل. اربطها بحساب في الشجرة أولاً.');
        }

        // شركة الشحن المرتبطة (سجل COD التشغيلي) — قيمة الشحن تُثبَّت كمصروف داخل runNetting.
        $ledgerCompany = $cc->linked_shipping_company_id
            ? ShippingCompany::find((int) $cc->linked_shipping_company_id)
            : null;

        return $this->runNetting(
            $orderIds,
            $cashAccountId,
            $receivableAccountId,
            0,
            $ledgerCompany,
            $date,
            $notes,
            $userId,
            'COLL-SHIP-NET-'.$cc->id,
            'تسوية مزدوجة (قبض بضاعة + شحن) — '.($cc->name ?: ('#'.$cc->id)),
            'تخفيض ذمة التحصيل'
        );
    }

    /**
     * تسوية مزدوجة مباشرة على مندوب/شركة شحن (الطرف نفسه يحتفظ بالشحن كأجرته):
     *  - قبض قيمة البضاعة نقداً        →  مدين: الخزنة/البنك،   دائن: ذمم الشحن (COD)
     *  - إطفاء مستحق الشحن للمندوب     →  مدين: مستحقات الشحن،   دائن: ذمم الشحن (COD)
     *
     * @param  array<int>  $orderIds
     * @return array{order_count:int, product_total:float, shipping_total:float, grand_total:float, daily_entry_id:int, lines: array<int, array<string, float|int>>}
     */
    public function settleForShippingCompany(
        int $shippingCompanyId,
        array $orderIds,
        int $cashAccountId,
        string $date,
        string $mode = 'netting',
        ?string $notes = null,
        ?int $userId = null
    ): array {
        $company = ShippingCompany::find($shippingCompanyId);
        if (! $company) {
            throw new \InvalidArgumentException('شركة الشحن / المندوب غير موجود.');
        }

        $receivableAccountId = $company->receivable_tree_account_id
            ? (int) $company->receivable_tree_account_id
            : null;
        if (! $receivableAccountId) {
            throw new \InvalidArgumentException('لا يوجد حساب ذمم تحصيل مرتبط بشركة الشحن / المندوب. اربط «حساب الذمم» أولاً.');
        }

        return $this->runNetting(
            $orderIds,
            $cashAccountId,
            $receivableAccountId,
            0,
            $company,
            $date,
            $notes,
            $userId,
            'SHIP-NET-'.$company->id,
            'تسوية مزدوجة (قبض بضاعة + شحن) — '.($company->name ?: ('#'.$company->id)),
            'تخفيض ذمة الشحن (COD)'
        );
    }

    /**
     * جوهر المقاصّة: احتساب البضاعة/الشحن، ترحيل القيد المتوازن، وإقفال الذمم التشغيلية.
     *
     * @param  array<int>  $orderIds
     * @return array{order_count:int, product_total:float, shipping_total:float, grand_total:float, daily_entry_id:int, lines: array<int, array<string, float|int>>}
     */
    private function runNetting(
        array $orderIds,
        int $cashAccountId,
        int $receivableAccountId,
        int $shippingPayableAccountId,
        ?ShippingCompany $ledgerCompany,
        string $date,
        ?string $notes,
        ?int $userId,
        string $batchPrefix,
        string $headerLabel,
        string $receivableLegLabel
    ): array {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            throw new \InvalidArgumentException('لم يتم تحديد أي طلبات للتسوية.');
        }

        // حساب الخزنة/البنك (مصدر نقدي)
        $this->receivableGuard->assertValidPaymentSourceTreeAccount($cashAccountId);

        $uid = $userId ?? auth()->id();

        return DB::transaction(function () use (
            $orderIds,
            $cashAccountId,
            $receivableAccountId,
            $shippingPayableAccountId,
            $ledgerCompany,
            $date,
            $notes,
            $uid,
            $batchPrefix,
            $headerLabel,
            $receivableLegLabel
        ) {
            $lines = [];
            $productTotal = 0.0;
            $shippingTotal = 0.0;

            foreach ($orderIds as $orderId) {
                $order = Order::with('order_details')->find($orderId);
                if (! $order || ! $order->order_details) {
                    continue;
                }

                $outstanding = $this->outstandingForOrder($order, $ledgerCompany?->id);
                if ($outstanding <= 0.009) {
                    continue;
                }

                $net = round((float) $order->net_total, 2);
                $customerShipping = round(max(0, (float) ($order->shipping_cost ?? 0)), 2);
                $product = round(max(0, $net - $customerShipping), 2);
                $product = round(min($product, $outstanding), 2);
                $shipping = round(max(0, $outstanding - $product), 2);

                $lines[] = [
                    'order_id' => (int) $orderId,
                    'product' => $product,
                    'shipping' => $shipping,
                    'outstanding' => $outstanding,
                ];
                $productTotal = round($productTotal + $product, 2);
                $shippingTotal = round($shippingTotal + $shipping, 2);
            }

            if ($lines === []) {
                throw new \InvalidArgumentException('لا توجد طلبات معلّقة قابلة للتسوية ضمن المحدد.');
            }

            $grandTotal = round($productTotal + $shippingTotal, 2);
            $batchCode = $batchPrefix.'-'.now()->format('YmdHis');
            $desc = $headerLabel.($notes ? ' — '.$notes : '');

            $journalLines = [];
            if ($productTotal > 0.009) {
                $journalLines[] = [
                    'account_id' => $cashAccountId,
                    'debit' => $productTotal,
                    'credit' => 0,
                    'description' => $desc.' — قبض قيمة البضاعة',
                ];
            }
            if ($shippingTotal > 0.009) {
                // قيمة الشحن تُثبَّت كمصروف شحن صادر (بدل إطفاء مستحقات) — نظام موحّد مع تحصيل الطلب.
                $expenseAccountId = (int) TreeAccount::ensureFreightOutExpenseAccount()->id;
                $journalLines[] = [
                    'account_id' => $expenseAccountId,
                    'debit' => $shippingTotal,
                    'credit' => 0,
                    'description' => $desc.' — مصروف شحن صادر',
                ];
            }
            $journalLines[] = [
                'account_id' => $receivableAccountId,
                'debit' => 0,
                'credit' => $grandTotal,
                'description' => $desc.' — '.$receivableLegLabel,
            ];

            $dailyEntry = $this->ledgerJournalService->postBalancedJournal(
                $journalLines,
                $desc,
                null,
                $batchCode,
                $uid,
                new \DateTimeImmutable($date),
                true
            );

            // إقفال الذمم التشغيلية للطلبات (ledger + حالة الطلب)
            foreach ($lines as $ln) {
                $this->closeOrderCollection(
                    (int) $ln['order_id'],
                    $ledgerCompany,
                    $date,
                    $uid
                );
            }

            return [
                'order_count' => count($lines),
                'product_total' => $productTotal,
                'shipping_total' => $shippingTotal,
                'grand_total' => $grandTotal,
                'daily_entry_id' => (int) $dailyEntry->id,
                'lines' => $lines,
            ];
        });
    }

    /**
     * المتبقّي على الطلب: مجموع سطور شركة الشحن المرتبطة المفتوحة إن وُجدت، وإلا ذمة التحصيل بالطلب.
     */
    private function outstandingForOrder(Order $order, ?int $linkedShippingCompanyId): float
    {
        if ($linkedShippingCompanyId) {
            $ledgerSum = shippingCompanyDetails::query()
                ->where('shipping_company_id', $linkedShippingCompanyId)
                ->where('order_id', $order->id)
                ->where('is_done', 0)
                ->whereIn('status', ['تم شحن', 'تم التسليم'])
                ->sum('amount');
            $ledgerSum = round((float) $ledgerSum, 2);
            if ($ledgerSum > 0.009) {
                return $ledgerSum;
            }
        }

        return round((float) ($order->order_details->collection_receivable_amount ?? 0), 2);
    }

    private function closeOrderCollection(
        int $orderId,
        ?ShippingCompany $linkedShippingCompany,
        string $date,
        ?int $userId
    ): void {
        $order = Order::with('order_details')->find($orderId);
        if (! $order || ! $order->order_details) {
            return;
        }
        $od = $order->order_details;
        $userName = $userId ? (\App\Models\User::find($userId)?->name ?? 'system') : (auth()->user()?->name ?? 'system');

        // إقفال سطور شركة الشحن المرتبطة (COD تشغيلي)
        if ($linkedShippingCompany) {
            $rows = shippingCompanyDetails::query()
                ->where('shipping_company_id', $linkedShippingCompany->id)
                ->where('order_id', $orderId)
                ->where('is_done', 0)
                ->whereIn('status', ['تم شحن', 'تم التسليم'])
                ->get();

            foreach ($rows as $elm) {
                $elm->is_done = 1;
                $elm->status = 'تم التحصيل';
                $elm->collect_date = $date;
                $elm->save();

                DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                    (float) $elm->shipping_company_id,
                    (int) $elm->order_id,
                    $od->shipping_date,
                    'تم التحصيل',
                    (float) -$elm->amount,
                    $userName,
                    now(),
                ]);
            }
        }

        // إقفال ذمة التحصيل بالطلب
        if ((float) ($od->collection_receivable_amount ?? 0) > 0.009) {
            $od->collection_receivable_amount = 0;
        }
        $od->settlement_status = OrderSettlementStatus::Settled->value;
        $od->collection_status = OrderCollectionStatus::Collected->value;
        $od->collection_date = $date;
        $od->status_date = $date;
        $od->save();

        if ($order->order_status !== 'تم التحصيل') {
            $order->order_status = 'تم التحصيل';
            $order->save();
        }

        Order::reconcileCollectionStatusIfAllShippingLinesClosed($orderId);
    }
}
