type OrderCollectContext = {
  net_total?: number;
  prepaid_amount?: number;
  prepaid_payment_type?: string | null;
  order_details?: {
    collection_provider_type?: string | null;
    shipping_receivable_amount?: number | null;
    collection_receivable_amount?: number | null;
    maintenance_date?: string | null;
  } | null;
};

export function isPrepaidOnCollectionIntermediary(order: OrderCollectContext): boolean {
  const prepaid = parseFloat(String(order?.prepaid_amount)) || 0;
  if (prepaid <= 0.009) {
    return false;
  }
  const paymentType = String(order?.prepaid_payment_type ?? '').trim();
  if (['bank', 'safe', 'service_account'].includes(paymentType)) {
    return false;
  }
  return order?.order_details?.collection_provider_type === 'collection_company';
}

/** جزء COD على شركة الشحن/المندوب */
export function shippingCodAmount(order: OrderCollectContext): number {
  const od = order?.order_details;
  if (od?.shipping_receivable_amount != null) {
    return Math.max(0, parseFloat(String(od.shipping_receivable_amount)) || 0);
  }
  return Math.max(0, parseFloat(String(order?.net_total)) || 0);
}

/** ذمة شركة التحصيل المفتوحة (Paymob وغيرها) */
export function openCollectionReceivableAmount(order: OrderCollectContext): number {
  const od = order?.order_details;
  if (od?.collection_receivable_amount != null) {
    return Math.max(0, parseFloat(String(od.collection_receivable_amount)) || 0);
  }
  if (isPrepaidOnCollectionIntermediary(order)) {
    return Math.max(0, parseFloat(String(order?.prepaid_amount)) || 0);
  }
  return 0;
}

/** مبلغ التحصيل المتوقع (شحن + شركة تحصيل) */
export function orderCollectAmount(order: OrderCollectContext): number {
  return roundMoney(shippingCodAmount(order) + openCollectionReceivableAmount(order));
}

function roundMoney(n: number): number {
  return Math.round(n * 100) / 100;
}

/** هل يوجد COD على المندوب/شركة الشحن؟ */
export function allowsManualShippingCollection(order: OrderCollectContext): boolean {
  return shippingCodAmount(order) > 0.009;
}

/** هل يُسمح بتحصيل الطلب (شحن و/أو شركة تحصيل)؟ */
export function allowsManualOrderCollection(order: OrderCollectContext): boolean {
  return allowsManualShippingCollection(order) || openCollectionReceivableAmount(order) > 0.009;
}

export function manualCollectionBlockedMessage(order: OrderCollectContext): string {
  return 'لا يوجد مبلغ مفتوح للتحصيل على هذا الطلب.';
}

export function canShowCollectOrderMenu(
  order: OrderCollectContext & { order_status?: string; order_type?: string }
): boolean {
  const status = order?.order_status ?? '';
  const maintenanceOk = order?.order_type !== 'طلب صيانة' || !!order?.order_details?.maintenance_date;
  if (!(['تم شحن', 'تم التسليم'].includes(status) && maintenanceOk)) {
    return false;
  }
  return allowsManualOrderCollection(order);
}

/** وصف مصدر التحصيل للعرض في الشاشة */
export function collectAmountBreakdownLabel(order: OrderCollectContext): string | null {
  const ship = shippingCodAmount(order);
  const coll = openCollectionReceivableAmount(order);
  if (ship > 0.009 && coll > 0.009) {
    return `مندوب: ${ship.toFixed(2)} + شركة تحصيل: ${coll.toFixed(2)}`;
  }
  if (coll > 0.009 && ship <= 0.009) {
    return 'ذمة شركة التحصيل (مدفوع إلكترونياً / Paymob)';
  }
  return null;
}
