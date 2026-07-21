import { Component, HostListener, OnInit } from '@angular/core';
import { CollectionCompanyService } from '../services/collection-company.service';
import { PaymentSourcesService, PaymentSourceItem } from '../../accounting/services/payment-sources.service';
import { ToastService } from '../../shared/toast/toast.service';

interface CollectibleRow {
  order_id?: number;
  id?: number;
  collectible?: boolean;
  collectible_amount?: number;
  collection_receivable_amount?: number;
  amount?: number;
  product_value?: number;
  shipping_value?: number;
  is_done?: number | boolean;
  status?: string;
  order_status?: string;
  shipment_numbers?: string[];
  order?: {
    customer_name?: string;
    order_shipment_number?: Array<{ shipment_number?: string }>;
  };
}

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
  collectibleOptions: CollectibleOption[] = [];
  activeTab: 'summary' | 'statement' = 'summary';
  dateFrom = '';
  dateTo = '';
  pendingOnly = true;
  loading = false;
  statementSource = '';

  orderSearchTerm = '';
  orderPickerOpen = false;

  /** order_id → collectible amount */
  selectedOrders = new Map<number, number>();

  // مصادر النقد لجانب «قبض قيمة البضاعة» في المقاصّة
  cashSources: PaymentSourceItem[] = [];
  nettingCashAccountId: number | null = null;
  nettingBusy = false;

  constructor(
    private api: CollectionCompanyService,
    private paymentSources: PaymentSourcesService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.load();
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
    this.orderSearchTerm = '';
    this.orderPickerOpen = false;
    this.collectibleOptions = [];
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

  optionShipmentText(o: CollectibleOption): string {
    return (o.shipment_numbers || []).filter(Boolean).join(' / ');
  }

  shipmentNumbersText(d: CollectibleRow): string {
    if (Array.isArray(d?.shipment_numbers) && d.shipment_numbers.length) {
      return d.shipment_numbers.filter(Boolean).join(' / ') || '—';
    }
    const list = d?.order?.order_shipment_number;
    if (!Array.isArray(list) || !list.length) return '—';
    const nums = list.map((n) => n?.shipment_number).filter(Boolean) as string[];
    return nums.length ? nums.join(' / ') : '—';
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

  back(): void {
    this.activeTab = 'summary';
    this.selected = null;
    this.collectibleOptions = [];
    this.orderSearchTerm = '';
    this.orderPickerOpen = false;
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
    this.selectedOrders = new Map(this.selectedOrders);
  }

  selectAllPendingOnPage(): void {
    const rows: CollectibleRow[] = this.details?.data || [];
    for (const row of rows) {
      if (!this.isCollectibleRow(row)) continue;
      const oid = this.rowOrderId(row);
      if (oid) this.selectedOrders.set(oid, this.rowCollectibleAmount(row));
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
    if (!this.selected || this.selectedOrderIds.length === 0) {
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
    this.api
      .settleWithShipping(this.selected.id, {
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
          this.load();
          this.loadStatement();
        },
        error: (err) => {
          this.nettingBusy = false;
          this.toast.error(err?.error?.message || 'تعذر إتمام التسوية');
        },
      });
  }

  cashReceiptQuery(c: any, amount?: number, orderIds?: number[], shippingAmount?: number): Record<string, string | number> {
    const q: Record<string, string | number> = {
      party: 'collection_company',
      type: 'receipt',
      collection_company_id: c.id,
      amount: amount ?? this.pendingAmount(c),
      note: `قبض — ${c.name || c.id} (من ذمم شركات التحصيل)`,
    };
    if (shippingAmount != null && shippingAmount > 0) {
      q['shipping_amount'] = Math.round(shippingAmount * 100) / 100;
    }
    const ids = orderIds?.length ? orderIds : c.pending_order_ids;
    if (ids?.length) {
      q['settled_order_ids'] = ids.join(',');
    }
    return q;
  }

  cashReceiptQuerySelected(): Record<string, string | number> | null {
    if (!this.selected || this.selectedOrderIds.length === 0) return null;
    // مبلغ الطلب (بضاعة) نقداً + مبلغ الشحن كمصروف يُخصم من مديونيتهم.
    return this.cashReceiptQuery(
      this.selected,
      this.selectedProductTotal,
      this.selectedOrderIds,
      this.selectedShippingTotal
    );
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
