import { Component, OnInit, ViewChild } from '@angular/core';
import { Location } from '@angular/common';
import { PageEvent } from '@angular/material/paginator';
import { MatPaginator } from '@angular/material/paginator';
import { AuthService } from 'src/app/auth/auth.service';
import { ReportNewOrderService } from '../../services/report-New-order.service';

@Component({
  selector: 'report-new-order',
  templateUrl: './report-new-order.component.html',
  styleUrls: [
    '../../../shared/styles/report-page-shell.css',
    './report-new-order.component.css',
  ],
})
export class ReportNewOrdersComponent implements OnInit {
  @ViewChild(MatPaginator) paginator?: MatPaginator;

  /** الصفوف الكاملة من الـ API */
  rawData: any[] = [];
  /** الصفوف المعروضة للصفحة الحالية */
  data: any[] = [];
  length = 0;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15, 50, 100];
  user: any;
  searchTerm = '';
  loading = false;
  showFilters = true;
  expanded = new Set<string>();

  constructor(
    private reportService: ReportNewOrderService,
    private authService: AuthService,
    private location: Location,
  ) {}

  ngOnInit() {
    this.user = this.authService.getUser();
    this.loadAll();
  }

  loadAll() {
    this.loading = true;
    this.reportService.getAll().subscribe({
      next: (res: any) => {
        // Laravel ApiResponseTrait: { success, data: [...] }
        const payload = res?.data;
        this.rawData = Array.isArray(payload)
          ? payload
          : Array.isArray(res)
            ? res
            : [];
        this.page = 0;
        this.paginator?.firstPage();
        this.applyFilterAndPage();
        this.loading = false;
      },
      error: (err) => {
        console.error('Error fetching all orders', err);
        this.rawData = [];
        this.applyFilterAndPage();
        this.loading = false;
      },
    });
  }

  getFilteredRows(): any[] {
    const q = this.searchTerm.trim().toLowerCase();
    if (!q) {
      return [...this.rawData];
    }
    return this.rawData.filter((row) => {
      const hay = [
        row?.order_id,
        row?.customer_name,
        row?.entry_batch_code,
      ]
        .map((v) => String(v ?? '').toLowerCase())
        .join(' ');
      return hay.includes(q);
    });
  }

  private num(v: unknown): number {
    if (v == null || v === '') {
      return 0;
    }
    const n = typeof v === 'number' ? v : parseFloat(String(v).replace(/,/g, ''));
    return Number.isFinite(n) ? n : 0;
  }

  get summaryCount(): number {
    return this.getFilteredRows().length;
  }

  get summaryDebit(): number {
    return this.getFilteredRows().reduce((s, r) => s + this.num(r?.total_debit), 0);
  }

  get summaryCredit(): number {
    return this.getFilteredRows().reduce((s, r) => s + this.num(r?.total_credit), 0);
  }

  get summaryNet(): number {
    return this.summaryDebit - this.summaryCredit;
  }

  applyFilterAndPage() {
    const filtered = this.getFilteredRows();
    this.length = filtered.length;
    let start = this.page * this.pageSize;
    if (this.length > 0 && start >= this.length) {
      this.page = 0;
      queueMicrotask(() => this.paginator?.firstPage());
      start = 0;
    }
    this.data = filtered.slice(start, start + this.pageSize);
  }

  onSearchInput() {
    this.page = 0;
    this.paginator?.firstPage();
    this.applyFilterAndPage();
  }

  goBack() {
    this.location.back();
  }

  onPageChange(event: PageEvent) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.applyFilterAndPage();
  }

  rowDisplayIndex(i: number): number {
    return this.page * this.pageSize + i + 1;
  }

  toggleFilters(): void {
    this.showFilters = !this.showFilters;
  }

  /**
   * مفتاح فريد لكل صف: الـ API يجمّع حسب (order_id + entry_batch_code) فيمكن تكرار order_id.
   */
  rowKey(item: any): string {
    const oid = item?.order_id ?? '';
    const batch = item?.entry_batch_code ?? '';
    const cust = item?.customer_name ?? '';
    return `${oid}\u0001${batch}\u0001${cust}`;
  }

  toggleExpand(item: any): void {
    const k = this.rowKey(item);
    if (k === '\u0001\u0001') {
      return;
    }
    if (this.expanded.has(k)) {
      this.expanded.delete(k);
    } else {
      this.expanded.add(k);
    }
  }

  isExpanded(item: any): boolean {
    return this.expanded.has(this.rowKey(item));
  }

  trackByRowKey(_index: number, item: any): string {
    return this.rowKey(item);
  }
}
