<?php

namespace App\Services\Orders;

use App\Enums\CollectionProviderType;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\shippingCompanyDetails;
use App\Services\Shipping\CollectionReceivableAccountResolver;

/**
 * يحدد متى يُسمح بـ «تحصيل الطلب» اليدوي (من المندوب/شركة الشحن)
 * مقابل التسوية عبر سند «نقد وارد — شركة تحصيل».
 */
final class OrderManualCollectionGuard
{
    private const OPEN_COLLECTION_STATUSES = ['تم شحن', 'تم التسليم'];

    public function __construct(
        private CollectionReceivableAccountResolver $collectionResolver,
    ) {
    }

    public function allowsManualShippingCollection(Order $order): bool
    {
        return $this->shippingCodAmount($order) > 0.009;
    }

    /**
     * هل يُسمح بـ «تحصيل الطلب» (COD على الشحن و/أو ذمة شركة تحصيل مثل Paymob)؟
     */
    public function allowsManualOrderCollection(Order $order): bool
    {
        return $this->allowsManualShippingCollection($order)
            || $this->openCollectionReceivableAmount($order) > 0.009;
    }

    /** المبلغ الإجمالي المتوقع عند تحصيل الطلب من الواجهة */
    public function expectedManualCollectTotal(Order $order): float
    {
        return round(
            max(0, $this->shippingCodAmount($order)) + max(0, $this->openCollectionReceivableAmount($order)),
            2
        );
    }

    /**
     * جزء التحصيل عند الاستلام (COD) على شركة الشحن/المندوب.
     */
    public function shippingCodAmount(Order $order): float
    {
        $order->loadMissing('order_details');
        $od = $order->order_details;

        if ($od?->shipping_receivable_amount !== null) {
            return round(max(0, (float) $od->shipping_receivable_amount), 2);
        }

        return round(max(0, (float) $order->net_total), 2);
    }

    /**
     * ذمة شركة التحصيل المفتوحة (جزء مدفوع مقدماً/إلكترونياً).
     */
    public function openCollectionReceivableAmount(Order $order): float
    {
        $order->loadMissing('order_details');
        $od = $order->order_details;
        if (! $od) {
            return 0.0;
        }

        if ($od->collection_receivable_amount !== null) {
            return round(max(0, (float) $od->collection_receivable_amount), 2);
        }

        if ($this->isPrepaidOnCollectionIntermediary($order)) {
            return round(max(0, (float) ($order->prepaid_amount ?? 0)), 2);
        }

        return 0.0;
    }

    public function isPrepaidOnCollectionIntermediary(Order $order): bool
    {
        $prepaid = (float) ($order->prepaid_amount ?? 0);
        if ($prepaid <= 0.009) {
            return false;
        }

        $paymentType = trim((string) ($order->prepaid_payment_type ?? ''));
        if (in_array($paymentType, ['bank', 'safe', 'service_account'], true)) {
            return false;
        }

        $order->loadMissing('order_details');
        if ($order->order_details?->collection_provider_type === CollectionProviderType::CollectionCompany->value
            && $order->order_details?->collection_provider_id) {
            return true;
        }

        return $this->collectionResolver->receivableAccountIdForOrder($order) !== null;
    }

    public function legacyCollectionShippingCompanyId(?OrderDetails $od): ?int
    {
        if (! $od) {
            return null;
        }

        if ($od->collection_company_id) {
            return (int) $od->collection_company_id;
        }

        if ($od->collection_provider_type === CollectionProviderType::CollectionCompany->value
            && $od->collection_provider_id) {
            $company = CollectionCompany::find((int) $od->collection_provider_id);

            return $company?->linked_shipping_company_id
                ? (int) $company->linked_shipping_company_id
                : null;
        }

        return null;
    }

