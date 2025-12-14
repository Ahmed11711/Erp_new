import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class TransactionService {

  constructor(private http:HttpClient) { }

  searchCustomer(items:number,page:number,search:any){
    return this.http.get(`${environment.Url}/transactions/by-customer-order/search?itemsPerPage=${items}&page=${page}`,{params:search});
  }

  getCustomerDetails(items:number,page:number,search:any){
    return this.http.get(`${environment.Url}/transactions/by-customer-order/detailed?itemsPerPage=${items}&page=${page}`,{params:search});
  }

  searchSupplier(items:number,page:number,search:any){
    return this.http.get(`${environment.Url}/transactions/by-supplier-order/search?itemsPerPage=${items}&page=${page}`,{params:search});
  }
}
