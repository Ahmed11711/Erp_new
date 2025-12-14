import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class CompaniesService {

  constructor(private http:HttpClient) { }

  addCompany(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/companies`,formData);
  }

  data():Observable<any>
  {
    return this.http.get<any>(`${environment.Url}/companies`);
  }

  search(items:number,page:number,search:any){
    return this.http.get(`${environment.Url}/companies/search?itemsPerPage=${items}&page=${page}`,{params:search});
  }

  companyBalanceDetails(id:any , items:number,page:number){
    return this.http.get(`${environment.Url}/companies/${id}?itemsPerPage=${items}&page=${page}`);
  }

  companyCollect(id:number , formData:any){
    return this.http.post(`${environment.Url}/companies/companycollect/${id}` ,formData);
  }
}
