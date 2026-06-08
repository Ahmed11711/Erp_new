import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ShippingCompanyService {

  constructor(private http:HttpClient) { }

  addLine(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/shippingcompanies`,formData)
  }

  editLine(formData:any , id:number):Observable<any>
  {
    return this.http.put<any>(`${environment.Url}/shippingcompanies/${id}`,formData)
  }

  shippingCompanies(){
    return this.http.get<any>(`${environment.Url}/shippingcompanies`)
  }

  unlinkedSummary() {
    return this.http.get<any>(`${environment.Url}/shippingcompanies/unlinked-summary`);
  }

  linkUnlinked() {
    return this.http.post<any>(`${environment.Url}/shippingcompanies/link-unlinked`, {});
  }

  linkAccount(id: number) {
    return this.http.post<any>(`${environment.Url}/shippingcompanies/${id}/link-account`, {});
  }

  shippingCompanySelect(){
    return this.http.get<any>(`${environment.Url}/shippingcompanySelect`)
  }

  shippingCompanyById(id:number , items:number,page:number){
    return this.http.get(`${environment.Url}/shippingcompany/${id}?itemsPerPage=${items}&page=${page}`);
  }

  deleteLine(id:number){
    return this.http.delete<any>(`${environment.Url}/shippingcompanies/${id}`)
  }

  search(items:number,page:number,search:any){
    // search = new HttpParams();
    console.log(search);

    return this.http.get(`${environment.Url}/shippingcompany/search?itemsPerPage=${items}&page=${page}`,{params:search});
  }

  shippingCompaniesReport(params: { date_from: string; date_to: string; q?: string }) {
    return this.http.get<any>(`${environment.Url}/reports/shipping-companies`, { params: params as any });
  }

}
