import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
    providedIn: 'root'
})
export class AccountingReportService {
    private apiUrl = environment.Url + '/accounting/reports';

    constructor(private http: HttpClient) { }

    getDailyLedger(params: any): Observable<any> {
        let httpParams = new HttpParams();

        // Append all params to HttpParams
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                httpParams = httpParams.append(key, params[key]);
            }
        });

        return this.http.get(`${this.apiUrl}/daily-ledger`, { params: httpParams });
    }

    getAccountBalance(params: any): Observable<any> {
        let httpParams = new HttpParams();
        Object.keys(params).forEach(key => {
            if (params[key]) httpParams = httpParams.append(key, params[key]);
        });
        return this.http.get(`${this.apiUrl}/account-balance`, { params: httpParams });
    }

    getTrialBalance(params: any): Observable<any> {
        let httpParams = new HttpParams();
        Object.keys(params).forEach(key => {
            if (params[key]) httpParams = httpParams.append(key, params[key]);
        });
        return this.http.get(`${this.apiUrl}/trial-balance`, { params: httpParams });
    }

    getAccountingTree(): Observable<any> {
        return this.http.get(`${this.apiUrl}/accounting-tree`);
    }
}
