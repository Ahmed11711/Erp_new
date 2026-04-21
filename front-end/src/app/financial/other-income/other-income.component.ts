import { Component, OnInit } from '@angular/core';
import { IncomeService } from '../services/income.service';

/** سجل إيراد من الـ API (مع علاقات اختيارية) */
export interface IncomeRow {
  id?: number;
  type?: string;
  date?: string;
  income_amount?: number | string;
  payment_type?: 'bank' | 'safe' | 'service_account' | string | null;
  revenue_tree_account_id?: number | null;
  revenue_tree_account?: { id?: number; name?: string; code?: number | string } | null;
  bank?: { name?: string } | null;
  safe?: { name?: string } | null;
  service_account?: { name?: string } | null;
}

@Component({
  selector: 'app-other-income',
  templateUrl: './other-income.component.html',
  styleUrls: ['./other-income.component.css']
})
export class OtherIncomeComponent implements OnInit {
  data: IncomeRow[] = [];
  tableData: IncomeRow[] = [];
  dateFrom: string | null = null;
  dateTo: string | null = null;
  total = 0;
  searchWord = '';
  loadError: string | null = null;

  constructor(private incomeService: IncomeService) {}

  ngOnInit(): void {
    this.incomeService.data().subscribe({
      next: (result: IncomeRow[]) => {
        this.loadError = null;
        this.data = Array.isArray(result) ? result : [];
        this.applyFilters();
      },
      error: () => {
        this.loadError = 'تعذر تحميل الإيرادات';
        this.data = [];
        this.tableData = [];
        this.total = 0;
      }
    });
  }

  onDateFromChange(event: Event): void {
    this.dateFrom = (event.target as HTMLInputElement).value || null;
    this.applyFilters();
  }

  onDateToChange(event: Event): void {
    this.dateTo = (event.target as HTMLInputElement).value || null;
    this.applyFilters();
  }

  /** وصف مصدر التحصيل (خزينة / بنك / محفظة) */
  paymentSourceLabel(row: IncomeRow): string {
    const pt = row.payment_type;
    if (pt === 'safe' && row.safe?.name) {
      return 'خزينة: ' + row.safe.name;
    }
    if (pt === 'bank' && row.bank?.name) {
      return 'بنك: ' + row.bank.name;
    }
    if (pt === 'service_account' && row.service_account?.name) {
      return 'محفظة: ' + row.service_account.name;
    }
    if (pt === 'safe') {
      return 'خزينة';
    }
    if (pt === 'bank') {
      return 'بنك';
    }
    if (pt === 'service_account') {
      return 'محفظة إلكترونية';
    }
    return '—';
  }

  revenueAccountLabel(row: IncomeRow): string {
    const a = row.revenue_tree_account;
    if (!a) {
      return '—';
    }
    const code = a.code != null && a.code !== '' ? String(a.code) + ' — ' : '';
    return code + (a.name || '');
  }

  private recalcTotal(): void {
    this.total = 0;
    for (const row of this.tableData) {
      const n = Number(row.income_amount);
      if (!Number.isNaN(n)) {
        this.total += n;
      }
    }
  }

  applyFilters(): void {
    let filtered = [...this.data];

    if (this.dateFrom && this.dateTo) {
      filtered = filtered.filter((item) => {
        const d = item.date;
        if (!d) {
          return false;
        }
        return d >= this.dateFrom! && d <= this.dateTo!;
      });
    } else if (this.dateFrom) {
      filtered = filtered.filter((item) => item.date && item.date >= this.dateFrom!);
    } else if (this.dateTo) {
      filtered = filtered.filter((item) => item.date && item.date <= this.dateTo!);
    }

    const q = (this.searchWord || '').trim().toLowerCase();
    if (q) {
      filtered = filtered.filter((item) => {
        const type = (item.type || '').toLowerCase();
        const rev = this.revenueAccountLabel(item).toLowerCase();
        const src = this.paymentSourceLabel(item).toLowerCase();
        return type.includes(q) || rev.includes(q) || src.includes(q);
      });
    }

    this.tableData = filtered;
    this.recalcTotal();
  }
}
