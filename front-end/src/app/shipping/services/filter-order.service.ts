import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class FilterOrderService {
  private triggerSearch = new BehaviorSubject('');
  value = this.triggerSearch.asObservable();

  triggerSearchFn(){
    this.triggerSearch.next('trigger');
  }

  constructor( private http:HttpClient) { }

  scrollOrder!:string;
  page = 0;
  pageSize = 15;

  private_order!:string;
  customer_type!:string;
  order_type!:string;
  order_status!:string;
  collectType!:string;
  shipping_company_id!:string;
  need_by_date!:string;
  status_date!:string;
  order_date!:string;
  delivery_date!:string;
  need_by_date_from!:string;
  need_by_date_to!:string;
  status_date_from!:string;
  status_date_to!:string;
  order_date_from!:string;
  order_date_to!:string;
  delivery_date_from!:string;
  delivery_date_to!:string;
  vip!:string;
  shortage!:string;
  paid!:string;
  prepaidAmount!:string;
  governorate!:string;
  city!:string;
  customer_name!:string;
  customer_phone!:string;
  order_number!:string;
  shippment_number!:string;
  order_source_id!:string;
  shipping_method_id!:string;
  shipping_line_id!:string;
  reviewed!:string;
  shopify!:string;
  company_id!:number;
  category_id!:number | null;
  confimedOrderNotifi!:boolean;

  /** Clears list-order search state so the next visit starts from the default list. */
  resetFilters(): void {
    this.private_order = '';
    this.customer_type = '';
    this.order_type = '';
    this.order_status = '';
    this.collectType = '';
    this.shipping_company_id = '';
    this.need_by_date = '';
    this.status_date = '';
    this.order_date = '';
    this.delivery_date = '';
    this.need_by_date_from = '';
    this.need_by_date_to = '';
    this.status_date_from = '';
    this.status_date_to = '';
    this.order_date_from = '';
    this.order_date_to = '';
    this.delivery_date_from = '';
    this.delivery_date_to = '';
    this.vip = '';
    this.shortage = '';
    this.paid = '';
    this.prepaidAmount = '';
    this.governorate = '';
    this.city = '';
    this.customer_name = '';
    this.customer_phone = '';
    this.order_number = '';
    this.shippment_number = '';
    this.order_source_id = '';
    this.shipping_method_id = '';
    this.shipping_line_id = '';
    this.reviewed = '';
    this.shopify = '';
    this.company_id = undefined as unknown as number;
    this.category_id = null;
    this.confimedOrderNotifi = false;
    this.page = 0;
    this.pageSize = 15;
  }

  getOrders(items:number,page:number){
    return this.http.get(`${environment.Url}/orders?itemsPerPage=${items}&page=${page}`);
  }

  filter(items:number,page:number): Observable<any> {
    let params = new HttpParams()
      .set('itemsPerPage', String(items))
      .set('page', String(page));

    const set = (key: string, value: string | number | boolean | null | undefined) => {
      if (value !== null && value !== undefined && value !== '') {
        params = params.set(key, String(value));
      }
    };

    set('company_id', this.company_id);
    set('category_id', this.category_id);
    set('reviewed', this.reviewed);
    set('shopify', this.shopify);
    set('customer_type', this.customer_type);
    set('order_type', this.order_type);
    set('order_status', this.order_status);
    set('order_date', this.order_date);
    set('delivery_date', this.delivery_date);
    set('order_date_from', this.order_date_from);
    set('order_date_to', this.order_date_to);
    set('delivery_date_from', this.delivery_date_from);
    set('delivery_date_to', this.delivery_date_to);
    set('need_by_date_from', this.need_by_date_from);
    set('need_by_date_to', this.need_by_date_to);
    set('status_date_from', this.status_date_from);
    set('status_date_to', this.status_date_to);
    set('collectType', this.collectType);
    set('private_order', this.private_order);
    set('shipping_company_id', this.shipping_company_id);
    set('need_by_date', this.need_by_date);
    set('status_date', this.status_date);
    set('vip', this.vip);
    set('shortage', this.shortage);
    set('governorate', this.governorate);
    set('city', this.city);
    set('customer_name', this.customer_name);
    set('customer_phone', this.customer_phone);
    set('order_number', this.order_number);
    set('shippment_number', this.shippment_number);
    set('order_source_id', this.order_source_id);
    set('shipping_method_id', this.shipping_method_id);
    set('shipping_line_id', this.shipping_line_id);

    if (this.paid === '1') {
      params = params.set('paid', this.paid);
    }
    if (this.prepaidAmount === '1') {
      params = params.set('prepaidAmount', this.prepaidAmount);
    }
    if (this.confimedOrderNotifi) {
      params = params.set('confimedOrderNotifi', '1');
    }

    return this.http.get(`${environment.Url}/orders/search`, { params });
  }
}
