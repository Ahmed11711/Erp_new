import { Component, OnInit } from '@angular/core';
import { AccountingReportService } from 'src/app/accounting/services/accounting-report.service';

@Component({
  selector: 'app-category',
  templateUrl: './category.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './category.component.css']
})
export class CategoryComponent implements OnInit {
  data: any[] = [];
  filteredData: any[] = [];
  dateFrom: string | null = null;
  dateTo: string | null = null;
  searchTerm = '';
  loading = false;
  loadError = false;
  showFilters = true;

  expanded = new Set<number>();

  constructor(private reportService: AccountingReportService) {}

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

  load(): void {
    this.loading = true;
    this.loadError = false;
    const params = { date_from: this.dateFrom || undefined, date_to: this.dateTo || undefined };
    this.reportService.getCategoryProfitability(params).subscribe({
      next: (res) => {
        this.data = (res?.data || []).map((r: any) => ({
          category_name: r.category_name,
          category_type: r.category_type ?? '-',
          measurement_unit: r.measurement_unit ?? '-',
          sales_qty: r.sales_qty,
          sales_amount: r.sales_amount,
          orders_count: r.orders_count ?? 0,
          returns_qty: r.returns_qty,
          rejected_qty: r.rejected_qty ?? r.returns_qty,
          avg_selling_price: r.avg_selling_price,
          avg_cost: r.avg_cost,
          ref_unit_cost: r.ref_unit_cost != null && r.ref_unit_cost !== '' ? r.ref_unit_cost : '—',
          net_profit: r.net_profit,
          total_profit: r.total_profit,
          profit_margin: r.profit_margin,
          description: r.description ?? ''
        }));
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

  onSearchChange(): void {
    this.applyFilter();
  }

  toggleFilters(): void {
    this.showFilters = !this.showFilters;
  }

  applyFilter(): void {
    const term = (this.searchTerm || '').trim().toLowerCase();
    if (!term) {
      this.filteredData = [...this.data];
    } else {
      this.filteredData = this.data.filter((r: any) =>
        (r.category_name || '').toLowerCase().includes(term) ||
        (r.category_type || '').toLowerCase().includes(term)
      );
    }
  }

  private num(v: unknown): number {
    if (v == null || v === '' || v === '—') {
      return 0;
    }
    const n = typeof v === 'number' ? v : parseFloat(String(v).replace(/,/g, ''));
    return Number.isFinite(n) ? n : 0;
  }

  get sumSalesQty(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.sales_qty), 0);
  }

  get sumSalesAmount(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.sales_amount), 0);
  }

  get sumOrdersCount(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.orders_count), 0);
  }

  get sumReturnsQty(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.returns_qty), 0);
  }

  get sumRejectedQty(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.rejected_qty), 0);
  }

  get sumNetProfit(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.net_profit), 0);
  }

  get sumTotalProfit(): number {
    return this.filteredData.reduce((s, r) => s + this.num(r.total_profit), 0);
  }

  /** متوسط سعر البيع = إجمالي القيمة ÷ إجمالي القطع */
  get aggregateAvgSellingPrice(): string {
    if (!this.sumSalesQty) {
      return '—';
    }
    return (this.sumSalesAmount / this.sumSalesQty).toFixed(2);
  }

  /** متوسط تكلفة مرجح بالكمية المباعة */
  get aggregateAvgCost(): string {
    if (!this.sumSalesQty) {
      return '—';
    }
    const weighted = this.filteredData.reduce(
      (s, r) => s + this.num(r.avg_cost) * this.num(r.sales_qty),
      0
    );
    return (weighted / this.sumSalesQty).toFixed(2);
  }

  /** هامش ربح إجمالي % */
  get aggregateProfitMargin(): string {
    if (!this.sumSalesAmount) {
      return '—';
    }
    return ((this.sumTotalProfit / this.sumSalesAmount) * 100).toFixed(2);
  }
}
