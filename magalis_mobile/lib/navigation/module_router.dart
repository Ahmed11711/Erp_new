import 'package:flutter/material.dart';

import '../navigation/module_catalog.dart';
import '../screens/accounting/accounting_tree_screen.dart';
import '../screens/accounting/cost_centers_screen.dart';
import '../screens/accounting/general_accounts_screen.dart';
import '../screens/modules/api_list_screen.dart';
import '../screens/placeholder_screen.dart';
import '../screens/categories/add_category_screen.dart';
import '../screens/categories/categories_list_screen.dart';
import '../screens/categories/category_lookup_admin_screen.dart';
import '../screens/manufacturing/add_recipe_screen.dart';
import '../screens/manufacturing/bom_list_screen.dart';
import '../screens/manufacturing/items_without_recipe_screen.dart';
import '../screens/manufacturing/manufacturing_additions_screen.dart';
import '../screens/manufacturing/manufacturing_confirmation_screen.dart';
import '../screens/manufacturing/manufacturing_orders_screen.dart';
import '../screens/manufacturing/recipes_list_screen.dart';
import '../screens/processing/processing_dashboard_screen.dart';
import '../screens/processing/processing_orders_screen.dart';
import '../screens/accounting/vouchers_list_screen.dart';
import '../screens/customers/companies_list_screen.dart';
import '../screens/customers/customer_accounts_screen.dart';
import '../screens/customers/orders_report_screen.dart';
import '../screens/customers/supplier_accounts_screen.dart';
import '../screens/purchases/add_purchase_invoice_screen.dart';
import '../screens/purchases/purchases_list_screen.dart';
import '../screens/suppliers/add_supplier_screen.dart';
import '../screens/suppliers/suppliers_list_screen.dart';
import '../screens/suppliers/supplier_types_screen.dart';
import '../screens/warehouse/monthly_inventory_hub_screen.dart';
import '../screens/warehouse/storage_report_screen.dart';
import '../screens/warehouse/warehouses_list_screen.dart';

class ModuleRouter {
  ModuleRouter._();

