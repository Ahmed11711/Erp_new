import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from 'src/env/env';

export interface Voucher {
    id?: number;
    date: string;
    type: 'receipt' | 'payment';
    voucher_type: 'client' | 'supplier' | 'shipping_company';
    account_id: number;
    client_id?: number;
    supplier_id?: number;
    shipping_company_id?: number;
    client_or_supplier_name?: string;
    amount: number;
    notes?: string;
    reference_number?: string;
    entry_number?: string;
    user?: any;
    account?: any;
    client?: any;
    supplier?: any;
}

@Injectable({
    providedIn: 'root'
})
export class VoucherService {
    private apiUrl = `${environment.Url}/accounting/vouchers`;

    constructor(private http: HttpClient) { }

    getVouchers(params: any = {}): Observable<any> {
        let httpParams = new HttpParams();
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                httpParams = httpParams.append(key, params[key]);
            }
        });
        return this.http.get<any>(this.apiUrl, { params: httpParams });
    }

    getVoucher(id: number): Observable<any> {
        return this.http.get<any>(`${this.apiUrl}/${id}`);
    }

    createVoucher(voucher: Voucher): Observable<any> {
        return this.http.post<any>(this.apiUrl, voucher);
    }

    updateVoucher(id: number, voucher: Voucher): Observable<any> {
        return this.http.put<any>(`${this.apiUrl}/${id}`, voucher);
    }

    deleteVoucher(id: number): Observable<any> {
        return this.http.delete<any>(`${this.apiUrl}/${id}`);
    }

    getClients(): Observable<any> {
        return this.http.get<any>(`${environment.Url}/companies`);
    }

    getSuppliers(): Observable<any> {
        return this.http.get<any>(`${environment.Url}/suppliers`);
    }

    getShippingCompaniesSelect(): Observable<{ id: number; name: string; type: string }[]> {
        return this.http.get<{ id: number; name: string; type: string }[]>(`${environment.Url}/shippingcompanySelect`);
    }

    /**
     * شجرة الحسابات كمصفوفة مسطحة.
     * الـ API قد يرجع { data: ResourceCollection } حيث ResourceCollection = { data: TreeAccount[] }.
     */
    getAccounts(): Observable<any[]> {
        return this.http.get<any>(`${environment.Url}/tree_accounts`).pipe(
            map((res) => {
                if (!res) return [];
                if (Array.isArray(res)) return res;
                const top = res.data;
                if (Array.isArray(top)) return top;
                if (top && typeof top === 'object' && Array.isArray(top.data)) return top.data;
                return [];
            })
        );
    }
}
