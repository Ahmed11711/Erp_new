import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class TransactionService {

  constructor(private http: HttpClient) { }

  searchCustomer(items: number, page: number, search: Record<string, string> | null | undefined) {
    let params = new HttpParams()
      .set('itemsPerPage', String(items))
      .set('page', String(page));
    if (search) {
      for (const k of Object.keys(search)) {
        const v = search[k];
        if (v !== undefined && v !== null && String(v).trim() !== '') {
          params = params.set(k, String(v).trim());
        }
      }
    }
    return this.http.get(`${environment.Url}/transactions/by-customer-order/search`, { params });
  }

  getCustomerDetails(items: number, page: number, search: Record<string, string> | null | undefined) {
    let params = new HttpParams()
      .set('itemsPerPage', String(items))
      .set('page', String(page));
    if (search) {
      for (const k of Object.keys(search)) {
        const v = search[k];
        if (v !== undefined && v !== null && String(v).trim() !== '') {
          params = params.set(k, String(v).trim());
        }
      }
    }
    return this.http.get(`${environment.Url}/transactions/by-customer-order/detailed`, { params });
  }

  /**
   * حسابات الموردين — باراميترات عبر HttpParams (يتطلب مصادقة + صلاحية قسم).
   */
  searchSupplier(items: number, page: number, search: Record<string, string> | undefined | null): Observable<any> {
    let params = new HttpParams()
      .set('itemsPerPage', String(items))
      .set('page', String(page));
    if (search) {
      for (const k of Object.keys(search)) {
        const v = search[k];
        if (v !== undefined && v !== null && String(v).trim() !== '') {
          params = params.set(k, String(v).trim());
        }
      }
    }
    return this.http.get(`${environment.Url}/transactions/by-supplier-order/search`, { params });
  }
}
