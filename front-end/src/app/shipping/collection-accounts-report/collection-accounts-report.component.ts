import { Component, OnInit } from '@angular/core';
import { CollectionCompanyService } from '../services/collection-company.service';

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
  loading = false;
  statementSource = '';

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
    this.loadStatement();
  }

  loadStatement(page = 1): void {
    if (!this.selected) return;
    const params: Record<string, string> = { page: String(page), per_page: '40' };
    if (this.dateFrom) params['date_from'] = this.dateFrom;
    if (this.dateTo) params['date_to'] = this.dateTo;
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
  }

  applyFilters(): void {
    this.load();
    if (this.selected) this.loadStatement();
  }

  pendingAmount(c: any): number {
    return parseFloat(c?.pending_to_collect) || 0;
  }

  cashReceiptQuery(c: any): Record<string, string | number> {
    const q: Record<string, string | number> = {
      party: 'shipping_company',
      type: 'receipt',
      amount: this.pendingAmount(c),
    };
    if (c.linked_shipping_company_id) {
      q['shipping_company_id'] = c.linked_shipping_company_id;
    }
    return q;
  }

  orderLink(id: number): string[] {
    return ['/dashboard/shipping/orderdetails', String(id)];
  }
}
