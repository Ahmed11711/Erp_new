/** نسبة ضريبة القيمة المضافة المعتمدة على عروض الأسعار وطلبات الشركات */
export const VAT_PERCENT = 14;

export function roundMoney(value: number): number {
  return Math.round((Number(value) || 0) * 100) / 100;
}

export function calculateVat(taxable: number, apply = true): number {
  if (!apply || taxable <= 0) {
    return 0;
  }
  return roundMoney(taxable * VAT_PERCENT / 100);
}

export function calculateOfferTotals(
  subtotal: number,
  transportation = 0,
  storedVat = 0
): { subtotal: number; transportation: number; vat: number; total: number } {
  const net = roundMoney(Math.max(0, Number(subtotal) || 0));
  const ship = roundMoney(Math.max(0, Number(transportation) || 0));
  const vat = calculateVat(net + ship, Number(storedVat) > 0);
  return {
    subtotal: net,
    transportation: ship,
    vat,
    total: roundMoney(net + ship + vat),
  };
}

export function applyOfferVat<T extends {
  subtotal?: number;
  transportation?: number;
  vat?: number;
  total?: number;
  category?: Array<{ category_quantity?: number; new_category_price?: number; total_price?: number }>;
}>(offer: T): T {
  const lines = offer.category || [];
  const fromLines = lines.reduce((sum, line) => {
    const qty = Number(line.category_quantity) || 0;
    const price = Number(line.new_category_price) || 0;
    const computed = roundMoney(qty * price);
    const stored = Number(line.total_price) || 0;
    return sum + (computed > 0 ? computed : stored);
  }, 0);
  const subtotal = lines.length ? fromLines : Number(offer.subtotal) || 0;
  const totals = calculateOfferTotals(subtotal, Number(offer.transportation) || 0, Number(offer.vat) || 0);
  offer.subtotal = totals.subtotal;
  offer.transportation = totals.transportation;
  offer.vat = totals.vat;
  offer.total = totals.total;
  return offer;
}
