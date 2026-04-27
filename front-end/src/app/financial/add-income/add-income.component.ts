import { Component, OnInit } from '@angular/core';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AccountingReportService } from 'src/app/accounting/services/accounting-report.service';
import { PaymentSourcesService } from 'src/app/accounting/services/payment-sources.service';
import { ToastService } from 'src/app/shared/toast/toast.service';
import { IncomeService } from '../services/income.service';

@Component({
  selector: 'app-add-income',
  templateUrl: './add-income.component.html',
  styleUrls: ['./add-income.component.css']
})
export class AddIncomeComponent implements OnInit {
  errorform = false;
  errorMessage = '';
  dateFrom = new Date().toISOString().slice(0, 10);
  paymentType: 'safe' | 'bank' | 'service_account' = 'safe';

  safesData: any[] = [];
  banksData: any[] = [];
  serviceAccountsData: any[] = [];
  revenueAccounts: { id: number; code?: number | string; name: string }[] = [];
  accountSearchTerm = '';

  form = new FormGroup({
    type: new FormControl<string | null>(null, [Validators.required]),
    date: new FormControl<string | null>(null, [Validators.required]),
    income_amount: new FormControl<number | null>(null, [Validators.required, Validators.min(0.01)]),
    revenue_tree_account_id: new FormControl<number | null>(null, [Validators.required]),
    payment_type: new FormControl<'safe' | 'bank' | 'service_account'>('safe', [Validators.required]),
    safe_id: new FormControl<number | null>(null),
    bank_id: new FormControl<number | null>(null),
    service_account_id: new FormControl<number | null>(null),
  });

  constructor(
    private incomeService: IncomeService,
    private route: Router,
    private paymentSourcesService: PaymentSourcesService,
    private accountingReportService: AccountingReportService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.form.patchValue({
      date: this.dateFrom,
      payment_type: 'safe',
    });
    this.paymentType = 'safe';

    this.paymentSourcesService.getPaymentSources().subscribe({
      next: (res) => {
        this.safesData = res.safes || [];
        this.banksData = res.banks || [];
        this.serviceAccountsData = res.service_accounts || [];
      },
      error: () => this.toast.error('تعذر تحميل مصادر التحصيل (خزائن، بنوك، محافظ)')
    });

    this.accountingReportService.getAccountingTree().subscribe({
      next: (response: any) => {
        const tree = Array.isArray(response) ? response : [];
        const flat = this.flattenTree(tree);
        this.revenueAccounts = flat
          .filter(
            (a: any) =>
              (a.type === 'revenue' || a.type === 'income') &&
              (!a.children || !a.children.length)
          )
          .map((a: any) => ({
            id: a.id,
            code: a.code,
            name: a.name,
          }))
          .sort((a, b) =>
            String(a.code ?? '').localeCompare(String(b.code ?? ''), undefined, { numeric: true })
          );
      },
      error: () => this.toast.error('تعذر تحميل شجرة حسابات الإيرادات')
    });
  }

  get filteredRevenueAccounts(): { id: number; code?: number | string; name: string }[] {
    const term = this.accountSearchTerm.trim().toLowerCase();
    if (!term) {
      return this.revenueAccounts;
    }
    return this.revenueAccounts.filter(
      (a) =>
        String(a.name || '')
          .toLowerCase()
          .includes(term) || String(a.code ?? '').includes(term)
    );
  }

  onPaymentTypeChange(): void {
    this.paymentType = this.form.get('payment_type')?.value || 'safe';
    this.form.patchValue({
      safe_id: null,
      bank_id: null,
      service_account_id: null
    });
  }

  get isSourceSelected(): boolean {
    const pt = this.form.get('payment_type')?.value;
    if (pt === 'safe') {
      return this.form.get('safe_id')?.value != null;
    }
    if (pt === 'bank') {
      return this.form.get('bank_id')?.value != null;
    }
    if (pt === 'service_account') {
      return this.form.get('service_account_id')?.value != null;
    }
    return false;
  }

  onDateFromChange(event: Event): void {
    const v = (event.target as HTMLInputElement).value;
    this.form.patchValue({ date: v });
  }

  private flattenTree(accounts: any[]): any[] {
    const out: any[] = [];
    if (!accounts?.length) {
      return out;
    }
    for (const a of accounts) {
      out.push(a);
      if (a.children?.length) {
        out.push(...this.flattenTree(a.children));
      }
    }
    return out;
  }

  submitform(): void {
    this.errorform = false;
    this.form.markAllAsTouched();

    if (this.form.invalid || !this.isSourceSelected) {
      this.errorform = true;
      if (!this.isSourceSelected) {
        this.errorMessage = 'اختر مصدر التحصيل: خزينة، أو بنك، أو محفظة إلكترونية.';
        this.toast.warning(this.errorMessage);
      } else {
        this.errorMessage = 'من فضلك أكمل الحقول المطلوبة.';
        this.toast.error(this.errorMessage);
      }
      return;
    }

    const v = this.form.getRawValue();
    const payload = {
      type: v.type,
      date: v.date,
      income_amount: v.income_amount,
      revenue_tree_account_id: v.revenue_tree_account_id,
      payment_type: v.payment_type,
      safe_id: v.payment_type === 'safe' ? v.safe_id : null,
      bank_id: v.payment_type === 'bank' ? v.bank_id : null,
      service_account_id: v.payment_type === 'service_account' ? v.service_account_id : null,
    };

    this.incomeService.add(payload).subscribe({
      next: () => {
        this.toast.success('تم تسجيل الإيراد وإثبات القيد المحاسبي');
        this.route.navigate(['/dashboard/financial/otherincome']);
      },
      error: (err) => {
        this.errorform = true;
        this.errorMessage =
          err?.error?.message ||
          (typeof err?.error === 'string' ? err.error : null) ||
          'تعذر الحفظ. تحقق من البيانات أو من ربط الخزينة/البنك بالشجرة المحاسبية.';
        this.toast.error(this.errorMessage);
      }
    });
  }
}
