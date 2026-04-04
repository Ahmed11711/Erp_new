import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { InvoiceService } from 'src/app/purchases/service/invoice.service';

@Component({
  selector: 'app-procurement-report',
  templateUrl: './procurement-report.component.html',
  styleUrls: ['./procurement-report.component.css']
})
export class ProcurementReportComponent implements OnInit {

  invoices: any[] = [];
  dateFrom!: string;
  dateTo!: string;
  searchKeyword = '';
  length = 0;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15, 50, 100];
  private searchTimer: ReturnType<typeof setTimeout> | undefined;

  constructor(
    private invoice: InvoiceService,
    private router: Router
  ) {
    const today = new Date();
    const to = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const from = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
    this.dateTo = this.formatDate(to);
    this.dateFrom = this.formatDate(from);
  }

  private formatDate(d: Date): string {
    const y = d.getFullYear();
    const m = (d.getMonth() + 1).toString().padStart(2, '0');
    const day = d.getDate().toString().padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    const params: Record<string, string> = {
      date_from: this.dateFrom,
      date_to: this.dateTo,
    };
    if (this.searchKeyword.trim()) {
      params['q'] = this.searchKeyword.trim();
    }
    this.invoice.search(this.pageSize, this.page + 1, params).subscribe((res: any) => {
      this.invoices = res.data ?? [];
      this.length = res.total ?? 0;
    });
  }

  onDatesChange(): void {
    this.page = 0;
    this.load();
  }

  onSearchInput(): void {
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }
    this.searchTimer = setTimeout(() => {
      this.page = 0;
      this.load();
    }, 400);
  }

  onPageChange(event: any): void {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.load();
  }

  displayAmount(inv: any, field: string): number {
    const src = inv?.updated_purchase ? inv.updated_purchase : inv;
    const v = src?.[field];
    return v != null ? Number(v) : 0;
  }

  invoiceDetails(id: number): void {
    this.router.navigate(['/dashboard/purchases/invoice', id]);
  }

  editInvoice(id: number): void {
    this.router.navigate(['/dashboard/purchases/add_invoice', id]);
  }

  getTotals(): { total: number; paid: number; due: number } {
    return this.invoices.reduce(
      (acc, item) => ({
        total: acc.total + this.displayAmount(item, 'total_price'),
        paid: acc.paid + this.displayAmount(item, 'paid_amount'),
        due: acc.due + this.displayAmount(item, 'due_amount'),
      }),
      { total: 0, paid: 0, due: 0 }
    );
  }
}
