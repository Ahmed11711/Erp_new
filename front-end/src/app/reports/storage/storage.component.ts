import { Component, OnDestroy, OnInit } from '@angular/core';
import { CategoryService } from 'src/app/categories/services/category.service';
import { environment } from 'src/env/env';

type WarehouseSortField =
  | 'quantity'
  | 'total_value'
  | 'sell_value'
  | 'category_name'
  | 'period_in_qty'
  | 'period_out_qty'
  | 'period_net_qty';

interface WarehouseReportTotals {
  items_count: number;
  total_quantity: number;
  total_value: number;
  total_sell_value: number;
  period_in_total: number;
  period_out_total: number;
  is_finished_warehouse: boolean;
  warehouse: string;
  date_from: string | null;
  date_to: string | null;
}

@Component({
  selector: 'app-storage',
  templateUrl: './storage.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './storage.component.css']
})
export class StorageComponent implements OnInit, OnDestroy {
  data: any[] = [];
  dateFrom: string | null = null;
  dateTo: string | null = null;
  warehouse = 'مخزن منتج تام';
  searchTerm = '';
  loading = false;
  loadError: string | null = null;
  length = 0;
  page = 0;
  pageSize = 15;
  pageSizeOptions = [15, 50, 100];
  showFilters = true;
  sortField: WarehouseSortField = 'total_value';
  totals: WarehouseReportTotals = {
    items_count: 0,
    total_quantity: 0,
    total_value: 0,
    total_sell_value: 0,
    period_in_total: 0,
    period_out_total: 0,
    is_finished_warehouse: true,
    warehouse: '',
    date_from: null,
    date_to: null
  };

  imgUrl = environment.imgUrl;
  expanded = new Set<number>();
  imageFailed = new Set<number>();

  private searchDebounce?: ReturnType<typeof setTimeout>;

  warehouses = [
    { value: 'مخزن مواد خام', label: 'مخزن المواد الخام' },
    { value: 'مخزن منتج تحت التشغيل', label: 'مخزن منتج التشغيل' },
    { value: 'مخزن منتج تام', label: 'مخزن المنتج التام' },
    { value: 'مخزن صيانة', label: 'مخزن الصيانة' },
    { value: 'مخزن تالف', label: 'مخزن التالف' }
  ];

  constructor(private categoryService: CategoryService) {}

  ngOnInit(): void {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    this.dateFrom = `${y}-${m}-01`;
    this.dateTo = `${y}-${m}-${d}`;
    this.load();
  }

  ngOnDestroy(): void {
    clearTimeout(this.searchDebounce);
  }

  get isFinishedWarehouse(): boolean {
    return this.warehouse === 'مخزن منتج تام';
  }

  get primaryValueLabel(): string {
    return this.isFinishedWarehouse ? 'قيمة البيع' : 'قيمة التكلفة';
  }

  get primaryValueTotal(): number {
    return this.isFinishedWarehouse ? this.totals.total_sell_value : this.totals.total_value;
  }

  itemPrimaryValue(row: any): number {
    return this.isFinishedWarehouse ? this.num(row.sell_value) : this.num(row.total_value);
  }

  load(): void {
    this.loading = true;
    this.loadError = null;
    const params: Record<string, string | undefined> = {
      warehouse: this.warehouse,
      date_from: this.dateFrom || undefined,
      date_to: this.dateTo || undefined,
      sort: this.sortField,
      search: this.searchTerm.trim() || undefined
    };
    this.categoryService.warehouseInventoryReport(this.pageSize, this.page + 1, params).subscribe({
      next: (res: any) => {
        this.data = res?.data || [];
        this.length = res?.total || 0;
        this.totals = { ...this.totals, ...(res?.totals || {}) };
        this.expanded.clear();
        this.imageFailed.clear();
        this.loading = false;
      },
      error: (err) => {
        this.data = [];
        this.loading = false;
        const body = err?.error;
        const msg =
          (typeof body?.message === 'string' && body.message) ||
          (body?.errors && typeof body.errors === 'object'
            ? Object.values(body.errors).flat().join(' ')
            : null) ||
          err?.message ||
          'تعذر تحميل التقرير. تحقق من التواريخ والاتصال بالخادم.';
        this.loadError = msg;
      }
    });
  }

  onPageChange(event: any): void {
    this.page = event.pageIndex;
    this.pageSize = event.pageSize;
    this.load();
  }

  onSearchChange(): void {
    clearTimeout(this.searchDebounce);
    this.searchDebounce = setTimeout(() => {
      this.page = 0;
      this.load();
    }, 400);
  }

  onSortChange(): void {
    this.page = 0;
    this.load();
  }

  onWarehouseChange(): void {
    this.page = 0;
    this.load();
  }

  onDateChange(): void {
    this.page = 0;
    this.load();
  }

  toggleFilters(): void {
    this.showFilters = !this.showFilters;
  }

  toggleExpand(index: number): void {
    if (this.expanded.has(index)) {
      this.expanded.delete(index);
    } else {
      this.expanded.add(index);
    }
  }

  onImgError(index: number): void {
    this.imageFailed.add(index);
  }

  trackByIndex(index: number): number {
    return index;
  }

  private num(v: unknown): number {
    if (v == null || v === '') {
      return 0;
    }
    const n = typeof v === 'number' ? v : parseFloat(String(v).replace(/,/g, ''));
    return Number.isFinite(n) ? n : 0;
  }
}

