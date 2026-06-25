import { Component, OnInit, OnDestroy } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { VoucherService } from '../../../accounting/services/voucher.service';
import { ExpenseService } from '../../services/expense.service';
import { RbacService } from '../../../core/rbac/rbac.service';
import { ToastService } from '../../../shared/toast/toast.service';
import { CashVoucherEditDialogComponent } from '../cash-voucher-edit-dialog/cash-voucher-edit-dialog.component';
import { formatVoucherClientPartyName } from '../cash-client-selection';
import { Subject, forkJoin, of } from 'rxjs';
import { debounceTime, distinctUntilChanged, takeUntil } from 'rxjs/operators';

type PartyFilter = 'all' | 'client' | 'supplier' | 'shipping_company' | 'collection_company' | 'expense';
type TypeFilter = 'all' | 'receipt' | 'payment';
type RowSource = 'voucher' | 'expense';

export interface UnifiedTransactionRow {
  id: number;
  source: RowSource;
  date: string;
  type: 'receipt' | 'payment';
  partyName: string;
  partyType: PartyFilter | 'client' | 'supplier' | 'shipping_company' | 'collection_company' | 'expense';
  amount: number;
  accountName: string;
  reference: string;
  notes: string;
  userName: string;
  expenseDetailUrl?: string;
}

interface FilterState {
  partyFilter: PartyFilter;
  typeFilter: TypeFilter;
  search: string;
  dateFrom: string;
  dateTo: string;
  page: number;
  perPage: number;
}

@Component({
  selector: 'app-previous-cash-payments',
  templateUrl: './previous-cash-payments.component.html',
  styleUrls: ['./previous-cash-payments.component.css'],
})
export class PreviousCashPaymentsComponent implements OnInit, OnDestroy {
  rows: UnifiedTransactionRow[] = [];
  loading = false;
  totalRecords = 0;
  totalPages = 0;
  currentPage = 1;

  filters: FilterState = {
    partyFilter: 'all',
    typeFilter: 'all',
    search: '',
    dateFrom: '',
    dateTo: '',
    page: 1,
    perPage: 25,
  };

  partyFilters: { key: PartyFilter; label: string; icon: string }[] = [
    { key: 'all', label: 'الكل', icon: 'fas fa-layer-group' },
    { key: 'client', label: 'عملاء', icon: 'fas fa-users' },
    { key: 'supplier', label: 'موردين', icon: 'fas fa-people-carry' },
    { key: 'shipping_company', label: 'شركات شحن', icon: 'fas fa-shipping-fast' },
    { key: 'collection_company', label: 'شركات تحصيل', icon: 'fas fa-hand-holding-usd' },
    { key: 'expense', label: 'مصروفات', icon: 'fas fa-file-invoice-dollar' },
  ];

  typeFilters: { key: TypeFilter; label: string }[] = [
    { key: 'all', label: 'الكل' },
    { key: 'receipt', label: 'قبض' },
    { key: 'payment', label: 'صرف' },
  ];

  private searchSubject = new Subject<string>();
  private destroy$ = new Subject<void>();
  private mergedCache: UnifiedTransactionRow[] = [];
  private lastMergeKey = '';

  constructor(
    private voucherService: VoucherService,
    private expenseService: ExpenseService,
    private rbac: RbacService,
    private toast: ToastService,
    private dialog: MatDialog,
  ) {}

  ngOnInit(): void {
    this.searchSubject
      .pipe(debounceTime(400), distinctUntilChanged(), takeUntil(this.destroy$))
      .subscribe((term) => {
        this.filters.search = term;
        this.filters.page = 1;
        this.loadData();
      });

    this.loadData();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  get showTypeFilter(): boolean {
    return this.filters.partyFilter !== 'expense';
  }

  get canEditCash(): boolean {
    return this.rbac.canAny(['finance.edit', 'system.rbac']);
  }

  editVoucher(row: UnifiedTransactionRow): void {
    if (row.source !== 'voucher' || !this.canEditCash) {
      return;
    }

    const ref = this.dialog.open(CashVoucherEditDialogComponent, {
      width: '640px',
      maxWidth: '95vw',
      disableClose: true,
      data: { voucherId: row.id },
    });

    ref.afterClosed().subscribe((saved) => {
      if (!saved) {
        return;
      }
      this.toast.success('تم تحديث السند بنجاح');
      this.mergedCache = [];
      this.lastMergeKey = '';
      this.loadData();
    });
  }

  expenseEditUrl(row: UnifiedTransactionRow): string {
    return `/dashboard/financial/editexpense/${row.id}`;
  }

  onSearchInput(term: string): void {
    this.searchSubject.next(term);
  }

  setPartyFilter(key: PartyFilter): void {
    if (this.filters.partyFilter === key) return;
    this.filters.partyFilter = key;
    if (key === 'expense' && this.filters.typeFilter === 'receipt') {
      this.filters.typeFilter = 'all';
    }
    this.filters.page = 1;
    this.loadData();
  }

  setTypeFilter(key: TypeFilter): void {
    if (this.filters.typeFilter === key) return;
    this.filters.typeFilter = key;
    this.filters.page = 1;
    this.loadData();
  }

  onDateChange(): void {
    this.filters.page = 1;
    this.loadData();
  }

  clearDates(): void {
    this.filters.dateFrom = '';
    this.filters.dateTo = '';
    this.filters.page = 1;
    this.loadData();
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages || page === this.currentPage) return;
    this.filters.page = page;
    this.loadData();
  }