    /**
     * هل السطر التشغيلي يخص ذمة شركة التحصيل (وليس COD على المندوب)؟
     *
     * عندما تُخزَّن ذمة التحصيل على نفس shipping_company_id للمندوب (legacy bridge)،
     * يُعامل السطر كمستحق مندوب — نفس منطق «قبض» في تقرير ذمم الشحن.
     */
    public function isCollectionCompanyShippingRow(int $shippingCompanyId, ?OrderDetails $od): bool
    {
        $legacyId = $this->legacyCollectionShippingCompanyId($od);
        if ($legacyId === null || $legacyId !== $shippingCompanyId) {
            return false;
        }

        $courierId = (int) ($od?->shipping_company_id ?? 0);
        if ($courierId > 0 && $legacyId === $courierId) {
            return false;
        }

        return true;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, shippingCompanyDetails>|\Illuminate\Database\Eloquent\Collection<int, shippingCompanyDetails>  $rows
     * @return \Illuminate\Support\Collection<int, shippingCompanyDetails>
     */
    public function filterManualCollectShippingRows(Order $order, $rows)
    {
        $order->loadMissing('order_details');
        $od = $order->order_details;

        return $rows->filter(function (shippingCompanyDetails $row) use ($od) {
            return ! $this->isCollectionCompanyShippingRow((int) $row->shipping_company_id, $od);
        })->values();
    }

    /**
     * سطور shipping_company_details المفتوحة القابلة لـ «تحصيل الطلب» على المندوب.
     *
     * @return \Illuminate\Support\Collection<int, shippingCompanyDetails>
     */
    public function openManualCollectShippingRows(Order $order)
    {
        $order->loadMissing('order_details');

        return $this->filterManualCollectShippingRows(
            $order,
            $this->openShippingDetailRows($order)
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, shippingCompanyDetails>
     */
    public function openShippingDetailRows(Order $order)
    {
        return shippingCompanyDetails::query()
            ->where('order_id', (int) $order->id)
            ->where('is_done', 0)
            ->whereIn('status', self::OPEN_COLLECTION_STATUSES)
            ->get();
    }

    /**
     * رسالة منع/توجيه قبل تنفيذ تحصيل الطلب؛ null = مسموح.
     */
    public function manualCollectionUnavailableMessage(Order $order): ?string
    {
        if (! $this->allowsManualOrderCollection($order)) {
            return $this->manualCollectionBlockedMessage($order);
        }

        $openCollection = $this->openCollectionReceivableAmount($order);
        $allOpen = $this->openShippingDetailRows($order);
        $manualRows = $this->filterManualCollectShippingRows($order, $allOpen);

        // ذمة شركة التحصيل فقط (Paymob / مدفوع إلكترونياً) — لا يلزم سطر مندوب مفتوح
        if ($manualRows->isEmpty() && $openCollection > 0.009) {
            $company = app(\App\Services\Shipping\CollectionCompanyForOrderResolver::class)->resolve($order);
            if ($company === null) {
                return 'لا توجد شركة تحصيل مرتبطة — راجع وسيلة الدفع أو «شركات التحصيل والدفع».';
            }

            return null;
        }

        if ($allOpen->isEmpty() && $openCollection <= 0.009) {
            return 'لا توجد مستحقات تحصيل مفتوحة لهذا الطلب.';
        }

        if ($manualRows->isEmpty()) {
            if ($openCollection > 0.009) {
                return null;
            }

            return 'لا توجد مستحقات تحصيل مفتوحة على المندوب/شركة الشحن لهذا الطلب.';
        }

        $order->loadMissing('order_details');
        $storedShippingAmount = $order->order_details?->shipping_receivable_amount;
        if ($storedShippingAmount !== null) {
            $expected = round(max(0, (float) $storedShippingAmount), 2);
            $actual = round((float) $manualRows->sum(static fn (shippingCompanyDetails $r) => (float) $r->amount), 2);
            if ($expected > 0.009 && abs($actual - $expected) > 0.05) {
                return sprintf(
                    'مجموع مستحقات المندوب (%.2f) لا يطابق مبلغ التحصيل المتوقع (%.2f). راجع تقرير ذمم الشحن قبل التحصيل.',
                    $actual,
                    $expected
                );
            }
        }

        return null;
    }

    public function manualCollectionBlockedMessage(Order $order): string
    {
        return 'لا يوجد مبلغ مفتوح للتحصيل على هذا الطلب.';
    }

    /**
     * بعد تحصيل جزء الشحن فقط: هل يُغلق الطلب بـ «تم التحصيل»؟
     */
    public function shouldMarkOrderFullyCollected(Order $order): bool
    {
        return $this->openCollectionReceivableAmount($order) <= 0.009;
    }
}
