import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({ providedIn: 'root' })
export class ShopifyIntegrationService {
  private readonly base = environment.Url;

  constructor(private http: HttpClient) {}

  getOrders(page = 1, perPage = 25): Observable<any> {
    const params = new HttpParams()
      .set('page', String(page))
      .set('per_page', String(perPage));
    return this.http.get(`${this.base}/shopify/integration/orders`, { params });
  }

  getProducts(page = 1, perPage = 25): Observable<any> {
    const params = new HttpParams()
      .set('page', String(page))
      .set('per_page', String(perPage));
    return this.http.get(`${this.base}/shopify/integration/products`, { params });
  }

  updateProduct(id: number, body: Partial<{ name: string; sku: string; price: number; quantity: number }>): Observable<any> {
    return this.http.patch(`${this.base}/shopify/integration/products/${id}`, body);
  }

  getFailedJobs(page = 1, perPage = 20): Observable<any> {
    const params = new HttpParams()
      .set('page', String(page))
      .set('per_page', String(perPage));
    return this.http.get(`${this.base}/shopify/integration/failed-jobs`, { params });
  }
}
