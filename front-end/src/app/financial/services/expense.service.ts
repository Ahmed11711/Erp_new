import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ExpenseService {

  constructor(private http:HttpClient) { }


  add(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/expense`,formData)
  }

  edit(id:any,formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/editexpense/${id}`,formData)
  }

  data(){
    return this.http.get<any>(`${environment.Url}/expense`)
  }

  getByID(id:number){
    return this.http.get<any>(`${environment.Url}/expense/${id}`)
  }

  deleteExpense(id:number){
    return this.http.post<any>(`${environment.Url}/deleteexpense/${id}`,'')
  }

  search(items:number,page:number,search:any){
    return this.http.get(`${environment.Url}/expense/search?itemsPerPage=${items}&page=${page}`,{params:search});
  }

}
