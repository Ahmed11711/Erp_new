import { Component, OnInit } from '@angular/core';
import { AccountingReportService } from 'src/app/accounting/services/accounting-report.service';

@Component({
  selector: 'app-category',
  templateUrl: './category.component.html',
  styleUrls: ['./category.component.css']
})
export class CategoryComponent implements OnInit {
  data: any[] = [];
  filteredData: any[] = [];
  dateFrom: string | null = null;
  dateTo: string | null = null;
  searchTerm = '';
  loading = false;
  loadError = false;

  constructor(private reportService: AccountingReportService) {}

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
}
