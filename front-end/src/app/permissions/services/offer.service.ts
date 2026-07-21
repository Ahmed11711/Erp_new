import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class OfferService {

  constructor( private http:HttpClient) { }

  getOffers(items: number, page: number, search?: {
    quote?: string;
    company_name?: string;
    customer_company_id?: number | string;
  }) {
    const params: any = {
      itemsPerPage: items,
      page,
    };
    if (search?.quote?.trim()) {
      params.quote = search.quote.trim();
    }
    if (search?.company_name?.trim()) {
      params.company_name = search.company_name.trim();
    }
    if (search?.customer_company_id) {
      params.customer_company_id = String(search.customer_company_id);
    }
    return this.http.get(`${environment.Url}/offer`, { params });
  }

  getOfferById(id:any){
    return this.http.get(`${environment.Url}/offer/${id}`);
  }

  addOffer(formData:any){
    return this.http.post(`${environment.Url}/offer` , formData);
  }

  linkClient(offerId: number | string, customerCompanyId: number){
    return this.http.post(`${environment.Url}/offer/${offerId}/link-client`, {
      customer_company_id: customerCompanyId,
    });
  }

  createAndLinkClient(offerId: number | string, payload: {
    name: string;
    phone1: string;
    phone2?: string;
    governorate: string;
    city?: string;
    address: string;
    tel?: string;
  }){
    return this.http.post(`${environment.Url}/offer/${offerId}/create-and-link-client`, payload);
  }

  syncDebtGl(offerId: number | string){
    return this.http.post(`${environment.Url}/offer/${offerId}/sync-debt-gl`, {});
  }

  getProductGaps(offerId: number | string) {
    return this.http.get(`${environment.Url}/offer/${offerId}/product-gaps`);
  }

  createMissingCategories(offerId: number | string, offerLineIds?: number[]) {
    const body = offerLineIds?.length ? { offer_line_ids: offerLineIds } : {};
    return this.http.post(`${environment.Url}/offer/${offerId}/create-missing-categories`, body);
  }

  linkMatchedCategory(offerId: number | string, offerLineId: number, categoryId: number) {
    return this.http.post(`${environment.Url}/offer/${offerId}/link-matched-category`, {
      offer_line_id: offerLineId,
      category_id: categoryId,
    });
  }

  clearMatchedCategory(offerId: number | string, offerLineId: number) {
    return this.http.post(`${environment.Url}/offer/${offerId}/clear-matched-category`, {
      offer_line_id: offerLineId,
    });
  }

  convertToOrder(offerId: number | string, payload: any) {
    return this.http.post(`${environment.Url}/offer/${offerId}/convert-to-order`, payload);
  }

}
