import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
    providedIn: 'root'
})
export class SafeService {
    private apiUrl = environment.Url + '/accounting/safes';

    constructor(private http: HttpClient) { }

    getAll(params?: any): Observable<any> {
        let httpParams = new HttpParams();
        if (params) {
            Object.keys(params).forEach(key => {
                if (params[key] !== null && params[key] !== undefined) {
                    httpParams = httpParams.set(key, params[key]);
                }
            });
        }
        return this.http.get<any>(this.apiUrl, { params: httpParams });
    }

    getById(id: number): Observable<any> {
        return this.http.get<any>(`${this.apiUrl}/${id}`);
    }

    create(data: any): Observable<any> {
        return this.http.post<any>(this.apiUrl, data);
    }

    update(id: number, data: any): Observable<any> {
        return this.http.put<any>(`${this.apiUrl}/${id}`, data);
    }

    delete(id: number): Observable<any> {
        return this.http.delete<any>(`${this.apiUrl}/${id}`);
    }

    transfer(data: any): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/transfer`, data);
    }

    directTransaction(data: any): Observable<any> {
        return this.http.post<any>(`${this.apiUrl}/direct-transaction`, data);
    }
}
