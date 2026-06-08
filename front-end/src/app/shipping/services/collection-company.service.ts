import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';
import { httpOptionsFromParams } from 'src/app/shared/utils/http-query.util';

@Injectable({ providedIn: 'root' })
export class CollectionCompanyService {
  constructor(private http: HttpClient) {}

  list(params: Record<string, string> = {}) {
    return this.http.get(`${environment.Url}/collection-companies`, httpOptionsFromParams(params));
  }

  unlinkedSummary() {
    return this.http.get<any>(`${environment.Url}/collection-companies/unlinked-summary`);
  }

  linkUnlinked() {
    return this.http.post<any>(`${environment.Url}/collection-companies/link-unlinked`, {});
  }

  linkAccount(id: number) {
    return this.http.post<any>(`${environment.Url}/collection-companies/${id}/link-account`, {});
  }

  select() {
    return this.http.get(`${environment.Url}/collection-companies/select`);
  }

  get(id: number) {
    return this.http.get(`${environment.Url}/collection-companies/${id}`);
  }

  create(body: any) {
    return this.http.post(`${environment.Url}/collection-companies`, body);
  }

  update(id: number, body: any) {
    return this.http.put(`${environment.Url}/collection-companies/${id}`, body);
  }

  delete(id: number) {
    return this.http.delete(`${environment.Url}/collection-companies/${id}`);
  }

  accountsReport(params: Record<string, string> = {}) {
    return this.http.get(`${environment.Url}/reports/collection-accounts`, httpOptionsFromParams(params));
  }

  statementReport(id: number, params: Record<string, string> = {}) {
    return this.http.get(
      `${environment.Url}/reports/collection-accounts/${id}/statement`,
      httpOptionsFromParams(params)
    );
  }

  pendingOrdersReport(params: Record<string, string> = {}) {
    return this.http.get(
      `${environment.Url}/reports/collection-accounts/pending-orders`,
      httpOptionsFromParams(params)
    );
  }
}
