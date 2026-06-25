import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from 'src/env/env';

export interface Voucher {
    id?: number;
    date: string;
    type: 'receipt' | 'payment';
    voucher_type: 'client' | 'supplier' | 'shipping_company' | 'collection_company';
    account_id: number;
    client_kind?: 'company' | 'individual' | null;
    client_id?: number | null;
    individual_customer_phone?: string | null;
    individual_customer_name?: string | null;
    supplier_id?: number;
    shipping_company_id?: number;
    collection_company_id?: number;
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

    updateVoucher(id: number, voucher: Partial<Voucher>): Observable<any> {
        return this.http.put<any>(`${this.apiUrl}/${id}`, voucher);
    }

    updateVoucherDate(id: number, date: string): Observable<any> {
        return this.updateVoucher(id, { date } as Voucher);
    }

    deleteVoucher(id: number): Observable<any> {
        return this.http.delete<any>(`${this.apiUrl}/${id}`);
    }

    getClients(): Observable<any> {
        return this.http.get<any>(`${environment.Url}/companies`);
    }

    getClientOptions(search = ''): Observable<{ companies: { id: number; label: string }[]; individuals: { phone: string; name: string; label: string }[] }> {
        let params = new HttpParams();
        if (search?.trim()) {
            params = params.set('search', search.trim());
        }
        return this.http.get<any>(`${this.apiUrl}/client-options`, { params });
    }

    getSuppliers(): Observable<any> {
        return this.http.get<any>(`${environment.Url}/suppliers`);
    }

    getShippingCompaniesSelect(): Observable<{ id: number; name: string; type: string }[]> {
        return this.http.get<{ id: number; name: string; type: string }[]>(`${environment.Url}/shippingcompanySelect`);
    }

    getCollectionCompaniesSelect(): Observable<{ id: number; name: string; linked_shipping_company_id?: number | null }[]> {
        return this.http.get<{ id: number; name: string; linked_shipping_company_id?: number | null }[]>(
            `${environment.Url}/collection-companies/select`
        );
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
