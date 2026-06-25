/** آخر مراجعة للفاتورة إن وُجدت */
export function resolveLatestPurchaseRow(item: unknown): Record<string, unknown> {
  if (!item || typeof item !== 'object') {
    return {};
  }
  const row = item as Record<string, unknown>;
  const updated = row['updated_purchase'] ?? row['updatedPurchase'];
  if (updated && typeof updated === 'object') {
    return updated as Record<string, unknown>;
  }
  return row;
}

export function resolvePurchaseListInvoiceType(item: unknown): string {
  const latest = resolveLatestPurchaseRow(item);
  const type = (latest['invoice_type'] ?? '').toString().trim();
  return type || '—';
}

/** اسم مندوب/شركة الشحن من كائن فاتورة مشتريات (يدعم snake_case و camelCase) */
export function resolvePurchaseShippingRep(source: unknown): string {
  if (!source || typeof source !== 'object') {
    return '—';
  }
  const row = source as Record<string, unknown>;
  const co = (row['shipping_company'] ?? row['shippingCompany']) as { name?: string } | undefined;
  const name = (co?.name ?? '').toString().trim();

  return name || '—';
}

/** في القائمة: آخر مراجعة أولاً ثم الصف الرئيسي */
export function resolvePurchaseListShippingRep(item: unknown): string {
  if (!item || typeof item !== 'object') {
    return '—';
  }
  const row = item as Record<string, unknown>;
  const updated = row['updated_purchase'] ?? row['updatedPurchase'];
  if (updated) {
    const fromUpdated = resolvePurchaseShippingRep(updated);
    if (fromUpdated !== '—') {
      return fromUpdated;
    }
  }

  return resolvePurchaseShippingRep(item);
}
