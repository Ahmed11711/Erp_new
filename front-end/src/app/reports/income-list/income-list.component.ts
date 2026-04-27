import { Component, OnInit } from '@angular/core';
import { IncomeListService } from 'src/app/financial/services/income-list.service';
import { AuthService } from 'src/app/auth/auth.service';

@Component({
  selector: 'app-income-list',
  templateUrl: './income-list.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './income-list.component.css']
})
export class IncomeListComponent implements OnInit {
  dateFrom: string | null = null;
  dateTo: string | null = null;

  data: any = {};
  incomeSales: number = 0;
  costSales: number = 0;
  totalWin: number = 0;
  operatingIncome: number = 0;
  totalWinBeforeVat: number = 0;
  netProfitAfterTax: number = 0;
  grossMarginPercent: number = 0;
  operatingMarginPercent: number = 0;
  netMarginPercent: number = 0;
  operatingExpensesTotal: number = 0;

  user!: string;
  filter: Record<string, string> = {};

  constructor(
    private incomeListService: IncomeListService,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    this.dateFrom = `${y}-${m}-01`;
    this.dateTo = `${y}-${m}-${d}`;
    this.filter['date_from'] = this.dateFrom;
    this.filter['date_to'] = this.dateTo;
    this.getData();
    this.user = this.authService.getUser();
  }

  onDateFromChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    this.dateFrom = target.value;
    delete this.filter['month'];
    this.filter['date_from'] = this.dateFrom;
    if (this.dateTo) {
      this.filter['date_to'] = this.dateTo;
    }
    this.getData();
  }

  onDateToChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    this.dateTo = target.value;
    delete this.filter['month'];
    if (this.dateFrom) {
      this.filter['date_from'] = this.dateFrom;
    }
    this.filter['date_to'] = this.dateTo;
    this.getData();
  }

  getData(): void {
    this.incomeListService.get(this.filter).subscribe((res: any) => {
      this.data = res || {};
      this.incomeSales =
        res?.total_revenue != null
          ? res.total_revenue
          : (res?.net_sales ?? 0) + (res?.shipping_revenue ?? 0);
      this.costSales = res?.cogs ?? 0;
      this.totalWin = res?.gross_profit ?? 0;
      this.operatingIncome = res?.operating_income ?? 0;
      this.totalWinBeforeVat = res?.earnings_before_tax ?? res?.net_profit_before_tax ?? 0;
      this.netProfitAfterTax = res?.net_profit_after_tax ?? res?.earnings_before_tax ?? 0;
      this.grossMarginPercent = res?.gross_margin_percent ?? 0;
      this.operatingMarginPercent = res?.operating_margin_percent ?? 0;
      this.netMarginPercent = res?.profit_margin_percent ?? 0;
      this.operatingExpensesTotal = res?.operating_expenses_total ?? 0;
    });
  }

  formatNumber(val: number | undefined | null): string {
    if (val == null || val === undefined) {
      return '0.00';
    }
    return Number(val).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  get isProfit(): boolean {
    const val = this.netProfitAfterTax ?? this.totalWinBeforeVat ?? 0;
    return val >= 0;
  }
}
