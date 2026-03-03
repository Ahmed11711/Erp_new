import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { SafeService } from 'src/app/accounting/services/safe.service';
import { BankService } from 'src/app/accounting/services/bank.service';
import { ReportsService } from '../services/reports.service';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service';

@Component({
  selector: 'app-financial-statement',
  templateUrl: './financial-statement.component.html',
  styleUrls: ['./financial-statement.component.css']
})
export class FinancialStatementComponent implements OnInit {

  data: any[] = [];
  details: any = null;
  totals: any = null;

  dateFrom!: string;
  dateTo!: string;

  loading = false;
  errorMessage: string | null = null;

  // Lists for Dropdowns
  safes: any[] = [];
  banks: any[] = [];
  accounts: any[] = [];

  // Selected Entity
  selectedEntityId: any;

  form: FormGroup = new FormGroup({
    'type': new FormControl('safe', [Validators.required]), // safe, bank, account
    'entity_id': new FormControl(null, [Validators.required]),
    'date_from': new FormControl(null),
    'date_to': new FormControl(null),
  });

  constructor(
    private safeService: SafeService,
    private bankService: BankService,
    private serviceAccountsService: ServiceAccountsService,
    private reportsService: ReportsService
  ) {
    const today = new Date();
    const year = today.getFullYear();
    const month = (today.getMonth() + 1).toString().padStart(2, '0');
    const day = today.getDate().toString().padStart(2, '0');

    // Default to beginning of month
    this.dateFrom = `${year}-${month}-01`;
    this.dateTo = `${year}-${month}-${day}`;

    this.form.patchValue({
      date_from: this.dateFrom,
      date_to: this.dateTo
    });
  }

  ngOnInit() {
    this.loadSafes();
    this.loadBanks();
    this.loadAccounts();

    // Subscribe to type changes to reset entity selection
    this.form.get('type')?.valueChanges.subscribe(() => {
      this.form.patchValue({ entity_id: null });
      this.data = [];
      this.details = null;
      this.errorMessage = null;
    });
  }

  loadSafes() {
    this.safeService.getAll().subscribe({
      next: (res: any) => {
        this.safes = res?.data || res || [];
      },
      error: (err) => {
        console.error('خطأ في تحميل الخزائن:', err);
        this.safes = [];
      }
    });
  }

  loadBanks() {
    this.bankService.getAll().subscribe({
      next: (res: any) => {
        this.banks = res?.data || res || [];
      },
      error: (err) => {
        console.error('خطأ في تحميل البنوك:', err);
        this.banks = [];
      }
    });
  }

  loadAccounts() {
    this.serviceAccountsService.index().subscribe({
      next: (res: any) => {
        this.accounts = res?.data || res || [];
      },
      error: (err) => {
        console.error('خطأ في تحميل الحسابات الخدمية:', err);
        this.accounts = [];
      }
    });
  }

  submitform() {
    if (this.form.invalid) return;

    const type = this.form.value.type;
    const entityId = this.form.value.entity_id;
    const from = this.form.value.date_from;
    const to = this.form.value.date_to;

    let treeAccountId = null;

    // Determine Tree Account ID based on selection
    if (type === 'safe') {
      const safe = this.safes.find((s: any) => s.id == entityId);
      if (safe) treeAccountId = safe.account_id;
    } else if (type === 'bank') {
      const bank = this.banks.find((b: any) => b.id == entityId);
      if (bank) treeAccountId = bank.asset_id; // Bank links via asset_id
    } else if (type === 'account') {
      const account = this.accounts.find((a: any) => a.id == entityId);
      if (account) treeAccountId = account.account_id;
    }

    if (!treeAccountId) {
      this.errorMessage = 'الحساب المالي غير مرتبط بهذا العنصر';
      return;
    }

    this.loading = true;
    this.errorMessage = null;
    this.data = [];
    this.details = null;

    this.reportsService.getAccountStatement(treeAccountId, from, to).subscribe({
      next: (res: any) => {
        this.loading = false;
        this.data = res?.entries || [];
        this.details = {
          opening_balance: res?.opening_balance ?? 0,
          closing_balance: res?.closing_balance ?? 0,
          account: res?.account
        };
        this.totals = {
          debit: res?.total_debit ?? 0,
          credit: res?.total_credit ?? 0
        };
      },
      error: (err) => {
        this.loading = false;
        const errBody = err?.error;
        this.errorMessage = errBody?.message
          || (errBody?.errors && typeof errBody.errors === 'object' ? Object.values(errBody.errors).flat().join(', ') : null)
          || err?.message
          || 'حدث خطأ أثناء جلب التقرير. تأكد من تشغيل الخادم والاتصال بالإنترنت.';
        console.error('خطأ في تقرير كشف الحساب:', err);
      }
    });
  }

  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateFrom = target.value;
    this.form.patchValue({ date_from: this.dateFrom });
  }

  onDateToChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateTo = target.value;
    this.form.patchValue({ date_to: this.dateTo });
  }
}
