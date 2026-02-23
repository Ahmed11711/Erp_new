import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

export interface PaymentSourceItem {
  type: 'safe' | 'bank' | 'service_account';
  id: number;
  name: string;
  balance: number;
  account_id: number | null;
  account: { id: number; name: string; code: string; balance: number } | null;
  label: string;
}

export interface PaymentSourcesResponse {
  safes: PaymentSourceItem[];
  banks: PaymentSourceItem[];
  service_accounts: PaymentSourceItem[];
}

@Injectable({
  providedIn: 'root'
})
export class PaymentSourcesService {
  private apiUrl = environment.Url + '/accounting/payment-sources';

  constructor(private http: HttpClient) {}

  getPaymentSources(): Observable<PaymentSourcesResponse> {
    return this.http.get<PaymentSourcesResponse>(this.apiUrl);
  }
}