  getPartyTypeLabel(partyType: string): string {
    switch (partyType) {
      case 'client': return 'عميل';
      case 'supplier': return 'مورد';
      case 'shipping_company': return 'شركة شحن';
      case 'collection_company': return 'شركة تحصيل';
      case 'expense': return 'مصروف';
      default: return '—';
    }
  }

  getPartyBadgeClass(partyType: string): string {
    switch (partyType) {
      case 'client': return 'badge--client';
      case 'supplier': return 'badge--supplier';
      case 'shipping_company': return 'badge--shipping';
      case 'collection_company': return 'badge--collection';
      case 'expense': return 'badge--expense';
      default: return '';
    }
  }

  getVoucherTypeLabel(type: string): string {
    return type === 'receipt' ? 'قبض' : 'صرف';
  }

  getVoucherTypeClass(type: string): string {
    return type === 'receipt' ? 'badge--receipt' : 'badge--payment';
  }

  get paginationPages(): number[] {
    const pages: number[] = [];
    const total = this.totalPages;
    const current = this.currentPage;
    const delta = 2;

    let start = Math.max(1, current - delta);
    let end = Math.min(total, current + delta);

    if (current - delta < 1) end = Math.min(total, end + (delta - current + 1));
    if (current + delta > total) start = Math.max(1, start - (current + delta - total));

    for (let i = start; i <= end; i++) pages.push(i);
    return pages;
  }

  private loadData(): void {
    this.loading = true;

    if (this.filters.partyFilter === 'expense') {
      this.loadExpensesOnly();
      return;
    }

    if (this.filters.partyFilter === 'all') {
      this.loadMerged();
      return;
    }

    this.loadVouchersOnly();
  }

  private loadVouchersOnly(): void {
    const params = this.buildVoucherParams();

    this.voucherService.getVouchers(params).subscribe({
      next: (res) => {
        this.rows = (res.data || []).map((v: any) => this.normalizeVoucher(v));
        this.totalRecords = res.total || 0;
        this.totalPages = res.last_page || 1;
        this.currentPage = res.current_page || 1;
        this.loading = false;
      },
      error: () => {
        this.rows = [];
        this.loading = false;
      },
    });
  }

  private loadExpensesOnly(): void {
    const hasSearch = !!this.filters.search?.trim();

    if (hasSearch) {
      const params = this.buildExpenseParams();
      this.expenseService.search(500, 1, params).subscribe({
        next: (res: any) => {
          const items = (res.data || [])
            .filter((e: any) => e?.status !== 1)
            .map((e: any) => this.normalizeExpense(e));
          const filtered = this.applyClientFilters(items);
          this.paginateClientSide(filtered);
          this.loading = false;
        },
        error: () => {
          this.rows = [];
          this.loading = false;
        },
      });
      return;
    }

    const params = this.buildExpenseParams();
    this.expenseService.search(this.filters.perPage, this.filters.page, params).subscribe({
      next: (res: any) => {
        const items = (res.data || []).filter((e: any) => e?.status !== 1);
        this.rows = items.map((e: any) => this.normalizeExpense(e));
        this.totalRecords = res.total || 0;
        this.totalPages = res.last_page || 1;
        this.currentPage = res.current_page || 1;
        this.loading = false;
      },
      error: () => {
        this.rows = [];
        this.loading = false;
      },
    });
  }

  private loadMerged(): void {
    const mergeKey = this.getMergeKey();
    if (this.mergedCache.length && this.lastMergeKey === mergeKey) {
      this.paginateClientSide(this.mergedCache);
      this.loading = false;
      return;
    }

    const voucherParams = { ...this.buildVoucherParams(), page: 1, per_page: 500 };
    const expenseParams = this.buildExpenseParams();
    const includeExpenses = this.filters.typeFilter !== 'receipt';

    const voucher$ = this.voucherService.getVouchers(voucherParams);
    const expense$ = includeExpenses
      ? this.expenseService.search(500, 1, expenseParams)
      : of({ data: [], total: 0 });

    forkJoin({ vouchers: voucher$, expenses: expense$ }).subscribe({
      next: ({ vouchers, expenses }: { vouchers: any; expenses: any }) => {
        let merged: UnifiedTransactionRow[] = [];

        (vouchers.data || []).forEach((v: any) => {
          merged.push(this.normalizeVoucher(v));
        });

        if (includeExpenses) {
          (expenses.data || [])
            .filter((e: any) => e?.status !== 1)
            .forEach((e: any) => merged.push(this.normalizeExpense(e)));
        }

        merged = this.applyClientFilters(merged);
        merged.sort((a, b) => b.date.localeCompare(a.date));

        this.mergedCache = merged;
        this.lastMergeKey = mergeKey;
        this.paginateClientSide(merged);
        this.loading = false;
      },
      error: () => {
        this.rows = [];
        this.mergedCache = [];
        this.lastMergeKey = '';
        this.loading = false;
      },
    });
  }

