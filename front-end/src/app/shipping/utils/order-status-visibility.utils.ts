/** يطابق backend/config/order_status_visibility.php */

export interface OrderStatusVisibilityDef {
  permission: string;
  labels: readonly string[];
  filterValue: string;
  filterLabel: string;
}

export const ORDER_STATUS_VISIBILITY: Record<string, OrderStatusVisibilityDef> = {
  new: {
    permission: 'orders.view_status.new',
    labels: ['طلب جديد', 'جديد'],
    filterValue: 'طلب جديد',
    filterLabel: 'جديد',
  },
  confirmed: {
    permission: 'orders.view_status.confirmed',
    labels: ['طلب مؤكد'],
    filterValue: 'طلب مؤكد',
    filterLabel: 'طلب مؤكد',
  },
  partial_ship: {
    permission: 'orders.view_status.partial_ship',
    labels: ['شحن جزئي', 'تسليم جزئي'],
    filterValue: 'شحن جزئي',
    filterLabel: 'شحن جزئي',
  },
  partial_deliver: {
    permission: 'orders.view_status.partial_deliver',
    labels: ['تسليم جزئي'],
    filterValue: 'تسليم جزئي',
    filterLabel: 'تسليم جزئي',
  },
  shipped: {
    permission: 'orders.view_status.shipped',
    labels: ['تم شحن'],
    filterValue: 'تم شحن',
    filterLabel: 'تم شحن',
  },
  delivered: {
    permission: 'orders.view_status.delivered',
    labels: ['تم التسليم'],
    filterValue: 'تم التسليم',
    filterLabel: 'تم التسليم',
  },
  received: {
    permission: 'orders.view_status.received',
    labels: ['تم الاستلام'],
    filterValue: 'تم الاستلام',
    filterLabel: 'تم الاستلام',
  },
  collected: {
    permission: 'orders.view_status.collected',
    labels: ['تم التحصيل'],
    filterValue: 'تم التحصيل',
    filterLabel: 'تم التحصيل',
  },
  postponed: {
    permission: 'orders.view_status.postponed',
    labels: ['مؤجل'],
    filterValue: 'مؤجل',
    filterLabel: 'مؤجل',
  },
  archived: {
    permission: 'orders.view_status.archived',
    labels: ['أرشيف'],
    filterValue: 'أرشيف',
    filterLabel: 'أرشيف',
  },
  maintained: {
    permission: 'orders.view_status.maintained',
    labels: ['تم الصيانة'],
    filterValue: 'تم الصيانة',
    filterLabel: 'تم الصيانة',
  },
  refused: {
    permission: 'orders.view_status.refused',
    labels: ['رفض استلام'],
    filterValue: 'رفض استلام',
    filterLabel: 'رفض استلام',
  },
  cancelled: {
    permission: 'orders.view_status.cancelled',
    labels: ['ملغي'],
    filterValue: 'ملغي',
    filterLabel: 'ملغي',
  },
};

export interface OrderStatusFilterOption {
  value: string;
  label: string;
}

type PermissionCheck = (slug: string) => boolean;

/** الحالات المرئية = ما يملك المستخدم صلاحية عرضه فقط (متطابق مع الخلفية). */
function visibleStatusKeys(can: PermissionCheck): Set<string> {
  const visible = new Set<string>();

  for (const [key, def] of Object.entries(ORDER_STATUS_VISIBILITY)) {
    if (can(def.permission)) {
      visible.add(key);
    }
  }

  return visible;
}

export function canViewOrderStatus(
  _department: string,
  orderStatus: string,
  can: PermissionCheck,
): boolean {
  const normalized = String(orderStatus ?? '').trim();
  const keys = visibleStatusKeys(can);

  for (const [key, def] of Object.entries(ORDER_STATUS_VISIBILITY)) {
    if (def.labels.includes(normalized) && keys.has(key)) {
      return true;
    }
  }

  return false;
}

export function orderStatusFilterOptions(
  _department: string,
  can: PermissionCheck,
): OrderStatusFilterOption[] {
  const keys = visibleStatusKeys(can);
  return Object.entries(ORDER_STATUS_VISIBILITY)
    .filter(([key]) => keys.has(key))
    .map(([, def]) => ({ value: def.filterValue, label: def.filterLabel }));
}
