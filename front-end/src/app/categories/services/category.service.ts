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
 updateCategoryCode(id: number, category_code: string) {
  return this.http.patch(`${environment.Url}/categories/${id}/update-code`, { category_code });
 }


 searchCategories(items: number, page: number, search: any) {
  return this.http.get(`${environment.Url}/categories/search?itemsPerPage=${items}&page=${page}`, { params: search });
 }

 getCatName() {
  return this.http.get(`${environment.Url}/categories/catName`);
 }

 getCatBywarehouse(warehouse: any) {
  return this.http.get(`${environment.Url}/categories/categoryByWarehouse?warehouse=${warehouse}`);
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

 warehousebalance() {
  return this.http.get(`${environment.Url}/categories/warehouse_balance`)
 }

 getAllCategory() {
  return this.http.get(`${environment.Url}/categories`)
 }

 allCategories() {
  return this.http.get(`${environment.Url}/allcategories`)
 }

 monthlyInventory(warehouse) {
  return this.http.get(`${environment.Url}/categories/monthlyinventory?warehouse=${warehouse}`)
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

}
