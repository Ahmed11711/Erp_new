import 'package:flutter/material.dart';

/// Full Magalis web sidebar — same order as `dashboard.component.html`
/// (admin view with accounting section visible).
enum MenuTarget {
  home,
  orders,
  offers,
  notifications,
  placeholder,
}

class MenuLeaf {
  const MenuLeaf({
    required this.title,
    this.target = MenuTarget.placeholder,
    this.routeHint,
  });

  final String title;
  final MenuTarget target;
  final String? routeHint;
}

class MenuGroup {
  const MenuGroup({
    required this.title,
    required this.icon,
    this.children = const [],
    this.nestedGroups = const [],
  });

  final String title;
  final IconData icon;
  final List<MenuLeaf> children;
  final List<MenuGroup> nestedGroups;
}

class MenuTopItem {
  const MenuTopItem({
    required this.title,
    required this.icon,
    this.target = MenuTarget.placeholder,
    this.routeHint,
    this.active = false,
  });

  final String title;
  final IconData icon;
  final MenuTarget target;
  final String? routeHint;
  final bool active;
}

class AppMenu {
  AppMenu._();

  /// Top links (before accordion) — matches web screenshot.
  static const List<MenuTopItem> topItems = [
    MenuTopItem(
      title: 'الصفحة الرئيسية',
      icon: Icons.dashboard,
      target: MenuTarget.home,
      active: true,
    ),
    MenuTopItem(
      title: 'محادثات واتساب',
      icon: Icons.chat,
      routeHint: '/dashboard/whatsapp/chat',
    ),
  ];

