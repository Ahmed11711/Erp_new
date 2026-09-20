import { applyOfferVat, calculateOfferTotals, calculateVat } from './vat';

describe('vat', () => {
  it('applies 14% on the amount after discount', () => {
    expect(calculateVat(40300)).toBe(5642);
    expect(calculateOfferTotals(40300, 0, 1)).toEqual({
      subtotal: 40300,
      transportation: 0,
      vat: 5642,
      total: 45942,
    });
  });

  it('corrects quotation 5384 which stored VAT on the pre-discount price', () => {
    const totals = calculateOfferTotals(40300, 0, 5936);
    expect(totals.vat).toBe(5642);
    expect(totals.total).toBe(45942);
  });

  it('keeps VAT at zero when tax was cleared', () => {
    expect(calculateOfferTotals(40300, 0, 0).vat).toBe(0);
    expect(calculateOfferTotals(40300, 0, 0).total).toBe(40300);
  });

  it('includes transportation in the offer VAT base', () => {
    expect(calculateOfferTotals(40300, 2100, 1).vat).toBe(5936);
  });

  it('recomputes offer totals from after-discount line prices', () => {
    const offer = applyOfferVat({
      subtotal: 40300,
      transportation: 0,
      vat: 5936,
      total: 46236,
      category: [{
        category_quantity: 20,
        new_category_price: 2015,
        total_price: 40300,
      }],
    });
    expect(offer.vat).toBe(5642);
    expect(offer.total).toBe(45942);
  });
});
