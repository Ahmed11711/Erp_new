import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { data } from 'jquery';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ApproveService {

  constructor(private http: HttpClient) {}

  approvalStatus(data: any): Observable<any> {
    return this.http.post<any>(`${environment.Url}/approvals`, data);
  }

  getApprovals(items:number,page:number, params:any): Observable<any> {
    return this.http.get<any>(`${environment.Url}/approvals?itemsPerPage=${items}&page=${page}` , {params});
  }
}
