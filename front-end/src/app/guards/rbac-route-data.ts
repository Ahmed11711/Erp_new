/**
 * مجموعات slug للـ RBAC مع departmentGuard (data.rbacPermissions فقط).
 * تُظهر أي صلاحية واحدة ضمن المصفوفة دخولاً للمسار (canAny).
 */
export const RBAC_ROUTE = {
  financeTeam: ['finance.view', 'finance.edit'],
  /** شاشات كانت مقتصرة سابقاً على قسم Admin */
  financeAdminExclusive: ['system.rbac'],
  inventoryWarehouse: ['inventory.view', 'inventory.transfer', 'inventory.create', 'inventory.edit'],
  categoriesAll: ['categories.view', 'categories.manage'],
  suppliers: ['suppliers.view'],
  purchases: ['purchases.view'],
  manufacturingAll: ['manufacturing.view'],
  manufacturingWrite: ['manufacturing.edit_recipe', 'categories.manage', 'system.rbac'],
  processingAll: ['processing.view', 'purchases.view', 'suppliers.view'],
  ordersCreate: ['orders.create'],
  ordersView: ['orders.view'],
  ordersEdit: ['orders.edit'],
  ordersFulfillment: ['orders.change_status', 'orders.assign_driver'],
  ordersOpsWide: ['orders.view', 'orders.create', 'orders.edit', 'orders.change_status', 'orders.assign_driver'],
  shippingMaster: ['nav.shipping.master', 'system.rbac'],
  /** تقارير ذمم المندوبين وشركات الشحن وشركات التحصيل */
  shippingAccountsReport: ['nav.shipping.accounts_report', 'system.rbac'],
  /** يطابق order_rbac_profiles.shipping_company_crud (أقسام + أيّ من هذه الـ slugs) */
  shippingCompanyCrud: ['nav.shipping.master', 'orders.view', 'system.rbac'],
  /** تعديل وحذف (وإضافة) شركات الشحن والمندوبين */
  shippingCompanyManage: ['shipping.companies.manage', 'nav.shipping.master', 'system.rbac'],
  /** كشف حساب شركة شحن / مندوب */
  shippingCompanyStatement: ['shipping.companies.statement', 'nav.shipping.master', 'orders.view', 'system.rbac'],
  /** عرض قائمة عملاء الشركات */
  customerCompaniesView: ['customer_companies.view', 'orders.view', 'finance.view', 'system.rbac'],
  /** إضافة وتعديل وربط عملاء الشركات */
  customerCompaniesManage: ['customer_companies.manage', 'system.rbac'],
  /** كشف حساب عميل شركة */
  customerCompaniesStatement: ['customer_companies.statement', 'orders.view', 'finance.view', 'system.rbac'],
  /** تحصيل من عميل شركة */
  customerCompaniesCollect: ['customer_companies.collect', 'finance.edit', 'system.rbac'],
  /** لوحة طلبات/منتجات Shopify (الشحن) */
  shopifyDashboard: ['nav.shopify.dashboard', 'nav.shopify', 'system.rbac'],
  /** مزامنة يدوية، اختبار الاتصال، خرائط المنتجات */
  shopifySettings: ['nav.shopify.settings', 'nav.shopify', 'system.rbac'],
  /** مراجعة وتأكيد بيانات طلبات Shopify المستوردة */
  shopifyOrderReview: ['orders.shopify.review', 'nav.shopify.dashboard', 'nav.shopify', 'system.rbac'],
  corporate: ['nav.corporate', 'system.rbac'],
  receiptsAdmin: ['nav.receipts.admin', 'system.rbac'],
  receiptsQuotes: ['nav.receipts.quotes', 'orders.view'],
  systemAdmin: ['system.rbac'],
  settingsManage: ['settings.view', 'settings.edit', 'system.rbac'],
  hrEmployees: ['employees.view', 'employees.create', 'employees.edit'],
  hrAttendance: ['employees.attendance'],
  hrPayroll: ['employees.salary', 'finance.edit'],
  /** تغطية مسارات الموارد البشرية العامة */
  hrWide: ['employees.view', 'employees.create', 'employees.edit', 'employees.attendance', 'employees.salary'],
  approvals: ['finance.approve', 'system.rbac'],
  reportsFinance: ['finance.view', 'finance.edit'],
  /** تقارير كانت مقتصرة على system.rbac — يبقى المدير الكامل ضمنها */
  reportsAdmin: ['system.rbac'],
  reportsCorporate: ['nav.corporate', 'system.rbac'],
  categoriesReportsHome: ['categories.view', 'finance.view'],
  /** ظهور قسم «تقارير الحسابات» في القائمة */
  reportsSection: [
    'finance.view',
    'finance.edit',
    'inventory.view',
    'inventory.transfer',
    'categories.view',
    'purchases.view',
    'suppliers.view',
    'orders.view',
    'nav.shipping.accounts_report',
    'system.rbac',
  ],
  reportsWarehouse: [
    'inventory.view',
    'inventory.transfer',
    'inventory.create',
    'inventory.edit',
    'categories.view',
    'finance.view',
    'system.rbac',
  ],
  reportsProcurement: [
    'purchases.view',
    'suppliers.view',
    'finance.view',
    'finance.edit',
    'system.rbac',
  ],
  reportsProductSales: [
    'categories.view',
    'orders.view',
    'finance.view',
    'system.rbac',
  ],
  reportsCategoryProfit: [
    'categories.view',
    'finance.view',
    'finance.edit',
    'system.rbac',
  ],
  reportsShippingCompanies: [
    'orders.view',
    'nav.shipping.master',
    'system.rbac',
  ],
  reportsAccountingWide: [
    'finance.view',
    'finance.edit',
    'inventory.transfer',
    'system.rbac',
  ],
} as const satisfies Record<string, readonly string[]>;
