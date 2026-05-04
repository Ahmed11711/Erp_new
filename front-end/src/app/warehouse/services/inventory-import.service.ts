import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({ providedIn: 'root' })
export class InventoryImportService {
  private readonly base = `${environment.Url}/inventory/import`;

  constructor(private http: HttpClient) {}

  importItems(file: File): Observable<{ created: number; updated: number }> {
    const fd = new FormData();
    fd.append('file', file);
    return this.http.post<{ created: number; updated: number }>(`${this.base}/items`, fd);
  }

  importOpeningBalances(file: File, createMissingItems: boolean): Observable<{ processed_lines: number }> {
    const fd = new FormData();
    fd.append('file', file);
    if (createMissingItems) {
      fd.append('create_missing_items', '1');
    }
    return this.http.post<{ processed_lines: number }>(`${this.base}/opening-balances`, fd);
  }

  importAdjustments(file: File): Observable<{ processed_lines: number }> {
    const fd = new FormData();
    fd.append('file', file);
    return this.http.post<{ processed_lines: number }>(`${this.base}/adjustments`, fd);
  }
}
