import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { forkJoin } from 'rxjs';
import { AccountingReportService } from 'src/app/accounting/services/accounting-report.service';
import { SafeService } from 'src/app/accounting/services/safe.service';
import { BankService } from 'src/app/accounting/services/bank.service';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service';

@Component({
  selector: 'app-financial-statement',
  templateUrl: './financial-statement.component.html',
  styleUrls: ['./financial-statement.component.css']
})
export class FinancialStatementComponent implements OnInit {

  /** تبويب 1: شجرة الحسابات — تبويب 2: خزن/بنوك/خدمات */
  activeTab: 'ledger' | 'cash' = 'ledger';

  data: any[] = [];
  details: any = null;
  totals: any = null;

  loading = false;
  errorMessage: string | null = null;

  // ——— تبويب الشجرة ———
  allLeafAccounts: any[] = [];
  filteredAccounts: any[] = [];
  accountSearchTerm = '';

  accountSourceOptions = [
    { value: 'all', label: 'كل الحسابات' },
    { value: 'asset', label: 'أصول' },
    { value: 'liability', label: 'خصوم' },
    { value: 'expense', label: 'مصروفات' },
    { value: 'revenue', label: 'إيرادات' },
    { value: 'equity', label: 'حقوق ملكية' },
    { value: 'safe_only', label: 'خزن فقط' },
    { value: 'bank_only', label: 'بنوك فقط' },
    { value: 'cash_bank', label: 'خزن وبنوك (أصول نقدية)' },
    { value: 'service_style', label: 'حسابات خدمية (مصروف/إيراد شائعة)' },
  ];

  ledgerForm = new FormGroup({
    source_type: new FormControl('all', [Validators.required]),
    account_id: new FormControl<number | null>(null, [Validators.required]),
    date_from: new FormControl<string | null>(null),
    date_to: new FormControl<string | null>(null),
  });

  // ——— تبويب الخزن/البنوك/الخدمات ———
  safes: any[] = [];
  banks: any[] = [];
  serviceAccounts: any[] = [];

  entityTypeOptions = [
    { value: 'safe', label: 'خزنة' },
    { value: 'bank', label: 'بنك' },
    { value: 'service', label: 'حساب خدمي' }
  ];

  cashForm = new FormGroup({
    entity_type: new FormControl('safe', [Validators.required]),
    entity_id: new FormControl<number | null>(null, [Validators.required]),
    date_from: new FormControl<string | null>(null),
    date_to: new FormControl<string | null>(null),
  });

  constructor(
    private accountingReportService: AccountingReportService,
    private safeService: SafeService,
    private bankService: BankService,
    private serviceAccountsService: ServiceAccountsService,
    private route: ActivatedRoute,
    private router: Router
  ) {
    const today = new Date();
    const year = today.getFullYear();
    const month = (today.getMonth() + 1).toString().padStart(2, '0');
    const day = today.getDate().toString().padStart(2, '0');
    const from = `${year}-${month}-01`;
    const to = `${year}-${month}-${day}`;

    this.ledgerForm.patchValue({ date_from: from, date_to: to });
    this.cashForm.patchValue({ date_from: from, date_to: to });
  }

  ngOnInit(): void {
    this.route.queryParamMap.subscribe(() => {
      this.applyRouteFromQuery();
    });

    this.loadAccountingTree();

    forkJoin({
      safes: this.safeService.getAll(),
      banks: this.bankService.getAll(),
      serviceAccounts: this.serviceAccountsService.index()
    }).subscribe({
      next: (res: any) => {
        this.errorMessage = null;
        this.safes = res.safes?.data ?? res.safes ?? [];
        this.banks = res.banks?.data ?? res.banks ?? [];
        this.serviceAccounts = res.serviceAccounts?.data ?? res.serviceAccounts ?? [];
        this.applyRouteFromQuery();
      },
      error: (err) => {
        console.error('خطأ في تحميل الخزن/البنوك/الحسابات الخدمية:', err);
        this.errorMessage = 'تعذر تحميل قوائم الخزن والبنوك والحسابات الخدمية';
      }
    });

    this.ledgerForm.get('source_type')?.valueChanges.subscribe(() => {
      this.ledgerForm.patchValue({ account_id: null });
      this.updateFilteredAccounts();
      this.clearReportOnly();
    });

    this.cashForm.get('entity_type')?.valueChanges.subscribe(() => {
      this.cashForm.patchValue({ entity_id: null });
      this.clearReportOnly();
    });
  }

