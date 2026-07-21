import { Component, HostListener, OnInit } from '@angular/core';
import { OrderService } from '../services/order.service';
import { DatePipe } from '@angular/common';
import { PaymentSourcesService, PaymentSourceItem } from '../../accounting/services/payment-sources.service';
import { ToastService } from '../../shared/toast/toast.service';

interface CollectibleOption {
  order_id: number;
  amount: number;
  product_value?: number;
  shipping_value?: number;
  customer_name?: string;
  shipment_numbers?: string[];
  status?: string;
}

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
  purchaseFreight: any[] = [];
  settlementData: any[] = [];
  settlementTotals: any = {};
  collectibleOptions: CollectibleOption[] = [];

  activeTab: 'summary' | 'statement' | 'settlement' = 'summary';
  typeFilter: string = '';
  dateFrom: string = '';
  dateTo: string = '';
  statusFilter: string = '';
  isDoneFilter: string = '';

  orderSearchTerm = '';
  orderPickerOpen = false;

  loading = false;

  /** order_id → collectible amount */
  selectedOrders = new Map<number, number>();

  cashSources: PaymentSourceItem[] = [];
  nettingCashAccountId: number | null = null;
  nettingBusy = false;

  constructor(
    private orderService: OrderService,
    private datePipe: DatePipe,
    private paymentSources: PaymentSourcesService,
    private toast: ToastService
  ) {}

  ngOnInit() {
    this.loadSummary();
    this.loadSettlement();
    this.paymentSources.getPaymentSources().subscribe({
      next: (res) => {
        this.cashSources = [
          ...(res.safes || []),
          ...(res.banks || []),
          ...(res.service_accounts || []),
        ].filter((s) => !!s.account_id);
      },
      error: () => {},
    });
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent): void {
    const target = event.target as HTMLElement | null;
    if (!target?.closest?.('.order-multi-select')) {
      this.orderPickerOpen = false;
    }
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
    this.isDoneFilter = '0';
    this.clearSelection();
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
      this.purchaseFreight = res.purchase_freight || [];
      this.collectibleOptions = (res.collectible_options || []).map((o: any) => ({
        order_id: Number(o.order_id),
        amount: Math.round(Number(o.amount || 0) * 100) / 100,
        product_value: Math.round(Number(o.product_value || 0) * 100) / 100,
        shipping_value: Math.round(Number(o.shipping_value || 0) * 100) / 100,
        customer_name: o.customer_name || '',
        shipment_numbers: Array.isArray(o.shipment_numbers) ? o.shipment_numbers : [],
        status: o.status,
      }));
    });
  }

  get filteredCollectibleOptions(): CollectibleOption[] {
    const term = (this.orderSearchTerm || '').trim().toLowerCase();
    if (!term) return this.collectibleOptions;
    return this.collectibleOptions.filter((o) => {
      const orderId = String(o.order_id || '');
      const customer = (o.customer_name || '').toLowerCase();
      const shipments = (o.shipment_numbers || []).join(' ').toLowerCase();
      return orderId.includes(term) || customer.includes(term) || shipments.includes(term);
    });
  }

  shipmentNumbersText(d: any): string {
    const list = d?.order?.order_shipment_number;
    if (!Array.isArray(list) || !list.length) return '—';
    const nums = list.map((n: any) => n?.shipment_number).filter(Boolean);
    return nums.length ? nums.join(' / ') : '—';
  }

  optionShipmentText(o: CollectibleOption): string {
    return (o.shipment_numbers || []).filter(Boolean).join(' / ');
  }

  isSearchOptionSelected(o: CollectibleOption): boolean {
    return this.selectedOrders.has(Number(o?.order_id));
  }

  toggleOrderFromSearch(o: CollectibleOption): void {
    const oid = Number(o?.order_id);
    if (!oid) return;
    if (this.selectedOrders.has(oid)) {
      this.selectedOrders.delete(oid);
    } else {
      this.selectedOrders.set(oid, Math.round(Number(o.amount || 0) * 100) / 100);
    }
    this.selectedOrders = new Map(this.selectedOrders);
  }

  selectFilteredCollectible(): void {
    for (const o of this.filteredCollectibleOptions) {
      const oid = Number(o.order_id);
      if (oid > 0) this.selectedOrders.set(oid, Math.round(Number(o.amount || 0) * 100) / 100);
    }
    this.selectedOrders = new Map(this.selectedOrders);
  }

  clearOrderSearch(): void {
    this.orderSearchTerm = '';
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
    this.collectibleOptions = [];
    this.orderSearchTerm = '';
    this.orderPickerOpen = false;
    this.clearSelection();
  }

  rowCollectibleAmount(d: any): number {
    if (d?.collectible_amount != null && !isNaN(Number(d.collectible_amount))) {
      return Math.round(Number(d.collectible_amount) * 100) / 100;
    }
    const v = parseFloat(String(d?.amount ?? 0));
    return !isNaN(v) ? Math.round(Math.abs(v) * 100) / 100 : 0;
  }

  isCollectibleRow(d: any): boolean {
    if (d?.collectible === true) return true;
    if (d?.collectible === false) return false;
    // المديونية تُرمى عند «تم التسليم» فقط.
    return !d?.is_done && d?.status === 'تم التسليم';
  }

  isRowSelected(d: any): boolean {
    const oid = Number(d?.order_id);
    return oid > 0 && this.selectedOrders.has(oid);
  }

  toggleRow(d: any): void {
    const oid = Number(d?.order_id);
    if (!oid || !this.isCollectibleRow(d)) return;
    if (this.selectedOrders.has(oid)) {
      this.selectedOrders.delete(oid);
    } else {
      this.selectedOrders.set(oid, this.rowCollectibleAmount(d));
    }
    this.selectedOrders = new Map(this.selectedOrders);
  }

  selectAllPendingOnPage(): void {
    const rows = this.statementDetails?.data || [];
    for (const d of rows) {
      if (!this.isCollectibleRow(d)) continue;
      const oid = Number(d.order_id);
      if (oid > 0) this.selectedOrders.set(oid, this.rowCollectibleAmount(d));
    }
    this.selectedOrders = new Map(this.selectedOrders);
  }

  clearSelection(): void {
    this.selectedOrders = new Map();
  }

  get selectedOrderIds(): number[] {
    return Array.from(this.selectedOrders.keys());
  }

  get selectedTotal(): number {
    let sum = 0;
    this.selectedOrders.forEach((amt) => (sum += amt));
    return Math.round(sum * 100) / 100;
  }

  private get selectedOptionRows(): CollectibleOption[] {
    return this.collectibleOptions.filter((o) => this.selectedOrders.has(Number(o.order_id)));
  }

  get selectedProductTotal(): number {
    const sum = this.selectedOptionRows.reduce((acc, o) => acc + Number(o.product_value || 0), 0);
    return Math.round(sum * 100) / 100;
  }

  get selectedShippingTotal(): number {
    const sum = this.selectedOptionRows.reduce((acc, o) => acc + Number(o.shipping_value || 0), 0);
    return Math.round(sum * 100) / 100;
  }

  settleWithShippingNetting(): void {
    if (!this.selectedCompany || this.selectedOrderIds.length === 0) {
      this.toast.warning('اختر طلبات أولاً');
      return;
    }
    if (!this.nettingCashAccountId) {
      this.toast.warning('اختر الخزنة/البنك لقبض قيمة البضاعة');
      return;
    }
    if (this.nettingBusy) return;

    this.nettingBusy = true;
    const date = new Date().toISOString().split('T')[0];
    this.orderService
      .settleShippingWithShipping(this.selectedCompany.id, {
        order_ids: this.selectedOrderIds,
        cash_account_id: this.nettingCashAccountId,
        date,
        mode: 'netting',
      })
      .subscribe({
        next: (res: any) => {
          this.nettingBusy = false;
          this.toast.success(res?.message || 'تمت التسوية بنجاح');
          this.clearSelection();
          this.loadSummary();
          this.loadSettlement();
          this.loadStatement();
        },
        error: (err) => {
          this.nettingBusy = false;
          this.toast.error(err?.error?.message || 'تعذر إتمام التسوية');
        },
      });
  }

  cashQueryReceiptSelected(): Record<string, string | number> | null {
    if (!this.selectedCompany || this.selectedOrderIds.length === 0) return null;
    // مبلغ الطلب (بضاعة) نقداً + مبلغ الشحن كمصروف يُخصم من مديونيتهم.
    return this.cashQueryReceipt(
      this.selectedCompany,
      this.selectedProductTotal,
      this.selectedOrderIds,
      this.selectedShippingTotal
    );
  }

  /** روابط قبض/دفع من التقرير → شاشة السندات مع تعبئة مبدئية */
  cashQueryReceipt(
    company: { id: number; name?: string; pending_order_ids?: number[] },
    suggestedAmount?: number | null,
    settledOrderIdsOverride?: number[],
    shippingAmount?: number | null
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
    const shipAmt = this.normalizeSuggestedAmount(shippingAmount);
    if (shipAmt != null) {
      q['shipping_amount'] = shipAmt;
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
      party: 'shipping_company',
      type: 'payment',
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
    // المستحق للتحصيل = المُسلَّم المعلق فقط (المديونية تُرمى عند التسليم).
    const v = parseFloat(c?.delivered_pending_amount);
    return !isNaN(v) && v > 0.009 ? Math.round(v * 100) / 100 : null;
  }

  pendingAmountFromSettlementRow(s: any): number | null {
    const v = parseFloat(s?.delivered_amount);
    return !isNaN(v) && v > 0.009 ? Math.round(v * 100) / 100 : null;
  }

  suggestedAmountFromStatementAggregates(): number | null {
    const v = parseFloat(this.statementAggregates?.delivered_pending);
    return !isNaN(v) && v > 0.009 ? Math.round(v * 100) / 100 : null;
  }

  private normalizeSuggestedAmount(n?: number | null): number | null {
    if (n == null || isNaN(Number(n)) || Number(n) <= 0) {
      return null;
    }
    return Math.round(Number(n) * 100) / 100;
  }
}