  /// Accordion sections — same sequence as web sidenav.
  static const List<MenuGroup> groups = [
    MenuGroup(
      title: 'المخازن',
      icon: Icons.warehouse,
      children: [
        MenuLeaf(title: 'المخازن', routeHint: '/dashboard/warehouse/list'),
        MenuLeaf(title: 'الجرد الشهري', routeHint: '/dashboard/warehouse/listwarhouse'),
        MenuLeaf(title: 'استيراد Excel والجرد', routeHint: '/dashboard/warehouse/inventory-import'),
        MenuLeaf(title: 'تقرير المخزون', routeHint: '/dashboard/reports/storage'),
      ],
    ),
    MenuGroup(
      title: 'الأصناف',
      icon: Icons.widgets,
      children: [
        MenuLeaf(title: 'إضافة صنف', routeHint: '/dashboard/categories/add_category'),
        MenuLeaf(title: 'الأصناف', routeHint: '/dashboard/categories/all_categories'),
        MenuLeaf(title: 'وحدات القياس', routeHint: '/dashboard/categories/units'),
        MenuLeaf(title: 'تصنيفات الأصناف', routeHint: '/dashboard/categories/classifications'),
        MenuLeaf(title: 'خطوط الإنتاج', routeHint: '/dashboard/categories/production'),
      ],
    ),
    MenuGroup(
      title: 'التشغيل الخارجي',
      icon: Icons.build_circle,
      children: [
        MenuLeaf(title: 'لوحة التشغيل الخارجي', routeHint: '/dashboard/processing/dashboard'),
        MenuLeaf(title: 'إذونات الصرف والاستلام', routeHint: '/dashboard/processing/orders'),
      ],
    ),
    MenuGroup(
      title: 'الموردين',
      icon: Icons.person_outline,
      children: [
        MenuLeaf(title: 'إضافة مورد', routeHint: '/dashboard/suppliers/add_supplier'),
        MenuLeaf(title: 'الموردين', routeHint: '/dashboard/suppliers/list_suppliers'),
        MenuLeaf(title: 'فئات الموردين', routeHint: '/dashboard/suppliers/types'),
      ],
    ),
    MenuGroup(
      title: 'المشتريات',
      icon: Icons.shopping_cart_outlined,
      children: [
        MenuLeaf(title: 'إضافة فاتورة', routeHint: '/dashboard/purchases/add_invoice'),
        MenuLeaf(title: 'المشتريات', routeHint: '/dashboard/purchases/list_invoice'),
        MenuLeaf(title: 'تقرير المشتريات', routeHint: '/dashboard/reports/procurement'),
      ],
    ),
    MenuGroup(
      title: 'التصنيع',
      icon: Icons.precision_manufacturing,
      children: [
        MenuLeaf(title: 'إضافة وصفة', routeHint: '/dashboard/manufacturing/addrecipe'),
        MenuLeaf(title: 'وصفات التصنيع', routeHint: '/dashboard/manufacturing/recipes'),
        MenuLeaf(title: 'أصناف بدون وصفة', routeHint: '/dashboard/manufacturing/items-without-recipes'),
        MenuLeaf(title: 'قائمة المواد (BOM)', routeHint: '/dashboard/manufacturing/bom'),
        MenuLeaf(title: 'تأكيد أمر التصنيع', routeHint: '/dashboard/manufacturing/confirmation'),
        MenuLeaf(title: 'أوامر التصنيع', routeHint: '/dashboard/manufacturing/orders'),
        MenuLeaf(title: 'الإضافات الخاصة', routeHint: '/dashboard/manufacturing/additions'),
      ],
    ),
    MenuGroup(
      title: 'الحسابات',
      icon: Icons.account_balance,
      nestedGroups: [
        MenuGroup(
          title: 'شجرة الحسابات',
          icon: Icons.account_tree,
          children: [
            MenuLeaf(title: 'شجرة الحسابات', routeHint: '/dashboard/accounting/accounting-tree'),
            MenuLeaf(title: 'دليل الحسابات', routeHint: '/dashboard/accounting/general-accounts'),
            MenuLeaf(title: 'مراكز التكلفة', routeHint: '/dashboard/accounting/cost-centers'),
          ],
        ),
        MenuGroup(
          title: 'الخزن والبنوك',
          icon: Icons.savings,
          children: [
            MenuLeaf(title: 'الخزن', routeHint: '/dashboard/accounting/safes'),
            MenuLeaf(title: 'البنوك', routeHint: '/dashboard/accounting/banks'),
            MenuLeaf(title: 'تحويل من بنك إلى خزينة', routeHint: '/dashboard/accounting/banks/transfer-to-safe'),
            MenuLeaf(title: 'تحويل من خزينة إلى بنك', routeHint: '/dashboard/accounting/banks/transfer-from-safe'),
            MenuLeaf(title: 'حسابات الخدمات', routeHint: '/dashboard/financial/service-accounts'),
            MenuLeaf(title: 'سحب وإيداع', routeHint: '/dashboard/accounting/banks/deposit-withdraw'),
            MenuLeaf(title: 'قيد الانتظار', routeHint: '/dashboard/financial/pendingBanks'),
          ],
        ),
        MenuGroup(
          title: 'العملاء والموردين',
          icon: Icons.people,
          children: [
            MenuLeaf(title: 'حسابات العملاء', routeHint: '/dashboard/financial/customer-accounts'),
            MenuLeaf(title: 'العملاء الأفراد', routeHint: '/dashboard/financial/individualsclients'),
            MenuLeaf(title: 'عملاء الشركات', routeHint: '/dashboard/shipping/companies'),
            MenuLeaf(title: 'حسابات الموردين', routeHint: '/dashboard/financial/supplier-accounts'),
            MenuLeaf(title: 'تقرير الأوردرات', routeHint: '/dashboard/financial/report-order-new'),
          ],
        ),
        MenuGroup(
          title: 'السندات',
          icon: Icons.receipt_long,
          children: [
            MenuLeaf(title: 'سندات العملاء', routeHint: '/dashboard/accounting/vouchers/clients'),
            MenuLeaf(title: 'سندات الموردين', routeHint: '/dashboard/accounting/vouchers/suppliers'),
          ],
        ),
        MenuGroup(
          title: 'العمليات المالية',
          icon: Icons.payments,
          children: [
            MenuLeaf(title: 'نقد وارد (Cash In)', routeHint: '/dashboard/financial/cash-in'),
            MenuLeaf(title: 'نقد صادر (Cash Out)', routeHint: '/dashboard/financial/cash-out'),
          ],
        ),
        MenuGroup(
          title: 'رأس المال والإيرادات',
          icon: Icons.trending_up,
          children: [
            MenuLeaf(title: 'إضافة رأس مال', routeHint: '/dashboard/financial/capitals/create'),
            MenuLeaf(title: 'مبالغ رأس المال', routeHint: '/dashboard/financial/capitals'),
            MenuLeaf(title: 'إضافة إيراد', routeHint: '/dashboard/financial/addincome'),
            MenuLeaf(title: 'الإيرادات الأخرى', routeHint: '/dashboard/financial/otherincome'),
            MenuLeaf(title: 'الخصومات والالتزامات', routeHint: '/dashboard/financial/discounts'),
            MenuLeaf(title: 'إضافة التزام', routeHint: '/dashboard/financial/addcimmitment'),
            MenuLeaf(title: 'العهد', routeHint: '/dashboard/financial/covenant'),
            MenuLeaf(title: 'صرف العهد', routeHint: '/dashboard/financial/addcovenant'),
          ],
        ),
        MenuGroup(
          title: 'الأصول الثابتة',
          icon: Icons.business,
          children: [
            MenuLeaf(title: 'قائمة الأصول', routeHint: '/dashboard/accounting/fixed-assets'),
            MenuLeaf(title: 'إضافة أصل', routeHint: '/dashboard/accounting/fixed-assets/create'),
            MenuLeaf(title: 'الإهلاك', routeHint: '/dashboard/accounting/fixed-assets/depreciation'),
          ],
        ),
        MenuGroup(
          title: 'القيود اليومية',
          icon: Icons.menu_book,
          children: [
            MenuLeaf(title: 'القيود اليومية', routeHint: '/dashboard/accounting/daily-entries'),
            MenuLeaf(title: 'تقرير دفتر اليومية', routeHint: '/dashboard/accounting/daily-ledger-report'),
          ],
        ),
        MenuGroup(
          title: 'تقارير الحسابات',
          icon: Icons.assessment,
          children: [
            MenuLeaf(title: 'ميزان المراجعة', routeHint: '/dashboard/accounting/trial-balance'),
            MenuLeaf(title: 'كشف حساب تفصيلي', routeHint: '/dashboard/reports/financialstatement'),
            MenuLeaf(title: 'قائمة الدخل', routeHint: '/dashboard/financial/income-list'),
            MenuLeaf(title: 'تقرير أداء المنتجات', routeHint: '/dashboard/reports/product-performance'),
            MenuLeaf(title: 'تقرير المشتريات', routeHint: '/dashboard/reports/procurement'),
            MenuLeaf(title: 'حركة المبيعات', routeHint: '/dashboard/shipping/sales-movement'),
            MenuLeaf(title: 'تقرير مبيعات المنتجات', routeHint: '/dashboard/reports/productsales'),
            MenuLeaf(title: 'تقارير الأصناف', routeHint: '/dashboard/categoriesreports'),
            MenuLeaf(title: 'تقرير المخزون', routeHint: '/dashboard/reports/storage'),
            MenuLeaf(title: 'تقرير ربحية الصنف', routeHint: '/dashboard/reports/category'),
            MenuLeaf(title: 'تقارير ذمم الشحن والتحصيل', routeHint: '/dashboard/shipping/shipping-accounts-report'),
            MenuLeaf(title: 'تقرير شركات الشحن', routeHint: '/dashboard/reports/shippingcompany'),
          ],
        ),
      ],
    ),
    MenuGroup(
      title: 'إدارة الشحن',
      icon: Icons.local_shipping,
      children: [
        MenuLeaf(title: 'إضافة طلب', routeHint: '/dashboard/shipping/addorder'),
        MenuLeaf(title: 'الطلبات', target: MenuTarget.orders, routeHint: '/dashboard/shipping/listorders'),
        MenuLeaf(title: 'حركة المبيعات', routeHint: '/dashboard/shipping/sales-movement'),
        MenuLeaf(
          title: 'طلبات عروض الأسعار',
          target: MenuTarget.offers,
          routeHint: '/dashboard/shipping/offer-orders',
        ),
        MenuLeaf(title: 'شركات الشحن والمندوبين', routeHint: '/dashboard/shipping/shippingcompany'),
        MenuLeaf(title: 'شركات التحصيل والدفع', routeHint: '/dashboard/shipping/collection-companies'),
        MenuLeaf(title: 'مصادر الطلبات', routeHint: '/dashboard/shipping/ordersource'),
        MenuLeaf(title: 'طرق الشحن', routeHint: '/dashboard/shipping/shippingway'),
        MenuLeaf(title: 'خطوط الشحن', routeHint: '/dashboard/shipping/shippingline'),
        MenuLeaf(title: 'بيان خط الشحن', routeHint: '/dashboard/shipping/shipping-line-statement'),
        MenuLeaf(title: 'ذمم المناديب وشركات الشحن', routeHint: '/dashboard/shipping/shipping-accounts-report'),
        MenuLeaf(title: 'ذمم شركات التحصيل والدفع', routeHint: '/dashboard/shipping/collection-accounts-report'),
      ],
      nestedGroups: [
        MenuGroup(
          title: 'تكامل Shopify',
          icon: Icons.storefront,
          children: [
            MenuLeaf(title: 'لوحة الطلبات والمنتجات', routeHint: '/dashboard/shopify'),
            MenuLeaf(title: 'إعدادات المزامنة والاتصال', routeHint: '/dashboard/system/settings'),
          ],
        ),
      ],
    ),
    MenuGroup(
      title: 'HR',
      icon: Icons.badge,
      children: [
        MenuLeaf(title: 'إضافة موظف', routeHint: '/dashboard/hr/addemployee'),
        MenuLeaf(title: 'الموظفين', routeHint: '/dashboard/hr/employee'),
        MenuLeaf(title: 'كشف المرتبات', routeHint: '/dashboard/hr/payroll'),
        MenuLeaf(title: 'مراجعة الغيابات', routeHint: '/dashboard/hr/reviewabsencess'),
        MenuLeaf(title: 'كشف الحضور والغياب', routeHint: '/dashboard/hr/workinghours'),
      ],
    ),
    MenuGroup(
      title: 'الإيصالات والأذونات',
      icon: Icons.description,
      children: [
        MenuLeaf(title: 'إذن استلام', routeHint: '/dashboard/permissions/receive'),
        MenuLeaf(title: 'إيصال استلام شيك', routeHint: '/dashboard/permissions/receivecheck'),
        MenuLeaf(title: 'إيصال استلام نقدية', routeHint: '/dashboard/permissions/receivemoney'),
        MenuLeaf(title: 'إضافة عرض سعر 1', routeHint: '/dashboard/permissions/priceoffer1'),
        MenuLeaf(title: 'إضافة عرض سعر 2', routeHint: '/dashboard/permissions/priceoffer2'),
        MenuLeaf(title: 'عروض الأسعار', target: MenuTarget.offers, routeHint: '/dashboard/permissions/priceoffer'),
        MenuLeaf(
          title: 'طلبات عروض الأسعار',
          target: MenuTarget.offers,
          routeHint: '/dashboard/shipping/offer-orders',
        ),
        MenuLeaf(title: 'شروط عروض الأسعار', routeHint: '/dashboard/permissions/offerconditions'),
        MenuLeaf(title: 'الحسابات البنكية', routeHint: '/dashboard/permissions/banksacounts'),
        MenuLeaf(title: 'الأوصاف', routeHint: '/dashboard/permissions/descriptions'),
        MenuLeaf(title: 'تكويد الطلبات', routeHint: '/dashboard/permissions/ordercoding'),
        MenuLeaf(title: 'سند تسليم الطلبات', routeHint: '/dashboard/permissions/orderdocument'),
        MenuLeaf(title: 'طباعة فواتير الطلبات', routeHint: '/dashboard/permissions/orderprint'),
      ],
    ),
    MenuGroup(
      title: 'Admin',
      icon: Icons.settings,
      children: [
        MenuLeaf(title: 'إضافة مستخدم', routeHint: '/dashboard/system/adduser'),
        MenuLeaf(title: 'المستخدمين', routeHint: '/dashboard/system/users'),
        MenuLeaf(title: 'إدارة الأدوار والصلاحيات', routeHint: '/dashboard/system/rbac-roles'),
        MenuLeaf(title: 'صلاحيات المستخدمين (كاملة)', routeHint: '/dashboard/system/powers'),
        MenuLeaf(title: 'الأوردرات', routeHint: '/dashboard/admin/adminorder'),
        MenuLeaf(title: 'التتبع', routeHint: '/dashboard/admin/tracking'),
        MenuLeaf(title: 'سجل النشاط', routeHint: '/dashboard/admin/activity-log'),
        MenuLeaf(title: 'إدارة أرقام الواتساب', routeHint: '/dashboard/admin/whatsapp-management'),
      ],
    ),
    MenuGroup(
      title: 'Corporate Sales',
      icon: Icons.corporate_fare,
      children: [
        MenuLeaf(title: 'Add Lead', routeHint: '/dashboard/corparates-sales/add-lead'),
        MenuLeaf(title: 'Leads', routeHint: '/dashboard/corparates-sales/leads'),
        MenuLeaf(title: 'Lead Status Management', routeHint: '/dashboard/corparates-sales/lead-status-management'),
        MenuLeaf(title: 'Follow Up Leads', routeHint: '/dashboard/corparates-sales/follow-up-leads'),
        MenuLeaf(title: 'Lead Activity Report', routeHint: '/dashboard/reports/lead-activity'),
      ],
    ),
  ];
}
