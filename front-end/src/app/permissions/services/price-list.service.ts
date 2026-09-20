import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

export interface PriceListItem {
  id: number;
  price_list_id?: number;
  code: string;
  product_name: string;
  price: number;
  currency?: string;
  photo1?: string | null;
  photo2?: string | null;
  sort_order?: number;
}

export interface PriceListSummary {
  id: number;
  name: string;
  sort_order?: number;
  items_count: number;
  created_at?: string;
  updated_at?: string;
}

export interface PriceListDetail {
  id: number;
  name: string;
  title?: string;
  items: PriceListItem[];
}

@Injectable({
  providedIn: 'root',
})
export class PriceListService {
  private readonly base = `${environment.Url}/price-list`;

  constructor(private http: HttpClient) {}

  getLists(): Observable<{ lists: PriceListSummary[] }> {
    return this.http.get<{ lists: PriceListSummary[] }>(this.base);
  }

  createList(name: string) {
    return this.http.post<{ message: string; list: PriceListSummary }>(this.base, { name });
  }

  getList(listId: number | string): Observable<PriceListDetail> {
    return this.http.get<PriceListDetail>(`${this.base}/${listId}`);
  }

  renameList(listId: number | string, name: string) {
    return this.http.post<{ message: string; name: string; title: string }>(`${this.base}/${listId}`, { name });
  }

  deleteList(listId: number | string) {
    return this.http.delete(`${this.base}/${listId}`);
  }

  createItem(listId: number | string, formData: FormData) {
    return this.http.post(`${this.base}/${listId}/items`, formData);
  }

  updateItem(listId: number | string, itemId: number | string, formData: FormData) {
    return this.http.post(`${this.base}/${listId}/items/${itemId}`, formData);
  }

  deleteItem(listId: number | string, itemId: number | string) {
    return this.http.delete(`${this.base}/${listId}/items/${itemId}`);
  }

  searchShopifyProducts(q = '', page = 1, perPage = 20) {
    return this.http.get<any>(`${this.base}/shopify-products`, {
      params: {
        q,
        page: String(page),
        per_page: String(perPage),
      },
    });
  }

  searchShopifyImages(q = '', page = 1, perPage = 48) {
    return this.http.get<any>(`${this.base}/shopify-images`, {
      params: {
        q,
        page: String(page),
        per_page: String(perPage),
      },
    });
  }

  importFromShopify(listId: number | string, shopifyProductId: number, currency = 'EGP') {
    return this.http.post(`${this.base}/${listId}/items/from-shopify`, {
      shopify_product_id: shopifyProductId,
      currency,
    });
  }
}