  setTab(tab: 'ledger' | 'cash'): void {
    if (this.activeTab === tab) {
      return;
    }
    this.activeTab = tab;
    this.clearReportOnly();
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { tab },
      queryParamsHandling: 'merge',
      replaceUrl: true
    });
  }

  private clearReportOnly(): void {
    this.data = [];
    this.details = null;
    this.totals = null;
    this.errorMessage = null;
  }

  /** ?tab=ledger|cash & ?preset=safes|banks|service */
  private applyRouteFromQuery(): void {
    const tab = this.route.snapshot.queryParamMap.get('tab');
    if (tab === 'cash') {
      this.activeTab = 'cash';
    } else if (tab === 'ledger') {
      this.activeTab = 'ledger';
    }

    const preset = this.route.snapshot.queryParamMap.get('preset');
    const map: Record<string, string> = {
      safes: 'safe',
      banks: 'bank',
      service: 'service'
    };
    if (preset && map[preset]) {
      this.activeTab = 'cash';
      this.cashForm.patchValue({ entity_type: map[preset], entity_id: null }, { emitEvent: false });
    }
  }

  // ——— شجرة الحسابات ———
  loadAccountingTree(): void {
    this.accountingReportService.getAccountingTree().subscribe({
      next: (response: any) => {
        const tree = Array.isArray(response) ? response : [];
        this.allLeafAccounts = this.flattenTree(tree).filter(
          (a) => !a.children || a.children.length === 0
        );
        this.updateFilteredAccounts();
      },
      error: (err) => {
        console.error('خطأ في تحميل الشجرة المحاسبية:', err);
        this.allLeafAccounts = [];
        this.filteredAccounts = [];
        if (this.activeTab === 'ledger') {
          this.errorMessage = 'تعذر تحميل شجرة الحسابات';
        }
      }
    });
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

  private isServiceStyleAccount(a: any): boolean {
    const n = (a.name || '').toLowerCase();
    const ne = (a.name_en || '').toLowerCase();
    return (
      a.type === 'expense' ||
      a.type === 'revenue' ||
      n.includes('خدمة') ||
      n.includes('خدمات') ||
      ne.includes('service')
    );
  }

  private isSafeLeaf(a: any): boolean {
    if (a.type !== 'asset') {
      return false;
    }
    const n = (a.name || '').toLowerCase();
    const ne = (a.name_en || '').toLowerCase();
    if (n.includes('بنك') || n.includes('bank') || ne.includes('bank')) {
      return false;
    }
    return n.includes('خزينة') || n.includes('خزن') || ne.includes('cash');
  }

  private isBankLeaf(a: any): boolean {
    if (a.type !== 'asset') {
      return false;
    }
    const n = (a.name || '').toLowerCase();
    const ne = (a.name_en || '').toLowerCase();
    return n.includes('بنك') || n.includes('bank') || ne.includes('bank');
  }

  private isCashBankLeaf(a: any): boolean {
    return this.isSafeLeaf(a) || this.isBankLeaf(a);
  }

  updateFilteredAccounts(): void {
    const sourceType = this.ledgerForm.get('source_type')?.value;
    let base: any[] = [...this.allLeafAccounts];

    if (sourceType === 'asset') {
      base = base.filter((a) => a.type === 'asset');
    } else if (sourceType === 'liability') {
      base = base.filter((a) => a.type === 'liability');
    } else if (sourceType === 'expense') {
      base = base.filter((a) => a.type === 'expense');
    } else if (sourceType === 'revenue') {
      base = base.filter((a) => a.type === 'revenue');
    } else if (sourceType === 'equity') {
      base = base.filter((a) => a.type === 'equity');
    } else if (sourceType === 'safe_only') {
      base = base.filter((a) => this.isSafeLeaf(a));
    } else if (sourceType === 'bank_only') {
      base = base.filter((a) => this.isBankLeaf(a));
    } else if (sourceType === 'cash_bank') {
      base = base.filter((a) => this.isCashBankLeaf(a));
    } else if (sourceType === 'service_style') {
      base = base.filter((a) => this.isServiceStyleAccount(a));
    }

    if (this.accountSearchTerm?.trim()) {
      const term = this.accountSearchTerm.trim().toLowerCase();
      base = base.filter(
        (a) =>
          (a.name && String(a.name).toLowerCase().includes(term)) ||
          (a.name_en && String(a.name_en).toLowerCase().includes(term)) ||
          (a.code != null && String(a.code).includes(term))
      );
    }

    this.filteredAccounts = base.sort((a, b) =>
      String(a.code ?? '').localeCompare(String(b.code ?? ''), undefined, { numeric: true })
    );
  }

  onAccountSearchChange(): void {
    this.updateFilteredAccounts();
  }

  onDateChange(which: 'ledger' | 'cash', part: 'from' | 'to', event: Event): void {
    const v = (event.target as HTMLInputElement).value;
    const g = which === 'ledger' ? this.ledgerForm : this.cashForm;
    if (part === 'from') {
      g.patchValue({ date_from: v });
    } else {
      g.patchValue({ date_to: v });
    }
  }

  submitLedgerForm(): void {
    if (this.ledgerForm.invalid) {
      return;
    }
    const accountId = this.ledgerForm.value.account_id;
    if (accountId == null) {
      this.errorMessage = 'اختر حساباً من الشجرة المحاسبية';
      return;
    }
    this.runReport({
      account_id: accountId,
      date_from: this.ledgerForm.value.date_from,
      date_to: this.ledgerForm.value.date_to
    });
  }

  submitCashForm(): void {
    if (this.cashForm.invalid) {
      return;
    }
    const type = this.cashForm.value.entity_type;
    const entityId = this.cashForm.value.entity_id;

    let treeAccountId: number | null = null;
    if (type === 'safe') {
      const safe = this.safes.find((s: any) => String(s.id) === String(entityId));
      treeAccountId = safe?.account_id ?? null;
    } else if (type === 'bank') {
      const bank = this.banks.find((b: any) => String(b.id) === String(entityId));
      treeAccountId = bank?.asset_id ?? null;
    } else if (type === 'service') {
      const svc = this.serviceAccounts.find((a: any) => String(a.id) === String(entityId));
      treeAccountId = svc?.account_id ?? null;
    }

    if (treeAccountId == null) {
      this.errorMessage = 'الحساب المالي غير مرتبط بهذا العنصر';
      return;
    }

    this.runReport({
      account_id: treeAccountId,
      date_from: this.cashForm.value.date_from,
      date_to: this.cashForm.value.date_to
    });
  }

  private runReport(params: { account_id: number; date_from: any; date_to: any }): void {
    this.loading = true;
    this.errorMessage = null;
    this.data = [];
    this.details = null;

    const httpParams: any = { account_id: params.account_id };
    if (params.date_from) {
      httpParams.date_from = params.date_from;
    }
    if (params.date_to) {
      httpParams.date_to = params.date_to;
    }

    this.accountingReportService.getAccountStatement(httpParams).subscribe({
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
}
