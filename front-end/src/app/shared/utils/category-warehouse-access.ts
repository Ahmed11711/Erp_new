import { RbacService } from 'src/app/core/rbac/rbac.service';

const FINISHED = 'مخزن منتج تام';
const RAW = 'مخزن مواد خام';
const WIP = 'مخزن منتج تحت التشغيل';

/**
 * هل يُسمح للمستخدم باختيار هذا المخزن في نماذج الأصناف؟
 * يدمج RBAC مع قواعد الأقسام القديمة في Angular.
 */
export function canSelectCategoryWarehouse(
  warehouseName: string,
  department: string | false | null | undefined,
  rbac: RbacService
): boolean {
  const dept = String(department ?? '').trim();
  const finished = warehouseName === FINISHED;
  const rawWip = warehouseName === RAW || warehouseName === WIP;

  if (rbac.can('categories.manage')) {
    if (finished || rawWip) {
      return true;
    }
    return dept === 'Admin' || rbac.can('system.rbac');
  }

  // نمط إدخال البيانات: منتجات تامة للطلبات
  if (rbac.can('categories.view') && rbac.can('orders.create') && finished) {
    return true;
  }

  // مواد خام / تحت التشغيل لمن يرى المخزون أو التصنيع
  if (rbac.can('categories.view') && rbac.canAny(['inventory.view', 'manufacturing.view']) && rawWip) {
    return true;
  }

  if (
    (dept === 'Admin' || dept === 'Account Management' || dept === 'Logistics Specialist') &&
    rawWip
  ) {
    return true;
  }

  if ((dept === 'Admin' || dept === 'Data Entry') && finished) {
    return true;
  }

  return false;
}

const CATEGORY_ROW_ACTION_WAREHOUSES = [RAW, WIP, FINISHED];

/**
 * هل يُعرض زر خيارات الصف (تعديل / حذف) في قائمة الأصناف؟
 * يدمج RBAC (categories.manage) مع قواعد الأقسام القديمة في list-categories.
 */
export function canShowCategoryRowActions(
  warehouseName: string,
  department: string | false | null | undefined,
  rbac: RbacService
): boolean {
  if (!CATEGORY_ROW_ACTION_WAREHOUSES.includes(warehouseName)) {
    return false;
  }

  if (rbac.can('categories.manage') || rbac.can('system.rbac')) {
    return true;
  }

  const dept = String(department ?? '').trim();
  const finished = warehouseName === FINISHED;

  if (dept === 'Admin' || dept === 'Financial Accounts') {
    return true;
  }
  if (dept === 'Data Entry' && finished) {
    return true;
  }
  if ((dept === 'Account Management' || dept === 'Logistics Specialist') && !finished) {
    return true;
  }

  return false;
}
