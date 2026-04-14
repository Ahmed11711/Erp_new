import { Component, OnInit } from '@angular/core';
import { AccountingReportService } from 'src/app/accounting/services/accounting-report.service';

@Component({
  selector: 'app-product-performance',
  templateUrl: './product-performance.component.html',
  styleUrls: ['./product-performance.component.css']
})
export class ProductPerformanceComponent implements OnInit {
  dateFrom: string | null = null;
  dateTo: string | null = null;
  loading = false;
  loadError: string | null = null;

  rows: any[] = [];
  totals: any = { sales_qty: 0, sales_amount: 0, returns_qty: 0, returns_amount: 0, net_sales: 0, cogs: 0, avg_unit_cost: 0, gross_profit: 0, gross_margin_percent: 0 };

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
    this.loadError = null;
    this.loading = true;

    const from = this.dateFrom?.trim() || undefined;
    const to = this.dateTo?.trim() || undefined;

    this.reportService.getProductPerformance({
      date_from: from,
      date_to: to
    }).subscribe({
      next: (res) => {
        this.rows = res?.data || [];
        this.totals = res?.totals || this.totals;
        this.loading = false;
      },
      error: (err) => {
        this.rows = [];
        this.loading = false;
        const body = err?.error;
        const msg =
          (typeof body?.message === 'string' && body.message) ||
          (body?.errors && typeof body.errors === 'object'
            ? Object.values(body.errors).flat().join(' ')
            : null) ||
          err?.message ||
          'تعذر تحميل التقرير. تحقق من التواريخ (من يجب أن يكون ≤ إلى) والاتصال بالخادم.';
        this.loadError = msg;
      }
    });
  }
}
