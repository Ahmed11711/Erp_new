import { Component, OnInit } from '@angular/core';
import { CategoryService } from 'src/app/categories/services/category.service';

@Component({
  selector: 'app-product-sales',
  templateUrl: './product-sales.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './product-sales.component.css']
})
export class ProductSalesComponent implements OnInit {
  data: any[] = [];
  filteredData: any[] = [];
  dateFrom: string | null = null;
  dateTo: string | null = null;
  searchTerm = '';
  loading = false;
  loadError = false;
  length = 0;
  page = 0;
  pageSize = 50;
  pageSizeOptions = [15, 50, 100];
  showFilters = true;

  expanded = new Set<number>();

  constructor(private categoryService: CategoryService) {}

  toggleExpand(index: number): void {
    if (this.expanded.has(index)) {
      this.expanded.delete(index);
    } else {
      this.expanded.add(index);
    }
  }

  trackByIndex(index: number): number {
    return index;
  }

  ngOnInit(): void {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    this.dateFrom = `${y}-${m}-01`;
    this.dateTo = `${y}-${m}-${d}`;
    this.load();
  }

  profitabilityTotals: any = null;

  load(): void {
    this.loading = true;
    this.loadError = false;
    const params: any = { date_from: this.dateFrom, date_to: this.dateTo };
    this.categoryService.categoriesSellReports(this.pageSize, this.page + 1, params).subscribe({
      next: (res: any) => {
        this.data = res?.data || [];
        this.length = res?.total || 0;
        this.profitabilityTotals = res?.profitability_totals ?? null;
        this.applyFilter();
        this.loading = false;
      },
      error: () => {
        this.data = [];
        this.filteredData = [];
        this.loadError = true;
        this.loading = false;
      }
    });
  }

  onPageChange(event: any): void {
    this.page = event.pageIndex;
    this.pageSize = event.pageSize;
    this.load();
  }

  onSearchChange(): void {
    this.applyFilter();
  }

  applyFilter(): void {
    const term = (this.searchTerm || '').trim().toLowerCase();
    if (!term) {
      this.filteredData = [...this.data];
    } else {
      this.filteredData = this.data.filter((r: any) =>
        (r.category_name || '').toLowerCase().includes(term)
      );
    }
  }

  toggleFilters(): void {
    this.showFilters = !this.showFilters;
  }

  private num(v: unknown): number {
    if (v == null || v === '' || v === '—') {
      return 0;
    }
    const n = typeof v === 'number' ? v : parseFloat(String(v).replace(/,/g, ''));
    return Number.isFinite(n) ? n : 0;
  }

  /** إجماليات الصفحة الحالية (بعد البحث المحلي) */
  get pageSumOrders(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.total_orders), 0);
  }
  get pageSumNewQty(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.total_quantity_new), 0);
  }
  get pageSumNewValue(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.total_new), 0);
  }
  get pageSumReturnValue(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.total_postpone), 0);
  }
}
