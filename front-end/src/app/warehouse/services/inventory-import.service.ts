import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

export interface StockCountPreviewRow {
  id: number;
  row_number: number;
  product_name: string;
  excel_warehouse: string;
  excel_quantity: number;
  excel_unit_cost: number | null;
  match_type: 'exact_sku' | 'exact_name' | 'fuzzy' | 'new';
  match_confidence: number | null;
  matched_name: string | null;
  matched_category_id: number | null;
  system_quantity: number | null;
  quantity_difference: number | null;
  adjustment_direction: 'in' | 'out' | null;
  assigned_warehouse: string;
  is_new_product: boolean;
  classification_method: string | null;
  warnings: string[];
}

export interface StockCountPreviewResponse {
  import_token: string;
  expires_in_minutes: number;
  filename: string;
  summary: {
    total_rows: number;
    matched: number;
    new_items: number;
    duplicates_merged: number;
    with_adjustment: number;
    with_gain: number;
    with_loss: number;
    low_confidence_matches: number;
  };
  warnings: string[];
  rows: StockCountPreviewRow[];
  message: string;
}

export interface StockCountConfirmResponse {
  message: string;
  summary: any;
  import_id: number;
}

export interface StockCountHistoryItem {
  id: number;
  filename: string;
  status: string;
  total_rows: number;
  matched_rows: number;
  new_items_rows: number;
  adjusted_rows: number;
  summary: any;
  created_at: string;
  confirmed_at: string | null;
}

@Injectable({ providedIn: 'root' })
export class InventoryImportService {
  private readonly base = `${environment.Url}/inventory/import`;
  private readonly stockCountBase = `${environment.Url}/inventory/stock-count`;

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

  // ─── Stock Count Reconciliation ───────────────────────────

  stockCountPreview(file: File): Observable<StockCountPreviewResponse> {
    const fd = new FormData();
    fd.append('file', file);
    return this.http.post<StockCountPreviewResponse>(`${this.stockCountBase}/preview`, fd);
  }

  stockCountConfirm(importToken: string): Observable<StockCountConfirmResponse> {
    return this.http.post<StockCountConfirmResponse>(`${this.stockCountBase}/confirm`, {
      import_token: importToken,
    });
  }

  stockCountCancel(importToken: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.stockCountBase}/cancel`, {
      import_token: importToken,
    });
  }

  stockCountHistory(): Observable<{ imports: StockCountHistoryItem[] }> {
    return this.http.get<{ imports: StockCountHistoryItem[] }>(`${this.stockCountBase}/history`);
  }

  stockCountDetails(id: number): Observable<any> {
    return this.http.get<any>(`${this.stockCountBase}/${id}`);
  }
}
