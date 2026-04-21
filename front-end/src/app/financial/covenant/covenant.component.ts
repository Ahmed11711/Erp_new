import { Component, OnInit } from '@angular/core';
import { CovenantService } from '../services/covenant.service';

export interface CovenantRow {
  id?: number;
  transaction_date?: string;
  covenant_type?: string;
  holder_kind?: string;
  payment_type?: string;
  amount?: number | string;
  description?: string | null;
  note?: string | null;
  safe?: { name?: string } | null;
  bank?: { name?: string } | null;
  service_account?: { name?: string } | null;
  user?: { name?: string } | null;
}

const HOLDER_LABELS: Record<string, string> = {
  petty_custodian: 'متحصل العهدة',
  employee: 'موظف',
  representative: 'مندوب',
  other: 'أخرى'
};

@Component({
  selector: 'app-covenant',
  templateUrl: './covenant.component.html',
  styleUrls: ['./covenant.component.css']
})
export class CovenantComponent implements OnInit {
  data: CovenantRow[] = [];
  tableData: CovenantRow[] = [];
  dateFrom: string;
  dateTo: string;
  searchWord = '';
  totalAmount = 0;
  loadError: string | null = null;

  constructor(private covenantService: CovenantService) {
    const today = new Date();
    const y = today.getFullYear();
    const m = (today.getMonth() + 1).toString().padStart(2, '0');
    const d = today.getDate().toString().padStart(2, '0');
    const iso = `${y}-${m}-${d}`;
    this.dateFrom = iso;
    this.dateTo = iso;
  }

  ngOnInit(): void {
    this.refresh();
  }

  refresh(): void {
    this.covenantService.list().subscribe({
      next: (rows) => {
        this.loadError = null;
        this.data = Array.isArray(rows) ? rows : [];
        this.applyFilters();
      },
      error: () => {
        this.loadError = 'تعذر تحميل سجل العهد';
        this.data = [];
        this.tableData = [];
        this.totalAmount = 0;
      }
    });
  }

  holderLabel(kind: string | undefined): string {
    if (!kind) {
      return '—';
    }
    return HOLDER_LABELS[kind] || kind;
  }

  paymentSourceLabel(row: CovenantRow): string {
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

  formatDate(raw: string | undefined): string {
    if (!raw) {
      return '—';
    }
    return String(raw).slice(0, 10);
  }

  onDateFromChange(event: Event): void {
    this.dateFrom = (event.target as HTMLInputElement).value;
    this.applyFilters();
  }

  onDateToChange(event: Event): void {
    this.dateTo = (event.target as HTMLInputElement).value;
    this.applyFilters();
  }

  private recalcTotal(): void {
    this.totalAmount = 0;
    for (const row of this.tableData) {
      const n = Number(row.amount);
      if (!Number.isNaN(n)) {
        this.totalAmount += n;
      }
    }
  }

  applyFilters(): void {
    let filtered = [...this.data];

    if (this.dateFrom && this.dateTo) {
      filtered = filtered.filter((item) => {
        const d = this.formatDate(item.transaction_date);
        return d >= this.dateFrom && d <= this.dateTo;
      });
    } else if (this.dateFrom) {
      filtered = filtered.filter((item) => this.formatDate(item.transaction_date) >= this.dateFrom);
    } else if (this.dateTo) {
      filtered = filtered.filter((item) => this.formatDate(item.transaction_date) <= this.dateTo);
    }

    const q = (this.searchWord || '').trim().toLowerCase();
    if (q) {
      filtered = filtered.filter((item) => {
        const desc = (item.description || '').toLowerCase();
        const note = (item.note || '').toLowerCase();
        const holder = this.holderLabel(item.holder_kind).toLowerCase();
        const src = this.paymentSourceLabel(item).toLowerCase();
        const ctype = (item.covenant_type || '').toLowerCase();
        return desc.includes(q) || note.includes(q) || holder.includes(q) || src.includes(q) || ctype.includes(q);
      });
    }

    this.tableData = filtered;
    this.recalcTotal();
  }
}
