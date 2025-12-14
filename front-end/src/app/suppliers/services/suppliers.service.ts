import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class SuppliersService {

  constructor(private http:HttpClient) { }


  addSupplier(obj:any){
    return this.http.post(`${environment.Url}/suppliers` ,obj);
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

  // search(items:number,page:number,search:any){
  //   return this.http.get(`${environment.Url}/purchases/search?itemsPerPage=${items}&page=${page}`,{params:search});
  // }
}
