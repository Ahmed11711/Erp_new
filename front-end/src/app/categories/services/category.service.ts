import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
 providedIn: 'root'
})
export class CategoryService {

 constructor(private http: HttpClient) { }

 addCategory(data: any) {
  return this.http.post(`${environment.Url}/categories`, data);
 }

 /** قائمة الوصفات (BOM) لربط الصنف عند الإضافة أو التعديل */
 listRecipes() {
  return this.http.get<Array<{ id: number; recipe_name: string; description?: string | null }>>(`${environment.Url}/recipes`);
 }

 editCategory(id: any, formData: any) {
  return this.http.post(`${environment.Url}/editcategory/${id}`, formData);
 }

 deleteCategory(id: any) {
  return this.http.delete(`${environment.Url}/deletecategory/${id}`);
 }

 getCategories(items: number, page: number) {
  return this.http.get(`${environment.Url}/categories?itemsPerPage=${items}&page=${page}`);
 }

 getCategoryById(id: any) {
  return this.http.get(`${environment.Url}/category/${id}`);
 }

 searchCategories(items: number, page: number, search: any) {
  return this.http.get(`${environment.Url}/categories/search?itemsPerPage=${items}&page=${page}`, { params: search });
 }

 getCatName() {
  return this.http.get(`${environment.Url}/categories/catName`);
 }

 getCatBywarehouse(warehouse: any) {
  const params = new HttpParams().set('warehouse', String(warehouse));
  return this.http.get(`${environment.Url}/categories/categoryByWarehouse`, { params });
 }

 categoryDetails(warehouse: any, items: number, page: number, search: any) {
  return this.http.get(`${environment.Url}/categories/categoryDetailsByWherehouse?warehouse=${warehouse}&itemsPerPage=${items}&page=${page}`, { params: search });
 }

 monthlyInventoryDetails(warehouse: any, items: number, page: number, month: string, search: any) {
  return this.http.get(`${environment.Url}/categories/monthlyInventoryDetailsByWherehouse?warehouse=${warehouse}&month=${month}&itemsPerPage=${items}&page=${page}`, { params: search });
 }

 categories_details(id: any, items: number, page: number, search: any) {
  return this.http.get(`${environment.Url}/categories/categories_details/${id}?itemsPerPage=${items}&page=${page}`, { params: search })
 }

 warehouseDetails(items: number, page: number, search: any) {
  return this.http.get(`${environment.Url}/categories/warehousedetails?itemsPerPage=${items}&page=${page}`, { params: search })
 }

 warehouseInventoryReport(items: number, page: number, params: Record<string, string | number | undefined | null>) {
  const clean: Record<string, string> = { itemsPerPage: String(items), page: String(page) };
  Object.entries(params).forEach(([k, v]) => {
   if (v !== undefined && v !== null && v !== '') {
    clean[k] = String(v);
   }
  });
  return this.http.get(`${environment.Url}/reports/warehouse-inventory`, { params: clean });
 }

 warehousebalance() {
  return this.http.get(`${environment.Url}/categories/warehouse_balance`)
 }

 getAllCategory() {
  return this.http.get(`${environment.Url}/categories`)
 }

 allCategories() {
  return this.http.get(`${environment.Url}/allcategories`)
 }

 monthlyInventory(warehouse: string, month?: string) {
  let url = `${environment.Url}/categories/monthlyinventory?warehouse=${encodeURIComponent(warehouse)}`;
  if (month) {
   url += `&month=${encodeURIComponent(month)}`;
  }
  return this.http.get(`${url}`);
 }

 /** معاينة فروقات مطابقة المخزون مع الحسابات (بدون ترحيل). */
 previewInventoryGlSync() {
  return this.http.get(`${environment.Url}/categories/inventory-gl-sync-preview`);
 }

 /** ترحيل قيد يومية لمطابقة أرصدة المخزون مع تكلفة الأصناف. */
 postInventoryGlSync() {
  return this.http.post(`${environment.Url}/categories/inventory-gl-sync`, {});
 }

 changeCategoryQuantity(id, status, quantity) {
  return this.http.get(`${environment.Url}/categoryquantity?id=${id}&status=${status}&quantity=${quantity}`);
 }

 categoriesSellReports(items: number, page: number, search: any) {
  return this.http.get(`${environment.Url}/reports/categoriesSellReports?itemsPerPage=${items}&page=${page}`, { params: search })
 }

 // ✅ الدالة الجديدة لتحديث الرصيد مباشرة
 updateQuantity(id: number, quantity: number) {
  return this.http.patch(`${environment.Url}/categories/${id}/quantity`, { quantity });
 }

 /** تعيين متوسط تكلفة الوحدة دون تغيير الكمية (مع تأثير محاسبي على حساب المخزون المرتبط بالصنف). */
 updateAverageUnitCost(id: number, averageUnitCost: number) {
  return this.http.patch(`${environment.Url}/categories/${id}/average-unit-cost`, {
   average_unit_cost: averageUnitCost,
  });
 }

 /** ترقية صنف من تحت التشغيل (WIP) إلى منتج تام مع حركة مخزون وقيد محاسبي. */
 promoteToFinished(id: number) {
  return this.http.post(`${environment.Url}/categories/${id}/promote-to-finished`, {});
 }

 getCategoryLinks(id: number) {
  return this.http.get(`${environment.Url}/categories/${id}/links`);
 }

 previewCategoryMerge(sourceId: number, targetId: number) {
  return this.http.post(`${environment.Url}/categories/merge-preview`, {
   source_id: sourceId,
   target_id: targetId,
  });
 }

 mergeCategory(sourceId: number, targetId: number) {
  return this.http.post(`${environment.Url}/categories/merge`, {
   source_id: sourceId,
   target_id: targetId,
  });
 }

 /** مجموعات الأصناف المكررة (نفس الاسم داخل نفس المخزن). */
 duplicateCategoryGroups(stockId?: number) {
  let url = `${environment.Url}/categories/duplicate-groups`;
  if (stockId) {
   url += `?stock_id=${stockId}`;
  }
  return this.http.get(url);
 }

 /** دمج أكثر من صنف مصدر في صنف واحد محتفظ به. */
 mergeCategoriesBulk(targetId: number, sourceIds: number[]) {
  return this.http.post(`${environment.Url}/categories/merge-bulk`, {
   target_id: targetId,
   source_ids: sourceIds,
  });
 }

 /** معاينة حذف قسري لأصناف (مع عدّ الارتباطات) — Admin فقط. */
 previewForceDeleteCategories(payload: { category_ids?: number[]; warehouse?: string }) {
  return this.http.post(`${environment.Url}/categories/force-delete-preview`, payload);
 }

 /** حذف قسري لأصناف مع كل ارتباطاتها — Admin فقط. */
 forceDeleteCategories(payload: {
  category_ids?: number[];
  warehouse?: string;
  confirm_phrase: string;
 }) {
  return this.http.post(`${environment.Url}/categories/force-delete-bulk`, payload);
 }

}
