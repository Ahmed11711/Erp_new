import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

export interface SupplierPurgePreview {
  supplier_count: number;
  purchase_count: number;
  processing_count: number;
}

@Injectable({
  providedIn: 'root'
})
export class SuppliersService {

  constructor(private http:HttpClient) { }


  addSupplier(obj:any){
    return this.http.post(`${environment.Url}/suppliers` ,obj);
  }

  getSupplier(id: number) {
    return this.http.get(`${environment.Url}/suppliers/${id}`);
  }

  updateSupplier(id: number, obj: Record<string, unknown>) {
    return this.http.put(`${environment.Url}/suppliers/${id}`, obj);
  }

  getSuppliers(itemsperpage:number,page:number = 1){
    return this.http.get(`${environment.Url}/suppliers?itemsPerPage=${itemsperpage}&page=${page}`);
  }

  searchSuppliers(itemsperpage:number,page:number = 1,search:any){
    return this.http.get(`${environment.Url}/suppliers/search?itemsPerPage=${itemsperpage}&page=${page}`,{params:search});
  }

  suppliersname(){
    return this.http.get(`${environment.Url}/suppliers/supplier_names`);
  }

  supplierDetails(id:any , items:number,page:number){
    return this.http.get(`${environment.Url}/suppliers/supplierDetails/${id}?itemsPerPage=${items}&page=${page}`);
  }

  supplierPay(id:number , obj:any){
    return this.http.post(`${environment.Url}/suppliers/supplierPay/${id}` ,obj);
  }

  deleteSupplier(id: number) {
    return this.http.delete(`${environment.Url}/suppliers/${id}`);
  }

  deleteSuppliers(ids: number[]) {
    return this.http.post(`${environment.Url}/suppliers/bulk-delete`, { ids });
  }

  purgePreview() {
    return this.http.get<SupplierPurgePreview>(`${environment.Url}/suppliers/purge-preview`);
  }

  purgeAll(includeProcessing = true) {
    return this.http.post(`${environment.Url}/suppliers/purge-all`, {
      confirm: true,
      include_processing: includeProcessing,
    });
  }

  // search(items:number,page:number,search:any){
  //   return this.http.get(`${environment.Url}/purchases/search?itemsPerPage=${items}&page=${page}`,{params:search});
  // }
}
