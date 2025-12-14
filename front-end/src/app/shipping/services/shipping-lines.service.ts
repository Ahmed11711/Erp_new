import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ShippingLinesService {

  constructor(private http:HttpClient) { }


  addLine(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/shippinglines`,formData)
  }

  editLine(formData:any , id:number):Observable<any>
  {
    return this.http.put<any>(`${environment.Url}/shippinglines/${id}`,formData)
  }

  dataLines(){
    return this.http.get<any>(`${environment.Url}/shippinglines`)
  }

  deleteLine(id:number){
    return this.http.delete<any>(`${environment.Url}/shippinglines/${id}`)
  }

}
