import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
    providedIn: 'root'
})
export class ReportsService {

    constructor(private http: HttpClient) { }

    getAccountStatement(accountId: number, dateFrom?: string, dateTo?: string) {
        let params = new HttpParams().set('account_id', accountId);
        if (dateFrom) params = params.set('date_from', dateFrom);
        if (dateTo) params = params.set('date_to', dateTo);

        return this.http.get(environment.Url + '/accounting/reports/account-statement', { params });
    }
}
