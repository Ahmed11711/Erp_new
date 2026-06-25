type OrderCollectContext = {
  net_total?: number;
  prepaid_amount?: number;
  prepaid_payment_type?: string | null;
  order_details?: {
    collection_provider_type?: string | null;
    shipping_receivable_amount?: number | null;
    collection_receivable_amount?: number | null;
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

/** ذمة شركة التحصيل المفتوحة */
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

/** هل يظهر زر «تحصيل الطلب» اليدوي؟ */
export function allowsManualShippingCollection(order: OrderCollectContext): boolean {
  return shippingCodAmount(order) > 0.009;
}

export function manualCollectionBlockedMessage(order: OrderCollectContext): string {
  if (openCollectionReceivableAmount(order) > 0.009 && !allowsManualShippingCollection(order)) {
    return 'هذا الطلب مدفوع مقدماً عبر شركة تحصيل — يُسوَّى عبر «نقد وارد / شركة تحصيل» وليس «تحصيل الطلب».';
  }
  return 'لا يوجد مبلغ للتحصيل من شركة الشحن/المندوب على هذا الطلب.';
}

export function canShowCollectOrderMenu(
  order: OrderCollectContext & { order_status?: string; order_type?: string; order_details?: { maintenance_date?: string | null } | null }
): boolean {
  const status = order?.order_status ?? '';
  const maintenanceOk = order?.order_type !== 'طلب صيانة' || !!order?.order_details?.maintenance_date;
  if (!(['تم شحن', 'تم التسليم'].includes(status) && maintenanceOk)) {
    return false;
  }
  return allowsManualShippingCollection(order);
}
