import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class PermissionService {

  constructor(private http:HttpClient) { }

  givePermission(id:number , permission:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/give_permission/${id}`,permission)
  }

  revokePermission(id:number , permission:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/revoke_permssion/${id}`,permission)
  }

  userPermission(id:number):Observable<any>
  {
    return this.http.get<any>(`${environment.Url}/user_permission/${id}`)
  }
}
