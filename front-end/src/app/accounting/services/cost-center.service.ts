import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';
import { CostCenter, CostCenterResponse } from '../interfaces/cost-center.interface';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class CostCenterService {

  constructor(private http: HttpClient) { }

  getAll(params?: any): Observable<CostCenterResponse> {
    let httpParams = new HttpParams();
    if (params) {
      Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined) {
          httpParams = httpParams.set(key, params[key]);
        }
      });
    }
    return this.http.get<CostCenterResponse>(`${environment.Url}/accounting/cost-centers`, { params: httpParams });
  }

  getTree(): Observable<CostCenter[]> {
    return this.http.get<CostCenter[]>(`${environment.Url}/accounting/cost-centers/tree`);
  }

  getById(id: number): Observable<CostCenter> {
    return this.http.get<CostCenter>(`${environment.Url}/accounting/cost-centers/${id}`);
  }

  create(costCenter: CostCenter): Observable<{ message: string; data: CostCenter }> {
    return this.http.post<{ message: string; data: CostCenter }>(`${environment.Url}/accounting/cost-centers`, costCenter);
  }

  update(id: number, costCenter: Partial<CostCenter>): Observable<{ message: string; data: CostCenter }> {
    return this.http.put<{ message: string; data: CostCenter }>(`${environment.Url}/accounting/cost-centers/${id}`, costCenter);
  }

  delete(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${environment.Url}/accounting/cost-centers/${id}`);
  }
}

