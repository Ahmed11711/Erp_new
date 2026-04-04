import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
    providedIn: 'root'
})
export class AccountingReportService {
    private apiUrl = environment.Url + '/accounting/reports';
    private accountingUrl = environment.Url + '/accounting';

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
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                httpParams = httpParams.append(key, params[key]);
            }
        });
        return this.http.get(`${this.apiUrl}/trial-balance`, { params: httpParams });
    }

    getAccountStatement(params: any): Observable<any> {
        let httpParams = new HttpParams();
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                httpParams = httpParams.append(key, params[key]);
            }
        });
        return this.http.get(`${this.apiUrl}/account-statement`, { params: httpParams });
    }

    getAccountingTree(): Observable<any> {
        return this.http.get(`${this.apiUrl}/accounting-tree`);
    }

    getAccountHierarchy(params?: any): Observable<any> {
        let httpParams = new HttpParams();
        if (params) {
            Object.keys(params).forEach(key => {
                if (params[key]) httpParams = httpParams.append(key, params[key]);
            });
        }
        return this.http.get(`${this.apiUrl}/account-hierarchy`, { params: httpParams });
    }

    validateIncomeStructure(): Observable<any> {
        return this.http.get(`${this.apiUrl}/validate-income-structure`);
    }

    processCashTransaction(transactionData: any): Observable<any> {
        return this.http.post(`${this.accountingUrl}/process-cash-transaction`, transactionData);
    }

    updateHierarchyBalances(accountId: number): Observable<any> {
        return this.http.post(`${this.accountingUrl}/update-hierarchy-balances`, { account_id: accountId });
    }

    recalculateAllHierarchyBalances(): Observable<any> {
        return this.http.post(`${this.accountingUrl}/recalculate-all-hierarchy-balances`, {});
    }

    getProductPerformance(params: { date_from?: string; date_to?: string }): Observable<any> {
        let httpParams = new HttpParams();
        if (params?.date_from) httpParams = httpParams.set('date_from', params.date_from);
        if (params?.date_to) httpParams = httpParams.set('date_to', params.date_to);
        return this.http.get(`${this.apiUrl}/product-performance`, { params: httpParams });
    }

    getCategoryProfitability(params: { date_from?: string; date_to?: string }): Observable<any> {
        let httpParams = new HttpParams();
        if (params?.date_from) httpParams = httpParams.set('date_from', params.date_from);
        if (params?.date_to) httpParams = httpParams.set('date_to', params.date_to);
        return this.http.get(`${this.apiUrl}/category-profitability`, { params: httpParams });
    }
}
