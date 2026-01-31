import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';
import { TreeAccount, TreeAccountResponse } from '../interfaces/tree-account.interface';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class TreeAccountService {

  constructor(private http: HttpClient) { }

  getAll(): Observable<TreeAccountResponse> {
    return this.http.get<TreeAccountResponse>(`${environment.Url}/tree_accounts`);
  }

  getRootAccounts(): Observable<TreeAccountResponse> {
    return this.http.get<TreeAccountResponse>(`${environment.Url}/tree_accounts?parent=true`);
  }

  getById(id: number): Observable<{ success: boolean; status: number; message: string; data: TreeAccount }> {
    return this.http.get<{ success: boolean; status: number; message: string; data: TreeAccount }>(`${environment.Url}/tree_accounts/${id}`);
  }

  create(account: TreeAccount): Observable<{ success: boolean; status: number; message: string; data: TreeAccount }> {
    return this.http.post<{ success: boolean; status: number; message: string; data: TreeAccount }>(`${environment.Url}/tree_accounts`, account);
  }

  update(id: number, account: TreeAccount): Observable<{ success: boolean; status: number; message: string; data: TreeAccount }> {
    return this.http.put<{ success: boolean; status: number; message: string; data: TreeAccount }>(`${environment.Url}/tree_accounts/${id}`, account);
  }

  delete(id: number): Observable<{ success: boolean; status: number; message: string; data: null }> {
    return this.http.delete<{ success: boolean; status: number; message: string; data: null }>(`${environment.Url}/tree_accounts/${id}`);
  }
}

