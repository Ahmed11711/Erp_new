/** حالات الطلب التي لا يُسمح فيها برفض الاستلام. */

export const ORDER_REFUSE_BLOCKED_STATUSES = ['رفض استلام', 'تم التحصيل', 'ملغي', 'أرشيف'] as const;



/** حالات سطر شركة الشحن المفتوح لرفض الاستلام. */

export const REFUSE_OPEN_SHIPPING_ROW_STATUSES = ['تم شحن', 'تم التسليم'] as const;



export function normalizeOrderStatus(status: unknown): string {

  return String(status ?? '').trim();

}



export function isRefuseEligibleOrderStatus(status: unknown): boolean {

  const st = normalizeOrderStatus(status);

  if (!st) {

    return false;

  }

  return !(ORDER_REFUSE_BLOCKED_STATUSES as readonly string[]).includes(st);

}



export function isCompanyCustomerType(customerType: unknown): boolean {

  return String(customerType ?? '').trim() === 'شركة';

}



/** أي نوع عميل غير «شركة» يُعامل كفرد في مسار refuseOrder. */

export function isIndividualCustomerType(customerType: unknown): boolean {

  return !isCompanyCustomerType(customerType);

}



export function canRefuseFromShippingRow(

  orderStatus: unknown,

  rowStatus: unknown,

  isDone: unknown

): boolean {

  const os = normalizeOrderStatus(orderStatus);

  if (!isRefuseEligibleOrderStatus(os)) {

    return false;

  }

  const done = isDone === true || isDone === 1 || isDone === '1';

  if (done) {

    return false;

  }

  const st = normalizeOrderStatus(rowStatus);

  return (REFUSE_OPEN_SHIPPING_ROW_STATUSES as readonly string[]).includes(st);

}