  static void open(
    BuildContext context, {
    required String title,
    String? routeHint,
  }) {
    final key = (routeHint ?? '').trim().toLowerCase();

    // إضافة صنف (Angular /categories/add_category)
    if (key.contains('/categories/add_category')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const AddCategoryScreen()),
      );
      return;
    }

    // قائمة الأصناف (Angular /categories/all_categories)
    if (key.contains('/categories/all_categories')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const CategoriesListScreen()),
      );
      return;
    }

    // وحدات القياس
    if (key.contains('/categories/units')) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => const CategoryLookupAdminScreen(
            kind: CategoryLookupKind.units,
          ),
        ),
      );
      return;
    }

    // تصنيفات الأصناف
    if (key.contains('/categories/classifications')) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => const CategoryLookupAdminScreen(
            kind: CategoryLookupKind.classifications,
          ),
        ),
      );
      return;
    }

    // خطوط الإنتاج
    if (key.contains('/categories/production')) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => const CategoryLookupAdminScreen(
            kind: CategoryLookupKind.productions,
          ),
        ),
      );
      return;
    }

    // قائمة المخازن (Angular /warehouse/list) — أرصدة + تفاصيل/تتبع/تحويلات
    if (key.contains('/warehouse/list') && !key.contains('listwarhouse')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const WarehousesListScreen()),
      );
      return;
    }

    // الجرد الشهري hub (Angular listwarhouse)
    if (key.contains('listwarhouse') || key.contains('/warehouse/monthlyinventory')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const MonthlyInventoryHubScreen()),
      );
      return;
    }

    // شجرة الحسابات
    if (key.contains('accounting-tree') || key.contains('accounting_tree')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const AccountingTreeScreen()),
      );
      return;
    }

    // دليل الحسابات (Angular /accounting/general-accounts)
    if (key.contains('general-accounts') || key.contains('general_accounts')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const GeneralAccountsScreen()),
      );
      return;
    }

    // مراكز التكلفة (Angular /accounting/cost-centers)
    if (key.contains('cost-centers') || key.contains('cost_centers')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const CostCentersScreen()),
      );
      return;
    }

    // تقرير المخزون (Angular /dashboard/reports/storage)
    if (key.contains('/reports/storage')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const StorageReportScreen()),
      );
      return;
    }

    // لوحة التشغيل الخارجي (Angular /processing/dashboard)
    if (key.contains('/processing/dashboard')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const ProcessingDashboardScreen()),
      );
      return;
    }

    // إذونات الصرف والاستلام (Angular /processing/orders)
    if (key.contains('/processing/orders')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const ProcessingOrdersScreen()),
      );
      return;
    }

    // إضافة مورد
    if (key.contains('/suppliers/add_supplier')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const AddSupplierScreen()),
      );
      return;
    }

    // قائمة الموردين
    if (key.contains('/suppliers/list_suppliers')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const SuppliersListScreen()),
      );
      return;
    }

    // حسابات العملاء (Angular /financial/customer-accounts)
    if (key.contains('/financial/customer-accounts')) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => const CustomerAccountsScreen(
            mode: CustomerAccountsMode.accounts,
          ),
        ),
      );
      return;
    }

    // العملاء الأفراد
    if (key.contains('/financial/individualsclients')) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => const CustomerAccountsScreen(
            mode: CustomerAccountsMode.individuals,
          ),
        ),
      );
      return;
    }

    // عملاء الشركات (Angular /shipping/companies)
    if (key.contains('/shipping/companies')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const CompaniesListScreen()),
      );
      return;
    }

    // حسابات الموردين
    if (key.contains('/financial/supplier-accounts')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const SupplierAccountsScreen()),
      );
      return;
    }

    // تقرير الأوردرات
    if (key.contains('/financial/report-order-new') &&
        !key.contains('details')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const OrdersReportScreen()),
      );
      return;
    }

    // سندات العملاء
    if (key.contains('/vouchers/clients')) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => const VouchersListScreen(voucherType: 'client'),
        ),
      );
      return;
    }

    // سندات الموردين
    if (key.contains('/vouchers/suppliers')) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => const VouchersListScreen(voucherType: 'supplier'),
        ),
      );
      return;
    }

    // فئات الموردين (Angular /suppliers/types)
    if (key.contains('/suppliers/types')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const SupplierTypesScreen()),
      );
      return;
    }

    // إضافة وصفة (Angular /manufacturing/addrecipe)
    if (key.contains('/manufacturing/addrecipe') ||
        key.contains('/manufacturing/editrecipe')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const AddRecipeScreen()),
      );
      return;
    }

    // وصفات التصنيع (Angular /manufacturing/recipes)
    if (key.contains('/manufacturing/recipes')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const RecipesListScreen()),
      );
      return;
    }

    // قائمة المواد BOM
    if (key.contains('/manufacturing/bom')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const BomListScreen()),
      );
      return;
    }

    // أصناف بدون وصفة
    if (key.contains('/manufacturing/items-without-recipes')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const ItemsWithoutRecipeScreen()),
      );
      return;
    }

    // تأكيد أمر التصنيع
    if (key.contains('/manufacturing/confirmation')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const ManufacturingConfirmationScreen()),
      );
      return;
    }

    // أوامر التصنيع
    if (key.contains('/manufacturing/orders')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const ManufacturingOrdersScreen()),
      );
      return;
    }

    // الإضافات الخاصة
    if (key.contains('/manufacturing/additions')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const ManufacturingAdditionsScreen()),
      );
      return;
    }

    // إضافة فاتورة مشتريات
    if (key.contains('/purchases/add_invoice')) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const AddPurchaseInvoiceScreen()),
      );
      return;
    }

    // قائمة المشتريات / تقرير المشتريات
    if (key.contains('/purchases/list_invoice') ||
        key.contains('/reports/procurement')) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => PurchasesListScreen(title: title),
        ),
      );
      return;
    }

    final module = ModuleCatalog.byRouteHint(routeHint, title);
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => module != null
            ? ApiListScreen(module: module)
            : PlaceholderScreen(
                title: title,
                routeHint: routeHint,
              ),
      ),
    );
  }
}
