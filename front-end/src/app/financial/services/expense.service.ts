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

  search(items:number,page:number,search:any): Observable<any> {
    return this.http.get<any>(`${environment.Url}/expense/search?itemsPerPage=${items}&page=${page}`,{params:search});
  }

  purgePreview() {
    return this.http.get<{
      active_expense_count: number;
      total_expense_count: number;
      gl_entry_count: number;
      expense_line_count: number;
    }>(`${environment.Url}/expense/purge-preview`);
  }

  purgeAll() {
    return this.http.post(`${environment.Url}/expense/purge-all`, { confirm: true });
  }

}
