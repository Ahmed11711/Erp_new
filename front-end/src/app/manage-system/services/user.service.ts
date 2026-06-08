import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class UserService {

  constructor(private http:HttpClient) { }


  add(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/register`,formData)
  }

  data(){
    return this.http.get<any>(`${environment.Url}/users`)
  }

  /** للتتبع ومراجع قصيرة — لا يتطلب قسم Admin (system.rbac أو Admin قديماً) */
  compactDirectory(){
    return this.http.get<any>(`${environment.Url}/users/compact-directory`)
  }

  usersForNotifi(){
    return this.http.get<any>(`${environment.Url}/usersnotification`)
  }

  deleteUser(id:number){
    return this.http.delete<any>(`${environment.Url}/user/delete/${id}`)
  }

}
