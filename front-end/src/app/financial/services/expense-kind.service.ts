import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ExpenseKindService {

  constructor(private http:HttpClient) { }


  add(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/expense_kind`,formData)
  }

  data(){
    return this.http.get<any>(`${environment.Url}/expense_kind`)
  }

  search(items:number,page:number,search:any){
    return this.http.get<any>(`${environment.Url}/expense_kind/search?itemsPerPage=${items}&page=${page}`,{params:search})
  }

  deleteUser(id:number){
    return this.http.delete<any>(`${environment.Url}/expense_kind/${id}`)
  }
}
