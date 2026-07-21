/** عناوين عربية لوحدات الصلاحيات كما تُرجعها واجهة API (مفتاح المجموعة). */
export const RBAC_MODULE_AR: Record<string, string> = {
  orders: 'الطلبات',
  orders_statuses: 'حالات الطلبات (عرض)',
  finance: 'المالية والمحاسبة',
  employees: 'الموارد البشرية',
  inventory: 'المخزون والمخازن',
  settings: 'إعدادات النظام',
  system: 'إدارة النظام والصلاحيات',
  whatsapp: 'واتساب',
  categories: 'الأصناف والفئات',
  suppliers: 'الموردين',
  purchases: 'المشتريات',
  manufacturing: 'التصنيع',
  processing: 'التشغيل الخارجي',
  notifications: 'الإشعارات',
  nav: 'القوائم والتنقل في الواجهة',
  shipping: 'شركات الشحن والمندوبين',
  customers: 'عملاء الشركات',
  offers: 'عروض الأسعار',
  general: 'عام',
};

/** ترجمة معرّف الصلاحية (slug) — يُعرَض الاسم العربي مع الإبقاء على slug تقني أسفله. */
export const RBAC_PERMISSION_AR: Record<string, string> = {
  'orders.view': 'عرض الطلبات',
  'orders.create': 'إنشاء طلبات',
  'orders.convert_from_offer': 'تحويل عرض السعر إلى طلب',
  'orders.edit': 'تعديل الطلبات',
  'orders.cancel_line': 'إلغاء أصناف من الطلب',
  'orders.delete': 'حذف الطلبات',
  'orders.change_status': 'تغيير حالة الطلب',
  'orders.export': 'تصدير الطلبات',
  'orders.assign_driver': 'تعيين مندوب توصيل',
  'orders.shopify.review': 'مراجعة طلبات Shopify المستوردة',
  'orders.fulfillment.view': 'عرض لوحة تنفيذ الطلب',
  'orders.liability.transfer': 'نقل مسؤولية الطلب إلى المحصّل',

  'orders.view_status.new': 'عرض الطلبات الجديدة',
  'orders.view_status.confirmed': 'عرض الطلبات المؤكدة',
  'orders.view_status.partial_ship': 'عرض الطلبات — شحن جزئي',
  'orders.view_status.shipped': 'عرض الطلبات المشحونة',
  'orders.view_status.delivered': 'عرض الطلبات المُسلَّمة',
  'orders.view_status.received': 'عرض الطلبات المُستلمة',
  'orders.view_status.collected': 'عرض الطلبات المحصّلة',
  'orders.view_status.postponed': 'عرض الطلبات المؤجلة',
  'orders.view_status.archived': 'عرض الطلبات المؤرشفة',
  'orders.view_status.maintained': 'عرض طلبات الصيانة',
  'orders.view_status.refused': 'عرض طلبات رفض الاستلام',
  'orders.view_status.cancelled': 'عرض الطلبات الملغاة',

  'finance.view': 'عرض الحركات والمالية',
  'finance.create': 'إنشاء قيود وسندات مالية',
  'finance.edit': 'تعديل البيانات المالية',
  'finance.account_statement.edit': 'فتح وتعديل المعاملات من كشف الحساب التفصيلي',
  'finance.delete': 'حذف البيانات المالية',
  'finance.approve': 'اعتماد العمليات المالية',

  'employees.view': 'عرض بيانات الموظفين',
  'employees.create': 'إضافة موظفين',
  'employees.edit': 'تعديل بيانات الموظفين',
  'employees.delete': 'حذف موظفين',
  'employees.attendance': 'الحضور والانصراف',
  'employees.salary': 'المرتبات',

  'inventory.view': 'عرض المخزون',
  'inventory.create': 'إضافة مخزون',
  'inventory.edit': 'تعديل المخزون',
  'inventory.delete': 'حذف سجلات مخزون',
  'inventory.transfer': 'تحويلات المخزون',

  'settings.view': 'عرض الإعدادات',
  'settings.edit': 'تعديل الإعدادات',

  'system.rbac': 'إدارة الأدوار والصلاحيات',
  'system.activity_log': 'عرض سجل النشاط',
  'system.activity_log.edit': 'فتح الكيان من سجل النشاط للتعديل',

  'whatsapp.assign_numbers': 'تعيين المستخدمين على أرقام واتساب',

  'categories.view': 'عرض الأصناف',
  'categories.manage': 'إدارة الأصناف والوحدات',

  'suppliers.view': 'عرض الموردين',
  'suppliers.delete': 'حذف مورد وحسابه في الشجرة',
  'suppliers.purge_all': 'حذف جميع الموردين وحساباتهم',
  'expenses.purge_all': 'حذف جميع المصروفات وقيودها',

  'purchases.view': 'عرض المشتريات',

  'manufacturing.view': 'عرض التصنيع والوصفات',
  'manufacturing.edit_recipe': 'تعديل وصفات التصنيع',
  'manufacturing.delete_recipe': 'حذف وصفات التصنيع',
  'manufacturing.delete_order': 'حذف أوامر التصنيع',
  'processing.view': 'عرض التشغيل الخارجي',
  'processing.create': 'إنشاء مستندات التشغيل الخارجي',
  'processing.post': 'ترحيل مستندات التشغيل الخارجي',
  'processing.delete_order': 'حذف أوامر التشغيل الخارجي',
  'nav.processing': 'قائمة: التشغيل الخارجي',

  'notifications.review_filter': 'تصفية ومراجعة الإشعارات',

  'nav.receipts': 'قائمة: الإيصالات والأذونات',
  'nav.receipts.quotes': 'قائمة: عروض الأسعار',
  'offers.view_all': 'عرض كل عروض الأسعار',
  'nav.receipts.admin': 'قائمة: نماذج إدارية للإيصالات',
  'nav.corporate': 'قائمة: مبيعات الشركات',
  'nav.shopify': 'قائمة: تكامل Shopify (كامل — قديم)',
  'nav.shopify.dashboard': 'Shopify: لوحة الطلبات والمنتجات',
  'nav.shopify.settings': 'Shopify: المزامنة اليدوية واختبار الاتصال',
  'nav.shipping.master': 'قائمة: إعدادات الشحن الرئيسية',
  'nav.shipping.accounts_report': 'قائمة: تقارير ذمم الشحن والتحصيل',
  'shipping.companies.manage': 'تعديل وحذف شركات الشحن والمندوبين',
  'shipping.companies.statement': 'كشف حساب شركة شحن أو مندوب',
  'collection_companies.manage': 'إدارة شركات التحصيل',
  'settlements.manage': 'إدارة تسويات الدفع عند الاستلام',
  'customer_companies.view': 'عرض قائمة عملاء الشركات',
  'customer_companies.manage': 'إضافة وتعديل وربط عملاء الشركات',
  'customer_companies.statement': 'كشف حساب عميل شركة',
  'customer_companies.collect': 'تحصيل من عميل شركة',
  'nav.whatsapp_chat': 'قائمة: محادثة واتساب بدون تعيين رقم',
};

export function rbacModuleLabelAr(moduleKey: string): string {
  const k = String(moduleKey ?? '').toLowerCase().trim();
  return RBAC_MODULE_AR[k] ?? moduleKey;
}

export function rbacPermissionLabelAr(slug: string, fallbackName: string): string {
  const s = String(slug ?? '').toLowerCase().trim();
  return RBAC_PERMISSION_AR[s] ?? fallbackName;
}
