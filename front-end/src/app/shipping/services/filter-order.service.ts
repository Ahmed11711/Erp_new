import { HttpClient } from '@angular/common/http';
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
  company_id!:number;
  category_id!:number | null;
  confimedOrderNotifi!:boolean;

  getOrders(items:number,page:number){
    return this.http.get(`${environment.Url}/orders?itemsPerPage=${items}&page=${page}`);
  }

  filter(items:number,page:number): Observable<any> {

    // let url = `${environment.Url}/orders/search?`;
    let url = `${environment.Url}/orders/search?itemsPerPage=${items}&page=${page}&`;


    if (this.company_id) {
      url += `company_id=${this.company_id}&`
    }

    if (this.category_id) {
      url += `category_id=${this.category_id}&`
    }

    if (this.reviewed) {
      url += `reviewed=${this.reviewed}&`
    }

    if (this.customer_type) {
      url += `customer_type=${this.customer_type}&`
    }

    if (this.order_type) {
      url += `order_type=${this.order_type}&`
    }

    if (this.order_status) {
      url += `order_status=${this.order_status}&`
    }

    if (this.order_date) {
      url += `order_date=${this.order_date}&`
    }

    if (this.delivery_date) {
      url += `delivery_date=${this.delivery_date}&`
    }

    if (this.collectType) {
      url += `collectType=${this.collectType}&`
    }

    if (this.private_order) {
      url += `private_order=${this.private_order}&`
    }

    if (this.shipping_company_id) {
      url += `shipping_company_id=${this.shipping_company_id}&`
    }

    if (this.need_by_date) {
      url += `need_by_date=${this.need_by_date}&`
    }

    if (this.status_date) {
      url += `status_date=${this.status_date}&`
    }

    if (this.vip) {
      url += `vip=${this.vip}&`
    }

    if (this.shortage) {
      url += `shortage=${this.shortage}&`
    }

    if (this.paid && this.paid == '1') {
      url += `paid=${this.paid}&`
    }

    if (this.prepaidAmount && this.prepaidAmount == '1') {
      url += `prepaidAmount=${this.prepaidAmount}&`
    }

    if (this.governorate) {
      url += `governorate=${this.governorate}&`
    }

    if (this.city) {
      url += `city=${this.city}&`
    }

    if (this.customer_name) {
      url += `customer_name=${this.customer_name}&`
    }

    if (this.customer_phone) {
      url += `customer_phone=${this.customer_phone}&`
    }

    if (this.order_number) {
      url += `order_number=${this.order_number}&`
    }

    if (this.shippment_number) {
      url += `shippment_number=${this.shippment_number}&`
    }

    if (this.order_source_id) {
      url += `order_source_id=${this.order_source_id}&`
    }

    if (this.shipping_method_id) {
      url += `shipping_method_id=${this.shipping_method_id}&`
    }

    if (this.shipping_line_id) {
      url += `shipping_line_id=${this.shipping_line_id}&`
    }

    if (this.confimedOrderNotifi) {
      url += `confimedOrderNotifi=${this.confimedOrderNotifi}&`
    }

    return this.http.get(url)
  }
}
