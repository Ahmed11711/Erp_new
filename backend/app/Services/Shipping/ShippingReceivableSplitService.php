<?php

namespace App\Services\Shipping;

use App\Models\Order;
use InvalidArgumentException;

/**
 * توزيع المبلغ التشغيلي (رصيد shipping_companies + shipping_company_details)
 * بين شركة الشحن/المندوب وشركة التحصيل — متوافق مع منطق SalesOrderAccountingService::buildSplitReceivableDebits.
 *
 * - تلقائي: جزء min(prepaid, net_total) على شركة التحصيل إن وُجدت واختلفت عن شركة الشحن؛ الباقي على شركة الشحن.
 * - يدوي: تمرير مبلغين يتساويان مع net_total (اختياري من واجهة الشحن).
 */
class ShippingReceivableSplitService
{
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

        if ($manualShip !== null xor $manualColl !== null) {
            throw new InvalidArgumentException('يجب إرسال مبلغ الشحن ومبلغ التحصيل معاً للتقسيم اليدوي.');
        }

        if ($manualShip !== null && $manualColl !== null) {
            $sum = round($manualShip + $manualColl, 3);
            if (abs($sum - $net) > 0.02) {
                throw new InvalidArgumentException(
                    'مجموع مبلغ الشحن ومبلغ التحصيل (' . $sum . ') يجب أن يساوي صافي الطلب (' . $net . ').'
                );
            }
            $collectionAmount = max(0, $manualColl);
            $shippingAmount = max(0, $manualShip);
        } else {
            $collectionAmount = 0.0;
            if (
                $collectionCompanyId
                && (int) $collectionCompanyId !== (int) $courierShippingCompanyId
                && $prepaid > 0.0001
            ) {
                $collectionAmount = round(min($prepaid, $net), 3);
            }
            $shippingAmount = round(max(0, $net - $collectionAmount), 3);
        }

        $effectiveCollectionId = null;
        if ($collectionAmount > 0.0001) {
            if (! $collectionCompanyId) {
                throw new InvalidArgumentException('يجب اختيار شركة تحصيل عند وجود مبلغ على التحصيل.');
            }
            $effectiveCollectionId = (int) $collectionCompanyId;
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
