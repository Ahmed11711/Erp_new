import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';
import { httpOptionsFromParams } from 'src/app/shared/utils/http-query.util';

@Injectable({
    providedIn: 'root'
})
export class AccountingReportService {
    private apiUrl = environment.Url + '/accounting/reports';
    private accountingUrl = environment.Url + '/accounting';

    constructor(private http: HttpClient) { }

    getDailyLedger(params: any): Observable<any> {
        return this.http.get(`${this.apiUrl}/daily-ledger`, httpOptionsFromParams(params));
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

    /** معاينة ملف ميزان مراجعة Excel ومطابقة أكواد الحسابات */
    previewTrialBalanceOpeningImport(file: File): Observable<any> {
        const formData = new FormData();
        formData.append('file', file);
        return this.http.post(`${this.apiUrl}/trial-balance/import-preview`, formData);
    }

    /** إنشاء حساب ناقص بنفس كود الإكسيل */
    createMissingAccountFromImport(payload: {
        account_code?: string;
        account_name: string;
        type: string;
        parent_id?: number | null;
        debit?: number;
        credit?: number;
    }): Observable<any> {
        return this.http.post(`${this.apiUrl}/trial-balance/import-create-account`, payload);
    }

    /** ترحيل الأرصدة الافتتاحية بعد المعاينة */
    applyTrialBalanceOpeningImport(payload: {
        opening_date: string;
        counter_account_id: number;
        reason?: string;
        lines: Array<{ tree_account_id: number; debit?: number; credit?: number; target_net?: number }>;
    }): Observable<any> {
        return this.http.post(`${this.apiUrl}/trial-balance/import-apply`, payload);
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
        return this.http.get(`${this.apiUrl}/account-hierarchy`, httpOptionsFromParams(params));
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

    /** معاينة فروقات تسوية أرصدة المخزون مع التكلفة الفعلية للأصناف (بدون ترحيل). */
    previewInventoryGlSync(): Observable<any> {
        return this.http.get(`${environment.Url}/categories/inventory-gl-sync-preview`);
    }

    /** ترحيل قيد تسوية لمطابقة أرصدة حسابات المخزون مع التكلفة الفعلية للأصناف. */
    postInventoryGlSync(): Observable<any> {
        return this.http.post(`${environment.Url}/categories/inventory-gl-sync`, {});
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
