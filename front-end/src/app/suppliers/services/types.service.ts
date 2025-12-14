import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class TypesService {
 
  constructor(private http:HttpClient) { }


  addType(supplier_type:string){
    return this.http.post(`${environment.Url}/suppliers/StoreSupplierType` ,{supplier_type});
  }

  getTypes(){
    return this.http.get(`${environment.Url}/suppliers/getAllSupplierTypes`);
  }

  

}
