import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class StockService {

  constructor(private http:HttpClient) { }


  list(params:any = {}):Observable<any>
  {
    return this.http.get<any>(`${environment.Url}/stocks`,{params})
  }

  add(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/stocks`,formData)
  }


  edit(id:any , formData:any):Observable<any>
  {
    return this.http.put<any>(`${environment.Url}/stocks/${id}`,formData)
  }


  delete(id:any):Observable<any>
  {
    return this.http.delete<any>(`${environment.Url}/stocks/${id}`)
  }

}
