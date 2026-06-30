import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { forkJoin } from 'rxjs';
import * as XLSX from 'xlsx';
import { AccountingReportService } from 'src/app/accounting/services/accounting-report.service';
import { SafeService } from 'src/app/accounting/services/safe.service';
import { BankService } from 'src/app/accounting/services/bank.service';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service';
import { PdfService } from 'src/app/pdf.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { AuthService } from 'src/app/auth/auth.service';

export interface AccountStatementEditLink {
  source: string;
  source_id: number;
  url: string;
}

@Component({
  selector: 'app-financial-statement',
  templateUrl: './financial-statement.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './financial-statement.component.css']
})
export class FinancialStatementComponent implements OnInit {

  /** تبويب 1: شجرة الحسابات — تبويب 2: خزن/بنوك/خدمات */
  activeTab: 'ledger' | 'cash' = 'ledger';

  data: any[] = [];
  details: any = null;
  totals: any = null;

  loading = false;
  errorMessage: string | null = null;
  lastSearchParams: { date_from?: string | null; date_to?: string | null } | null = null;

  // ——— تبويب الشجرة ———
  /** كل عُقد الشجرة (رئيسية وفرعية) لاختيار كشف الحساب */
  allTreeAccounts: any[] = [];
  filteredAccounts: any[] = [];
  accountSearchTerm = '';

  accountSourceOptions = [
    { value: 'all', label: 'كل الحسابات' },
    { value: 'asset', label: 'أصول' },
    { value: 'liability', label: 'خصوم' },
    { value: 'expense', label: 'مصروفات' },
    { value: 'revenue', label: 'إيرادات' },
    { value: 'equity', label: 'حقوق ملكية' },
    { value: 'settlement', label: 'حسابات تسوية' },
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
    private pdfService: PdfService,
    private route: ActivatedRoute,
    private router: Router,
    private rbac: RbacService,
    private authService: AuthService,
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
    this.stripTrailingQuestionMarkOnly();

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
  }

  /** يزيل ? من شريط العنوان عندما لا توجد query params (مثلاً financialstatement?) */
  private stripTrailingQuestionMarkOnly(): void {
    if (this.route.snapshot.queryParamMap.keys.length > 0) {
      return;
    }
    const clean = this.router.url.split('?')[0];
    if (clean && clean !== this.router.url) {
      this.router.navigateByUrl(clean, { replaceUrl: true });
    }
  }

  private clearReportOnly(): void {
    this.data = [];
    this.details = null;
    this.totals = null;
    this.lastSearchParams = null;
    this.errorMessage = null;
  }

  get canExport(): boolean {
    return !!this.details && !this.loading;
  }

  get canAdminEditEntries(): boolean {
    return this.rbac.canAny(['finance.account_statement.edit', 'system.rbac'])
      || this.authService.getUser() === 'Admin';
  }

  get hasEditableRows(): boolean {
    return this.data.some((item) => this.isEntryEditable(item));
  }

  isEntryEditable(item: { can_edit?: boolean; edit_link?: AccountStatementEditLink | null }): boolean {
    return item?.can_edit === true && !!item?.edit_link?.url;
  }

  openEntry(item: { can_edit?: boolean; edit_link?: AccountStatementEditLink | null }): void {
    if (!this.isEntryEditable(item) || !item.edit_link?.url) {
      return;
    }
    window.open(item.edit_link.url, '_blank', 'noopener');
  }

  exportToPdf(): void {
    if (!this.canExport || !this.details) {
      return;
    }

    void this.pdfService.generateAccountStatementPdf(
      {
        fileName: this.buildExportFileName(),
        accountCode: String(this.details.account?.code ?? ''),
        accountName: String(this.details.account?.name ?? ''),
        dateFrom: this.lastSearchParams?.date_from ?? null,
        dateTo: this.lastSearchParams?.date_to ?? null,
        consolidated: this.details.consolidated === true,
        accountsInScope: this.details.accounts_in_scope,
        openingBalance: this.details.opening_balance ?? 0,
        totalDebit: this.totals?.debit ?? 0,
        totalCredit: this.totals?.credit ?? 0,
        closingBalance: this.details.closing_balance ?? 0,
        entries: this.data.map((item) => ({
          entryDate: item.entry_date || item.created_at,
          createdAt: item.created_at,
          description: item.description ?? '',
          userName: item.user_name ?? '',
          debit: item.debit ?? 0,
          credit: item.credit ?? 0,
          runningBalance: item.running_balance ?? 0,
          subAccountCode: item.account?.code,
          subAccountName: item.account?.name,
        })),
      },
      'download'
    );
  }

