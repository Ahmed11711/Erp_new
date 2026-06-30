/**
 * يطابق أسماء المخازن في النظام ومفاتيح CategoriesController::warehouse_balance.
 */
export const WAREHOUSE_STOCK_ROWS: { nameAr: string; keyEn: string }[] = [
  { nameAr: 'مخزن مواد خام', keyEn: 'Raw' },
  { nameAr: 'مخزن منتج تحت التشغيل', keyEn: 'In_Process' },
  { nameAr: 'مخزن منتج تام', keyEn: 'Finished' },
  { nameAr: 'مخزن صيانة', keyEn: 'Maintenance' },
  { nameAr: 'مخزن تالف', keyEn: 'Defective' },
];

const STANDARD_WAREHOUSE_NAMES = new Set(WAREHOUSE_STOCK_ROWS.map((row) => row.nameAr));

export type WarehouseListRow = {
  name: string;
  keyEn: string | null;
  balance: number;
  quantityTotal: number;
  categorySum: number;
  stockBalance: number | null;
  id?: number;
  asset_id?: number;
  asset_name?: string | null;
  isCustom?: boolean;
};

function mapStockRow(
  s: any,
  balance: number,
  quantityTotal: number
): WarehouseListRow {
  const stockBal =
    s?.balance !== undefined && s?.balance !== null ? Number(s.balance) : null;
  return {
    name: String(s?.name ?? ''),
    keyEn: null,
    balance,
    quantityTotal,
    categorySum: balance,
    stockBalance: stockBal,
    id: s?.id,
    asset_id: s?.asset_id,
    asset_name: s?.asset_name ?? null,
    isCustom: true,
  };
}

/** صفوف قائمة المخازن: المعيارية + أي مخزن إضافي من ‎/stocks‎ */
export function buildWarehouseListRows(
  stockList: any[],
  balances: Record<string, unknown> | null | undefined
): WarehouseListRow[] {
  const byName = new Map<string, any>(stockList.map((s: any) => [s.name, s]));
  const qtyTotals =
    balances != null && typeof balances === 'object' && balances['quantity_totals']
      ? (balances['quantity_totals'] as Record<string, number>)
      : {};

  const standardRows: WarehouseListRow[] = WAREHOUSE_STOCK_ROWS.map(({ nameAr, keyEn }) => {
    const s = byName.get(nameAr);
    const rawVal = balances != null ? (balances as any)[keyEn] : undefined;
    const categorySum =
      rawVal !== undefined && rawVal !== null ? Number(rawVal) : 0;
    const quantityTotal =
      qtyTotals[keyEn] !== undefined && qtyTotals[keyEn] !== null
        ? Number(qtyTotals[keyEn])
        : 0;
    const stockBal =
      s != null && s.balance !== undefined && s.balance !== null
        ? Number(s.balance)
        : null;
    return {
      name: nameAr,
      keyEn,
      balance: categorySum,
      quantityTotal,
      categorySum,
      stockBalance: stockBal,
      id: s?.id,
      asset_id: s?.asset_id,
      asset_name: s?.asset_name ?? null,
      isCustom: false,
    };
  });

  const extraRows = stockList
    .filter((s) => s?.name && !STANDARD_WAREHOUSE_NAMES.has(String(s.name)))
    .map((s) => {
      const stockBalance =
        s.balance !== undefined && s.balance !== null ? Number(s.balance) : 0;
      return mapStockRow(s, stockBalance, 0);
    })
    .sort((a, b) => a.name.localeCompare(b.name, 'ar'));

  return [...standardRows, ...extraRows];
}

/** دمج أسماء المخازن الثابتة مع صفوف API ‎/stocks‎ (للحصول على id عند وجودها). */
export function warehouseOptionsFromStocks(stockList: any[]): {
  name: string;
  keyEn: string;
  id?: number;
  asset_id?: number;
  asset_name?: string | null;
}[] {
  const byName = new Map<string, any>(stockList.map((s: any) => [s.name, s]));
  const standard = WAREHOUSE_STOCK_ROWS.map(({ nameAr, keyEn }) => {
    const s = byName.get(nameAr);
    return {
      name: nameAr,
      keyEn,
      id: s?.id,
      asset_id: s?.asset_id,
      asset_name: s?.asset_name ?? null,
    };
  });

  const extras = stockList
    .filter((s) => s?.name && !STANDARD_WAREHOUSE_NAMES.has(String(s.name)))
    .map((s) => ({
      name: String(s.name),
      keyEn: String(s.name),
      id: s?.id,
      asset_id: s?.asset_id,
      asset_name: s?.asset_name ?? null,
    }))
    .sort((a, b) => a.name.localeCompare(b.name, 'ar'));

  return [...standard, ...extras];
}
