<?php

namespace App\Support;

use App\Models\Offers;

class VatCalculator
{
    public const RATE = 14.0;

    public static function amount(float $taxable, bool $apply = true): float
    {
        if (! $apply || $taxable <= 0) {
            return 0.0;
        }

        return round($taxable * self::RATE / 100, 2);
    }

    /**
     * ضريبة عرض السعر: 14% من (الصافي بعد الخصم + النقل) أو صفر إذا أُلغيت.
     *
     * @return array{subtotal: float, transportation: float, vat: float, total: float}
     */
    public static function offerTotals(float $subtotal, float $transportation, float $storedVat): array
    {
        $subtotal = round(max(0, $subtotal), 3);
        $transportation = round(max(0, $transportation), 3);
        $vat = self::amount($subtotal + $transportation, $storedVat > 0);
        $total = round($subtotal + $transportation + $vat, 3);

        return [
            'subtotal' => $subtotal,
            'transportation' => $transportation,
            'vat' => $vat,
            'total' => $total,
        ];
    }

    public static function lineTotal(float $quantity, float $unitPrice): float
    {
        return round(max(0, $quantity) * max(0, $unitPrice), 3);
    }

    public static function applyToOffer(Offers $offer): Offers
    {
        $subtotal = self::subtotalFromOffer($offer);
        $totals = self::offerTotals(
            $subtotal,
            (float) ($offer->transportation ?? 0),
            (float) ($offer->vat ?? 0)
        );

        $offer->subtotal = $totals['subtotal'];
        $offer->transportation = $totals['transportation'];
        $offer->vat = $totals['vat'];
        $offer->total = $totals['total'];

        return $offer;
    }

    private static function subtotalFromOffer(Offers $offer): float
    {
        if (! $offer->relationLoaded('category')) {
            return (float) ($offer->subtotal ?? 0);
        }

        $fromLines = 0.0;
        $hasLines = false;
        foreach ($offer->category as $line) {
            $hasLines = true;
            $computed = self::lineTotal(
                (float) ($line->category_quantity ?? 0),
                (float) ($line->new_category_price ?? 0)
            );
            $stored = (float) ($line->total_price ?? 0);
            $fromLines += $computed > 0 ? $computed : $stored;
        }

        return $hasLines ? $fromLines : (float) ($offer->subtotal ?? 0);
    }
}