  exportToExcel(): void {
    if (!this.canExport || !this.details) {
      return;
    }

    const consolidated = this.details.consolidated === true;
    const rows: unknown[][] = [
      ['كشف حساب تفصيلي'],
      [`الحساب: (${this.details.account?.code ?? ''}) ${this.details.account?.name ?? ''}`],
      [
        `من تاريخ: ${this.lastSearchParams?.date_from ?? ''}`,
        `إلى تاريخ: ${this.lastSearchParams?.date_to ?? ''}`
      ]
    ];

    if (consolidated) {
      rows.push([`عرض مجمّع — ${this.details.accounts_in_scope ?? 0} حساب في النطاق`]);
    }

    rows.push(
      [],
      ['الرصيد الافتتاحي', this.details.opening_balance ?? 0],
      ['إجمالي مدين (وارد)', this.totals?.debit ?? 0],
      ['إجمالي دائن (صادر)', this.totals?.credit ?? 0],
      ['الرصيد الحالي', this.details.closing_balance ?? 0],
      []
    );

    const headers = ['التاريخ', 'الوقت'];
    if (consolidated) {
      headers.push('الحساب الفرعي');
    }
    headers.push('البيان / الشرح', 'المستخدم', 'مدين', 'دائن', 'الرصيد المتحرك');
    rows.push(headers);

    for (const item of this.data) {
      const datePart = this.formatExportDatePart(item.entry_date || item.created_at);
      const timePart = this.formatExportTimePart(item.created_at);
      const row: unknown[] = [datePart, timePart];
      if (consolidated) {
        row.push(`${item.account?.code ?? ''} — ${item.account?.name ?? ''}`);
      }
      row.push(
        item.description ?? '',
        item.user_name ?? '',
        item.debit > 0 ? item.debit : '',
        item.credit > 0 ? item.credit : '',
        item.running_balance ?? ''
      );
      rows.push(row);
    }

    const totalRow: unknown[] = ['', ''];
    if (consolidated) {
      totalRow.push('');
    }
    totalRow.push(
      'الإجمالي',
      this.totals?.debit ?? 0,
      this.totals?.credit ?? 0,
      this.details.closing_balance ?? 0
    );
    rows.push(totalRow);

    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = headers.map(() => ({ width: 18 }));

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'كشف حساب');
    if (!wb.Workbook) {
      wb.Workbook = { Views: [{}] };
    }
    if (!wb.Workbook.Views) {
      wb.Workbook.Views = [{}];
    }
    wb.Workbook.Views[0].RTL = true;

    XLSX.writeFile(wb, `${this.buildExportFileName()}.xlsx`);
  }

  private buildExportFileName(): string {
    const code = this.details?.account?.code ?? 'account';
    const from = this.lastSearchParams?.date_from ?? 'from';
    const to = this.lastSearchParams?.date_to ?? 'to';
    return `كشف_حساب_${code}_${from}_${to}`;
  }

  private formatExportDatePart(value: unknown): string {
    if (!value) {
      return '';
    }
    const d = new Date(String(value));
    if (Number.isNaN(d.getTime())) {
      return String(value);
    }
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}/${month}/${year}`;
  }

  private formatExportTimePart(value: unknown): string {
    if (!value) {
      return '';
    }
    const d = new Date(String(value));
    if (Number.isNaN(d.getTime())) {
      return '';
    }
    const hours = String(d.getHours()).padStart(2, '0');
    const mins = String(d.getMinutes()).padStart(2, '0');
    return `${hours}:${mins}`;
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
        this.allTreeAccounts = this.flattenTree(tree);
        this.updateFilteredAccounts();
      },
      error: (err) => {
        console.error('خطأ في تحميل الشجرة المحاسبية:', err);
        this.allTreeAccounts = [];
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
    let base: any[] = [...this.allTreeAccounts];

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
    } else if (sourceType === 'settlement') {
      base = base.filter((a) => a.type === 'settlement');
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

    this.lastSearchParams = {
      date_from: params.date_from,
      date_to: params.date_to
    };

    this.accountingReportService.getAccountStatement(httpParams).subscribe({
      next: (res: any) => {
        this.loading = false;
        this.data = res?.entries || [];
        this.details = {
          opening_balance: res?.opening_balance ?? 0,
          closing_balance: res?.closing_balance ?? 0,
          account: res?.account,
          consolidated: res?.consolidated === true,
          accounts_in_scope: res?.accounts_in_scope
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
