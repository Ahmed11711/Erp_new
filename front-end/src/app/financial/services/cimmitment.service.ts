import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class CimmitmentService {

  constructor(private http:HttpClient) { }


  add(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/cimmitments`,formData)
  }

  data(){
    return this.http.get<any>(`${environment.Url}/cimmitments`)
  }

  pay(id: number, payload: {
    amount: number;
    cash_account_id: number;
    payment_source_type: 'safe' | 'bank' | 'service_account';
    payment_source_id: number;
    description?: string;
  }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/cimmitments/${id}/pay`, payload);
  }
}
