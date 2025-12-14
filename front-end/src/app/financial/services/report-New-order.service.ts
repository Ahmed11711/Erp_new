import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ReportNewOrderService {

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

getAll(orderId?: string) {
  let url = `${environment.Url}/report/order`;
  
    
  return this.http.get(url);
}

getAllByKey(orderId?: string, assetId?: string) {
  let url = `${environment.Url}/report/getByOrderId`;
  const params: any = {};

  if (orderId) params.order_id = orderId;
  if (assetId) params.asset_id = assetId;
 
  
  return this.http.get(url, { params });
}

}

