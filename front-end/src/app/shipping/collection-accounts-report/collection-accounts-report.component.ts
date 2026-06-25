import { Component, OnInit } from '@angular/core';
import { CollectionCompanyService } from '../services/collection-company.service';

interface CollectibleRow {
  order_id?: number;
  id?: number;
  collectible?: boolean;
  collectible_amount?: number;
  collection_receivable_amount?: number;
  amount?: number;
  is_done?: number | boolean;
  status?: string;
  order_status?: string;
}

@Component({
  selector: 'app-collection-accounts-report',
  templateUrl: './collection-accounts-report.component.html',
  styleUrls: ['./collection-accounts-report.component.css'],
})
export class CollectionAccountsReportComponent implements OnInit {
  companies: any[] = [];
  summary: any = {};
  selected: any = null;
  statement: any = null;
  aggregates: any = null;
  details: any = null;
  activeTab: 'summary' | 'statement' = 'summary';
  dateFrom = '';
  dateTo = '';
  pendingOnly = true;
  loading = false;
  statementSource = '';

  /** order_id → collectible amount */
  selectedOrders = new Map<number, number>();

  constructor(private api: CollectionCompanyService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    const params: Record<string, string> = {};
    if (this.dateFrom) params['date_from'] = this.dateFrom;
    if (this.dateTo) params['date_to'] = this.dateTo;
    this.api.accountsReport(params).subscribe({
      next: (res: any) => {
        this.companies = res.data || [];
        this.summary = res.summary || {};
        this.loading = false;
      },
      error: () => (this.loading = false),
    });
  }

  openStatement(c: any): void {
    this.selected = c;
    this.activeTab = 'statement';
    this.clearSelection();
    this.loadStatement();
  }

  loadStatement(page = 1): void {
    if (!this.selected) return;
    const params: Record<string, string> = {
      page: String(page),
      per_page: '40',
    };
    if (this.dateFrom) params['date_from'] = this.dateFrom;
    if (this.dateTo) params['date_to'] = this.dateTo;
    if (this.pendingOnly) params['pending_only'] = '1';
    this.api.statementReport(this.selected.id, params).subscribe((res: any) => {
      this.statement = res.company;
      this.aggregates = res.aggregates;
      this.details = res.details;
      this.statementSource = res.source || '';
    });
  }

  back(): void {
    this.activeTab = 'summary';
    this.selected = null;
    this.clearSelection();
  }

  applyFilters(): void {
    this.load();
    if (this.selected) this.loadStatement();
  }

  onPendingOnlyChange(): void {
    this.clearSelection();
    if (this.selected) this.loadStatement();
  }

  pendingAmount(c: any): number {
    return parseFloat(c?.pending_to_collect) || 0;
  }

  rowOrderId(row: CollectibleRow): number | null {
    const id = row.order_id ?? row.id;
    return id != null && id > 0 ? Number(id) : null;
  }

  rowCollectibleAmount(row: CollectibleRow): number {
    if (row.collectible_amount != null && !isNaN(Number(row.collectible_amount))) {
      return Math.round(Number(row.collectible_amount) * 100) / 100;
    }
    if (this.statementSource === 'orders') {
      const v = parseFloat(String(row.collection_receivable_amount ?? 0));
      return !isNaN(v) ? Math.round(v * 100) / 100 : 0;
    }
    const v = parseFloat(String(row.amount ?? 0));
    return !isNaN(v) ? Math.round(Math.abs(v) * 100) / 100 : 0;
  }

  isCollectibleRow(row: CollectibleRow): boolean {
    if (row.collectible === true) return true;
    if (row.collectible === false) return false;
    const oid = this.rowOrderId(row);
    if (!oid) return false;
    if (this.statementSource === 'ledger') {
      return !row.is_done && (row.status === 'تم شحن' || row.status === 'تم التسليم');
    }
    const amt = this.rowCollectibleAmount(row);
    return amt > 0.009 && row.order_status !== 'تم التحصيل' && row.order_status !== 'ملغي';
  }

  isRowSelected(row: CollectibleRow): boolean {
    const oid = this.rowOrderId(row);
    return oid != null && this.selectedOrders.has(oid);
  }

  toggleRow(row: CollectibleRow): void {
    const oid = this.rowOrderId(row);
    if (!oid || !this.isCollectibleRow(row)) return;
    if (this.selectedOrders.has(oid)) {
      this.selectedOrders.delete(oid);
    } else {
      this.selectedOrders.set(oid, this.rowCollectibleAmount(row));
    }
  }

  selectAllPendingOnPage(): void {
    const rows: CollectibleRow[] = this.details?.data || [];
    for (const row of rows) {
      if (!this.isCollectibleRow(row)) continue;
      const oid = this.rowOrderId(row);
      if (oid) this.selectedOrders.set(oid, this.rowCollectibleAmount(row));
    }
  }

  clearSelection(): void {
    this.selectedOrders.clear();
  }

  get selectedOrderIds(): number[] {
    return Array.from(this.selectedOrders.keys());
  }

  get selectedTotal(): number {
    let sum = 0;
    this.selectedOrders.forEach((amt) => (sum += amt));
    return Math.round(sum * 100) / 100;
  }

  cashReceiptQuery(c: any, amount?: number, orderIds?: number[]): Record<string, string | number> {
    const q: Record<string, string | number> = {
      party: 'collection_company',
      type: 'receipt',
      collection_company_id: c.id,
      amount: amount ?? this.pendingAmount(c),
      note: `قبض — ${c.name || c.id} (من ذمم شركات التحصيل)`,
    };
    const ids = orderIds?.length ? orderIds : c.pending_order_ids;
    if (ids?.length) {
      q['settled_order_ids'] = ids.join(',');
    }
    return q;
  }

  cashReceiptQuerySelected(): Record<string, string | number> | null {
    if (!this.selected || this.selectedOrderIds.length === 0) return null;
    return this.cashReceiptQuery(this.selected, this.selectedTotal, this.selectedOrderIds);
  }

  cashPaymentQuery(c: any): Record<string, string | number> {
    return {
      party: 'collection_company',
      type: 'payment',
      collection_company_id: c.id,
      note: `صرف — ${c.name || c.id} (من ذمم شركات التحصيل)`,
    };
  }

  orderLink(id: number): string[] {
    return ['/dashboard/shipping/orderdetails', String(id)];
  }
}
