import { Component, OnInit } from '@angular/core';
import { ShippingCompanyService } from 'src/app/shipping/services/shipping-company.service';

@Component({
  selector: 'app-shippincompany-reports',
  templateUrl: './shippincompany-reports.component.html',
  styleUrls: ['./shippincompany-reports.component.css']
})
export class ShippincompanyReportsComponent implements OnInit {

  data: any[] = [];
  dateFrom!: string;
  dateTo!: string;
  searchKeyword = '';
  loading = false;
  private searchTimer: ReturnType<typeof setTimeout> | undefined;

  constructor(private shippingCompany: ShippingCompanyService) {
    const today = new Date();
    this.dateTo = this.formatDate(today);
    const from = new Date(today.getFullYear(), today.getMonth(), 1);
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
    this.loading = true;
    const params: { date_from: string; date_to: string; q?: string } = {
      date_from: this.dateFrom,
      date_to: this.dateTo,
    };
    if (this.searchKeyword.trim()) {
      params.q = this.searchKeyword.trim();
    }
    this.shippingCompany.shippingCompaniesReport(params).subscribe({
      next: (res: any) => {
        this.data = res?.data ?? [];
        this.loading = false;
      },
      error: () => {
        this.data = [];
        this.loading = false;
      },
    });
  }

  onDatesChange(): void {
    this.load();
  }

  onSearchInput(): void {
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }
    this.searchTimer = setTimeout(() => this.load(), 400);
  }

  get totalOrders(): number {
    return this.data.reduce((sum, row) => sum + (+row.orders_count || 0), 0);
  }

  get totalCollected(): number {
    return this.data.reduce((sum, row) => sum + (+row.collected_count || 0), 0);
  }

  get totalRefused(): number {
    return this.data.reduce((sum, row) => sum + (+row.refused_count || 0), 0);
  }

  /** إجمالي نسبة التحصيل = مجموع المحصل ÷ مجموع الطلبات */
  get aggregateCollectionPercentage(): string {
    if (!this.totalOrders) {
      return '0';
    }
    return ((this.totalCollected / this.totalOrders) * 100).toFixed(2);
  }

}
