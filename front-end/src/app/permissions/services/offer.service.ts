import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class OfferService {

  constructor( private http:HttpClient) { }

  getOffers(items:number,page:number){
    return this.http.get(`${environment.Url}/offer?itemsPerPage=${items}&page=${page}`);
  }

  getOfferById(id:any){
    return this.http.get(`${environment.Url}/offer/${id}`);
  }

  addOffer(formData:any){
    return this.http.post(`${environment.Url}/offer` , formData);
  }

}
