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

/** دمج أسماء المخازن الثابتة مع صفوف API ‎/stocks‎ (للحصول على id عند وجودها). */
export function warehouseOptionsFromStocks(stockList: any[]): {
  name: string;
  keyEn: string;
  id?: number;
  asset_id?: number;
  asset_name?: string | null;
}[] {
  const byName = new Map<string, any>(stockList.map((s: any) => [s.name, s]));
  return WAREHOUSE_STOCK_ROWS.map(({ nameAr, keyEn }) => {
    const s = byName.get(nameAr);
    return {
      name: nameAr,
      keyEn,
      id: s?.id,
      asset_id: s?.asset_id,
      asset_name: s?.asset_name ?? null,
    };
  });
}
