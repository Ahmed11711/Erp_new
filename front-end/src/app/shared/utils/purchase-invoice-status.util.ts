/** ألوان/تنسيق حالة فاتورة المشتريات — مشترك بين القائمة وصفحة التفاصيل */
export function purchaseStatusBadgeClass(status: string | null | undefined): string {
  const key = (status ?? '').trim();
  const map: Record<string, string> = {
    'اضافة وارد جديد': 'pp-status pp-status--new',
    'تم الاستلام': 'pp-status pp-status--received',
    'اضافة وارد تشغيل': 'pp-status pp-status--new',
    'مرتجع': 'pp-status pp-status--return',
    'مرتجع مبيعات': 'pp-status pp-status--sales-return',
    'امانات': 'pp-status pp-status--amanat',
  };

  return map[key] ?? 'pp-status pp-status--default';
}
