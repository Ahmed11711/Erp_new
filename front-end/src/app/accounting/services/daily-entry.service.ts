import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';
import { DailyEntry, DailyEntryResponse, DailyEntryFormData } from '../interfaces/daily-entry.interface';

@Injectable({
  providedIn: 'root'
})
export class DailyEntryService {

  constructor(private http: HttpClient) { }

  getAll(params?: any): Observable<DailyEntryResponse> {
    let httpParams = new HttpParams();
    if (params) {
      Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
          httpParams = httpParams.set(key, params[key]);
        }
      });
    }
    return this.http.get<DailyEntryResponse>(`${environment.Url}/accounting/daily-entries`, { params: httpParams });
  }

  getById(id: number): Observable<DailyEntry> {
    return this.http.get<DailyEntry>(`${environment.Url}/accounting/daily-entries/${id}`);
  }

  create(data: DailyEntryFormData): Observable<any> {
    return this.http.post<any>(`${environment.Url}/accounting/daily-entries`, data);
  }

  update(id: number, data: DailyEntryFormData): Observable<any> {
    return this.http.put<any>(`${environment.Url}/accounting/daily-entries/${id}`, data);
  }

  delete(id: number): Observable<any> {
    return this.http.delete<any>(`${environment.Url}/accounting/daily-entries/${id}`);
  }
}

