import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

/** يطلب من TokenInterceptor عدم إظهار غطاء التحميل العام (لوحة Shopify تستطلع دورياً). */
const SKIP_GLOBAL_LOADING = new HttpHeaders({ 'X-Skip-Global-Loading': '1' });

@Injectable({ providedIn: 'root' })
export class ShopifyIntegrationService {
  private readonly base = environment.Url;

  constructor(private http: HttpClient) {}

  getOrders(page = 1, perPage = 25): Observable<any> {
    const params = new HttpParams()
      .set('page', String(page))
      .set('per_page', String(perPage));
    return this.http.get(`${this.base}/shopify/integration/orders`, {
      params,
      headers: SKIP_GLOBAL_LOADING,
    });
  }

  getProducts(page = 1, perPage = 25): Observable<any> {
    const params = new HttpParams()
      .set('page', String(page))
      .set('per_page', String(perPage));
    return this.http.get(`${this.base}/shopify/integration/products`, {
      params,
      headers: SKIP_GLOBAL_LOADING,
    });
  }

  updateProduct(id: number, body: Partial<{ name: string; sku: string; price: number; quantity: number }>): Observable<any> {
    return this.http.patch(`${this.base}/shopify/integration/products/${id}`, body, {
      headers: SKIP_GLOBAL_LOADING,
    });
  }

  /**
   * جلب متغيرات المنتجات من Shopify إلى النظام (جداول shopify_products / الربط).
   * بدون body = جلب الكتالوج كاملاً (قد يكون بطيئاً). أو { days: N } أو date_from + date_to.
   */
  syncProductMappings(body?: { days?: number; date_from?: string; date_to?: string }): Observable<any> {
    return this.http.post(`${this.base}/shopify/sync-product-mappings`, body ?? {}, {
      headers: SKIP_GLOBAL_LOADING,
    });
  }

  getFailedJobs(page = 1, perPage = 20): Observable<any> {
    const params = new HttpParams()
      .set('page', String(page))
      .set('per_page', String(perPage));
    return this.http.get(`${this.base}/shopify/integration/failed-jobs`, {
      params,
      headers: SKIP_GLOBAL_LOADING,
    });
  }
}
