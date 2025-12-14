import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ShippingLineStatementService {

  constructor(private http:HttpClient) { }

  get(params):Observable<any>
  {
    return this.http.get<any>(`${environment.Url}/ShippingLineStatement`, {params})
  }

  add(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/ShippingLineStatement`,formData)
  }

  cancel(id:number){
    return this.http.delete<any>(`${environment.Url}/ShippingLineStatement/${id}`)
  }

}
