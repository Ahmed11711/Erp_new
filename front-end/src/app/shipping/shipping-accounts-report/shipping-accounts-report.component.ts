import { Component, OnInit } from '@angular/core';
import { OrderService } from '../services/order.service';
import { DatePipe } from '@angular/common';

@Component({
  selector: 'app-shipping-accounts-report',
  templateUrl: './shipping-accounts-report.component.html',
  styleUrls: ['./shipping-accounts-report.component.css']
})
export class ShippingAccountsReportComponent implements OnInit {
  companies: any[] = [];
  summary: any = {};
  selectedCompany: any = null;
  statement: any = null;
  statementAggregates: any = null;
  statementDetails: any = null;
  settlementData: any[] = [];
  settlementTotals: any = {};

  activeTab: 'summary' | 'statement' | 'settlement' = 'summary';
  typeFilter: string = '';
  dateFrom: string = '';
  dateTo: string = '';
  statusFilter: string = '';
  isDoneFilter: string = '';

  loading = false;

  constructor(
    private orderService: OrderService,
    private datePipe: DatePipe
  ) {}

  ngOnInit() {
    this.loadSummary();
    this.loadSettlement();
  }

  loadSummary() {
    this.loading = true;
    const params: any = {};
    if (this.typeFilter) params.type = this.typeFilter;
    if (this.dateFrom) params.date_from = this.dateFrom;
    if (this.dateTo) params.date_to = this.dateTo;

    this.orderService.getShippingAccountsSummary(params).subscribe(
      (res: any) => {
        this.companies = res.data || [];
        this.summary = res.summary || {};
        this.loading = false;
      },
      () => { this.loading = false; }
    );
  }

  loadSettlement() {
    const params: any = {};
    if (this.typeFilter) params.type = this.typeFilter;
    if (this.dateFrom) params.date_from = this.dateFrom;
    if (this.dateTo) params.date_to = this.dateTo;

    this.orderService.getSettlementSummary(params).subscribe((res: any) => {
      this.settlementData = res.data || [];
      this.settlementTotals = res.totals || {};
    });
  }

  openStatement(company: any) {
    this.selectedCompany = company;
    this.activeTab = 'statement';
    this.loadStatement();
  }

  loadStatement(page = 1) {
    if (!this.selectedCompany) return;

    const params: any = { page, per_page: 30 };
    if (this.dateFrom) params.date_from = this.dateFrom;
    if (this.dateTo) params.date_to = this.dateTo;
    if (this.statusFilter) params.status = this.statusFilter;
    if (this.isDoneFilter !== '') params.is_done = this.isDoneFilter;

    this.orderService.getShippingCompanyStatement(this.selectedCompany.id, params).subscribe((res: any) => {
      this.statement = res.company;
      this.statementAggregates = res.aggregates;
      this.statementDetails = res.details;
    });
  }

  applyFilters() {
    this.loadSummary();
    this.loadSettlement();
    if (this.selectedCompany) this.loadStatement();
  }

  formatDate(date: string): string {
    if (!date) return '';
    return this.datePipe.transform(date, 'yyyy-MM-dd') || date;
  }

  onDateFrom(event: any) {
    const d = new Date(event);
    this.dateFrom = this.datePipe.transform(d, 'yyyy-MM-dd') || '';
  }

  onDateTo(event: any) {
    const d = new Date(event);
    this.dateTo = this.datePipe.transform(d, 'yyyy-MM-dd') || '';
  }

  backToSummary() {
    this.activeTab = 'summary';
    this.selectedCompany = null;
  }

  /** روابط قبض/دفع من التقرير → شاشة السندات مع تعبئة مبدئية */
  cashQueryReceipt(
    company: { id: number; name?: string; pending_order_ids?: number[] },
    suggestedAmount?: number | null,
    settledOrderIdsOverride?: number[]
  ) {
    const q: Record<string, string | number> = {
      party: 'shipping_company',
      type: 'receipt',
      shipping_company_id: company.id,
      note: `قبض — ${company.name || company.id} (من تقرير ذمم الشحن)`,
    };
    const amt = this.normalizeSuggestedAmount(suggestedAmount);
    if (amt != null) {
      q['amount'] = amt;
    }
    const ids =
      settledOrderIdsOverride?.length
        ? settledOrderIdsOverride
        : (Array.isArray(company.pending_order_ids) && company.pending_order_ids.length
            ? company.pending_order_ids
            : undefined);
    if (ids?.length) {
      q['settled_order_ids'] = ids.join(',');
    }
    return q;
  }

  cashQueryPayment(company: { id: number; name?: string }, suggestedAmount?: number | null) {
    const q: Record<string, string | number> = {
      shipping_company_id: company.id,
      note: `صرف — ${company.name || company.id} (من تقرير ذمم الشحن)`,
    };
    const amt = this.normalizeSuggestedAmount(suggestedAmount);
    if (amt != null) {
      q['amount'] = amt;
    }
    return q;
  }

  pendingAmountFromRow(c: any): number | null {
    const v = parseFloat(c?.total_pending_amount);
    return !isNaN(v) && v > 0.009 ? Math.round(v * 100) / 100 : null;
  }

  pendingAmountFromSettlementRow(s: any): number | null {
    const v = parseFloat(s?.total_outstanding);
    return !isNaN(v) && v > 0.009 ? Math.round(v * 100) / 100 : null;
  }

  suggestedAmountFromStatementAggregates(): number | null {
    const v = parseFloat(this.statementAggregates?.outstanding);
    return !isNaN(v) && v > 0.009 ? Math.round(v * 100) / 100 : null;
  }

  private normalizeSuggestedAmount(n?: number | null): number | null {
    if (n == null || isNaN(Number(n)) || Number(n) <= 0) {
      return null;
    }
    return Math.round(Number(n) * 100) / 100;
  }
}