  private getMergeKey(): string {
    return JSON.stringify({
      type: this.filters.typeFilter,
      search: this.filters.search,
      dateFrom: this.filters.dateFrom,
      dateTo: this.filters.dateTo,
    });
  }

  private paginateClientSide(items: UnifiedTransactionRow[]): void {
    this.totalRecords = items.length;
    this.totalPages = Math.max(1, Math.ceil(items.length / this.filters.perPage));
    this.currentPage = Math.min(this.filters.page, this.totalPages);
    const start = (this.currentPage - 1) * this.filters.perPage;
    this.rows = items.slice(start, start + this.filters.perPage);
  }

  private buildVoucherParams(): Record<string, any> {
    const params: Record<string, any> = {
      page: this.filters.page,
      per_page: this.filters.perPage,
    };

    if (this.filters.partyFilter !== 'all') {
      params['voucher_type'] = this.filters.partyFilter;
    }
    if (this.filters.typeFilter !== 'all') {
      params['type'] = this.filters.typeFilter;
    }
    if (this.filters.search?.trim()) {
      params['search'] = this.filters.search.trim();
    }
    if (this.filters.dateFrom && this.filters.dateTo) {
      params['date_from'] = this.filters.dateFrom;
      params['date_to'] = this.filters.dateTo;
    }

    return params;
  }

  private buildExpenseParams(): Record<string, any> {
    const params: Record<string, any> = {};
    if (this.filters.dateFrom && this.filters.dateTo) {
      params['date_from'] = this.filters.dateFrom;
      params['date_to'] = this.filters.dateTo;
    }
    return params;
  }

  private applyClientFilters(rows: UnifiedTransactionRow[]): UnifiedTransactionRow[] {
    let result = [...rows];

    if (this.filters.typeFilter === 'receipt') {
      result = result.filter((r) => r.type === 'receipt');
    } else if (this.filters.typeFilter === 'payment') {
      result = result.filter((r) => r.type === 'payment');
    }

    const term = this.filters.search?.trim().toLowerCase();
    if (term) {
      result = result.filter((r) =>
        [r.partyName, r.reference, r.notes, r.accountName, r.userName, String(r.id)]
          .some((f) => (f || '').toLowerCase().includes(term))
      );
    }

    return result;
  }

  private normalizeVoucher(v: any): UnifiedTransactionRow {
    return {
      id: v.id,
      source: 'voucher',
      date: this.formatDate(v.date),
      type: v.type,
      partyName: this.voucherPartyName(v),
      partyType: v.voucher_type,
      amount: Number(v.amount) || 0,
      accountName: v.account?.name || '—',
      reference: v.reference_number || '—',
      notes: v.notes || '—',
      userName: v.user?.name || '—',
    };
  }

  private normalizeExpense(e: any): UnifiedTransactionRow {
    const date = e.created_at ? this.formatDate(e.created_at) : '—';

    return {
      id: e.id,
      source: 'expense',
      date,
      type: 'payment',
      partyName: this.expensePartyName(e),
      partyType: 'expense',
      amount: Number(e.amount) || 0,
      accountName: this.expenseAccountName(e),
      reference: e.expense_number || e.ref || '—',
      notes: e.note || e.expens_statement || '—',
      userName: '—',
      expenseDetailUrl: `/dashboard/financial/expense_details/${e.id}`,
    };
  }

  private voucherPartyName(v: any): string {
    if (v.voucher_type === 'client') return formatVoucherClientPartyName(v);
    if (v.voucher_type === 'supplier') return v.supplier?.supplier_name || v.client_or_supplier_name || '—';
    if (v.voucher_type === 'shipping_company') return v.shipping_company?.name || v.client_or_supplier_name || '—';
    if (v.voucher_type === 'collection_company') return v.collection_company?.name || v.client_or_supplier_name || '—';
    return v.client_or_supplier_name || '—';
  }

  private expensePartyName(e: any): string {
    if (Array.isArray(e?.lines) && e.lines.length > 1) {
      return `تقسيم (${e.lines.length} بنود)`;
    }
    return e?.expens_statement || e?.kind?.expense_kind || e?.expense_type || 'مصروف';
  }

  private expenseAccountName(e: any): string {
    const pt = e?.payment_type || (e?.safe_id ? 'safe' : e?.service_account_id ? 'service_account' : 'bank');
    if (pt === 'safe') return e?.safe?.name || 'خزينة';
    if (pt === 'bank') return e?.bank?.name || 'بنك';
    return e?.service_account?.name || 'حساب خدمي';
  }

  private formatDate(value: string): string {
    if (!value) return '—';
    const d = new Date(value);
    if (isNaN(d.getTime())) return String(value).slice(0, 10);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }
}
