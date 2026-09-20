<?php

namespace Tests\Unit;

use App\Models\Offers;
use App\Models\OffersCategory;
use App\Support\VatCalculator;
use Tests\TestCase;

class VatCalculatorTest extends TestCase
{
    public function test_amount_is_14_percent_of_taxable_base(): void
    {
        $this->assertEqualsWithDelta(5642.0, VatCalculator::amount(40300), 0.001);
        $this->assertEqualsWithDelta(0.0, VatCalculator::amount(40300, false), 0.001);
    }

    public function test_quotation_5384_vat_uses_price_after_discount_not_before(): void
    {
        $wrongPreDiscountVat = 20 * 2120 * 0.14;
        $this->assertEqualsWithDelta(5936.0, $wrongPreDiscountVat, 0.001);

        $totals = VatCalculator::offerTotals(40300, 0, 5936);

        $this->assertEqualsWithDelta(5642.0, $totals['vat'], 0.001);
        $this->assertEqualsWithDelta(45942.0, $totals['total'], 0.001);
    }

    public function test_cleared_vat_stays_zero(): void
    {
        $totals = VatCalculator::offerTotals(40300, 0, 0);

        $this->assertEqualsWithDelta(0.0, $totals['vat'], 0.001);
        $this->assertEqualsWithDelta(40300.0, $totals['total'], 0.001);
    }

    public function test_transportation_is_included_in_offer_vat_base(): void
    {
        $totals = VatCalculator::offerTotals(40300, 2100, 1);

        $this->assertEqualsWithDelta(5936.0, $totals['vat'], 0.001);
        $this->assertEqualsWithDelta(48336.0, $totals['total'], 0.001);
    }

    public function test_apply_to_offer_recomputes_from_after_discount_lines(): void
    {
        $offer = new Offers([
            'subtotal' => 40300,
            'transportation' => 0,
            'vat' => 5936,
            'total' => 46236,
        ]);
        $offer->setRelation('category', collect([
            new OffersCategory([
                'category_quantity' => 20,
                'new_category_price' => 2015,
                'total_price' => 40300,
            ]),
        ]));

        VatCalculator::applyToOffer($offer);

        $this->assertEqualsWithDelta(5642.0, (float) $offer->vat, 0.001);
        $this->assertEqualsWithDelta(45942.0, (float) $offer->total, 0.001);
    }
}
