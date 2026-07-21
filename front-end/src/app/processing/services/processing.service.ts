import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({ providedIn: 'root' })
export class ProcessingService {
  private base = `${environment.Url}/processing`;

  constructor(private http: HttpClient) {}

  meta() {
    return this.http.get<any>(`${this.base}/meta`);
  }

  listVendors() {
    return this.http.get<any[]>(`${environment.Url}/suppliers/processing-vendors`);
  }

  kpis() {
    return this.http.get<any>(`${this.base}/dashboard/kpis`);
  }

  listOrders(params: any = {}) {
    return this.http.get<any>(`${this.base}/orders`, { params });
  }

  getOrder(id: number) {
    return this.http.get<any>(`${this.base}/orders/${id}`);
  }

  createOrder(body: any) {
    return this.http.post<any>(`${this.base}/orders`, body);
  }

  updateOrder(id: number, body: any) {
    return this.http.put<any>(`${this.base}/orders/${id}`, body);
  }

  approveOrder(id: number) {
    return this.http.post<any>(`${this.base}/orders/${id}/approve`, {});
  }

  deleteOrder(id: number, reason?: string) {
    return this.http.delete<any>(`${this.base}/orders/${id}`, {
      body: reason ? { reason } : {},
    });
  }

  listDispatches(params: any = {}) {
    return this.http.get<any>(`${this.base}/dispatches`, { params });
  }

  createDispatch(body: any) {
    return this.http.post<any>(`${this.base}/dispatches`, body);
  }

  submitDispatchVoucher(body: any) {
    return this.http.post<any>(`${this.base}/dispatches/voucher`, body);
  }

  postDispatch(id: number) {
    return this.http.post<any>(`${this.base}/dispatches/${id}/post`, {});
  }

  listReceipts(params: any = {}) {
    return this.http.get<any>(`${this.base}/receipts`, { params });
  }

  createReceipt(body: any) {
    return this.http.post<any>(`${this.base}/receipts`, body);
  }

  postReceipt(id: number) {
    return this.http.post<any>(`${this.base}/receipts/${id}/post`, {});
  }

  listInvoices(params: any = {}) {
    return this.http.get<any>(`${this.base}/invoices`, { params });
  }

  createInvoice(body: any) {
    return this.http.post<any>(`${this.base}/invoices`, body);
  }

  postInvoice(id: number) {
    return this.http.post<any>(`${this.base}/invoices/${id}/post`, {});
  }

  materialsAtVendor() {
    return this.http.get<any>(`${this.base}/reports/materials-at-vendor`);
  }

  vendorBalances() {
    return this.http.get<any>(`${this.base}/reports/vendor-balances`);
  }

  aging() {
    return this.http.get<any>(`${this.base}/reports/aging`);
  }
}
