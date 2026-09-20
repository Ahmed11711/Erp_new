<?php

namespace App\Services\Shipping;

use App\Models\Order;
use InvalidArgumentException;

/**
 * توزيع المبلغ التشغيلي (رصيد shipping_companies + shipping_company_details)
 * بين شركة الشحن/المندوب وشركة التحصيل.
 *
 * net_total في النظام = المتبقي للتحصيل كاش (إجمالي − مدفوع مقدماً − خصم).
 * إذا كان المدفوع أكبر من الصافي (أوردر أونلاين ثم إضافة صنف كاش) يُحسب:
 *   شركة التحصيل = prepaid، المندوب = net_total.
 *
 * - تلقائي: جزء prepaid على شركة التحصيل إن وُجدت واختلفت عن شركة الشحن؛ الباقي على شركة الشحن.
 * - يدوي ناقص (مبلغ واحد فقط): يُتجاهل ويُحسب تلقائياً حتى لا يفشل التسليم بعد تعديل الطلب.
 * - يدوي كامل: مجموع المبلغين = الصافي، أو = الصافي + المدفوع عندما يكون المدفوع أكبر من الصافي.
 */
class ShippingReceivableSplitService
{
    /**
     * الصافي هنا متبقٍ كاش، والمدفوع أونلاين أكبر منه — إضافة أصناف بعد سداد إلكتروني.
     */
    public function prepaidExceedsNet(float $net, float $prepaid): bool
    {
        return round(max(0, $prepaid), 3) > round(max(0, $net), 3) + 0.02;
    }

    /**
     * المبلغ المطلوب تحصيله كاش (COD).
     */
    public function codRemaining(float $net, float $prepaid): float
    {
        $net = round(max(0, $net), 3);
        $prepaid = round(max(0, $prepaid), 3);

        if ($this->prepaidExceedsNet($net, $prepaid)) {
            return $net;
        }

        return round(max(0, $net - $prepaid), 3);
    }

    /**
     * الأساس الذي يجب أن يساويه مجموع مبلغ الشحن + مبلغ التحصيل.
     */
    public function splitBasis(float $net, float $prepaid): float
    {
        $net = round(max(0, $net), 3);
        $prepaid = round(max(0, $prepaid), 3);

        if ($this->prepaidExceedsNet($net, $prepaid)) {
            return round($net + $prepaid, 3);
        }

        return $net;
    }

    /**
     * @return array{shipping_amount: float, collection_amount: float}
     */
    public function autoAmounts(float $net, float $prepaid, bool $hasDistinctCollection): array
    {
        $net = round(max(0, $net), 3);
        $prepaid = round(max(0, $prepaid), 3);

        if (! $hasDistinctCollection || $prepaid <= 0.0001) {
            return [
                'shipping_amount' => $net,
                'collection_amount' => 0.0,
            ];
        }

        if ($this->prepaidExceedsNet($net, $prepaid)) {
            return [
                'shipping_amount' => $net,
                'collection_amount' => $prepaid,
            ];
        }

        $collectionAmount = round(min($prepaid, $net), 3);

        return [
            'shipping_amount' => round(max(0, $net - $collectionAmount), 3),
            'collection_amount' => $collectionAmount,
        ];
    }

    /**
     * @return array{shipping_amount: float, collection_amount: float, collection_company_id: ?int}
     */
    public function resolveForShip(
        Order $order,
        int $courierShippingCompanyId,
        ?int $collectionCompanyId,
        ?float $manualShippingAmount,
        ?float $manualCollectionAmount,
        ?float $basisNetTotal = null,
    ): array {
        $net = round(max(0, $basisNetTotal ?? (float) $order->net_total), 3);
        $prepaid = round(max(0, (float) ($order->prepaid_amount ?? 0)), 3);

        $manualShip = $manualShippingAmount !== null ? round((float) $manualShippingAmount, 3) : null;
        $manualColl = $manualCollectionAmount !== null ? round((float) $manualCollectionAmount, 3) : null;

        $hasDistinctCollection = $collectionCompanyId
            && (int) $collectionCompanyId !== (int) $courierShippingCompanyId;

        // مبلغ واحد فقط = لقطة ناقصة بعد تعديل الطلب، وليست تقسيماً يدوياً صحيحاً.
        if ($manualShip !== null xor $manualColl !== null) {
            $manualShip = null;
            $manualColl = null;
        }

        if ($manualShip !== null && $manualColl !== null) {
            $sum = round($manualShip + $manualColl, 3);
            $basis = $this->splitBasis($net, $prepaid);
            $matchesNet = abs($sum - $net) <= 0.02;
            $matchesGrand = abs($sum - $basis) <= 0.02;
            if (! $matchesNet && ! $matchesGrand) {
                throw new InvalidArgumentException(
                    'مجموع مبلغ الشحن ومبلغ التحصيل (' . $sum . ') يجب أن يساوي صافي الطلب (' . $net . ')'
                    . ($basis > $net + 0.02 ? ' أو إجمالي الطلب بعد الدفعة المقدمة (' . $basis . ')' : '')
                    . '.'
                );
            }
            $collectionAmount = max(0, $manualColl);
            $shippingAmount = max(0, $manualShip);
        } else {
            $auto = $this->autoAmounts($net, $prepaid, (bool) $hasDistinctCollection);
            $collectionAmount = $auto['collection_amount'];
            $shippingAmount = $auto['shipping_amount'];
        }

        $effectiveCollectionId = null;
        if ($collectionAmount > 0.0001) {
            if ($collectionCompanyId) {
                $effectiveCollectionId = (int) $collectionCompanyId;
            } elseif ($this->prepaidExceedsNet($net, $prepaid) || $prepaid > 0.0001) {
                // ذمة الأونلاين على شركة التحصيل (Visa/Paymob) وليست صف shipping_companies.
            } else {
                throw new InvalidArgumentException('يجب اختيار شركة تحصيل عند وجود مبلغ على التحصيل.');
            }
        }

        return [
            'shipping_amount' => $shippingAmount,
            'collection_amount' => $collectionAmount,
            'collection_company_id' => $effectiveCollectionId,
        ];
    }

    /**
     * @return list<array{company_id: int, amount: float}>
     */
    public function segmentsForProcedureCalls(
        int $courierShippingCompanyId,
        ?int $collectionCompanyId,
        float $shippingAmount,
        float $collectionAmount
    ): array {
        $segments = [];

        if ($collectionAmount > 0.0001 && $collectionCompanyId) {
            $segments[] = [
                'company_id' => (int) $collectionCompanyId,
                'amount' => round($collectionAmount, 3),
            ];
        }

        if ($shippingAmount > 0.0001) {
            $segments[] = [
                'company_id' => (int) $courierShippingCompanyId,
                'amount' => round($shippingAmount, 3),
            ];
        }

        if (count($segments) === 2 && $segments[0]['company_id'] === $segments[1]['company_id']) {
            return [[
                'company_id' => $segments[0]['company_id'],
                'amount' => round($segments[0]['amount'] + $segments[1]['amount'], 3),
            ]];
        }

        return $segments;
    }
}
