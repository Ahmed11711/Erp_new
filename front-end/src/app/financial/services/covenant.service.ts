import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

export interface CovenantPayload {
  transaction_date: string;
  covenant_type: string;
  holder_kind: string;
  payment_type: 'safe' | 'bank' | 'service_account';
  safe_id: number | null;
  bank_id: number | null;
  service_account_id: number | null;
  amount: number;
  description?: string | null;
  note?: string | null;
}

@Injectable({ providedIn: 'root' })
export class CovenantService {
  private base = `${environment.Url}/covenants`;

  constructor(private http: HttpClient) {}

  list(): Observable<any[]> {
    return this.http.get<any[]>(this.base);
  }

  create(payload: CovenantPayload): Observable<any> {
    return this.http.post<any>(this.base, payload);
  }
}
