import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators, FormArray } from '@angular/forms';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { ExpenseService } from '../services/expense.service';
import { PaymentSourcesService } from 'src/app/accounting/services/payment-sources.service';
import { AccountingReportService } from 'src/app/accounting/services/accounting-report.service';
import { ActivatedRoute, Router } from '@angular/router';
import { dateToIsoString } from 'src/app/shared/date/date-utils';

interface AccountOption {
  id: number;
  code: string;
  name: string;
  type: string;
  label: string;
}

@Component({
  selector: 'app-add-expense',
  templateUrl: './add-expense.component.html',
  styleUrls: ['./add-expense.component.css']
})
export class AddExpenseComponent implements OnInit{

  errormessage:boolean=false;
  submitError = '';
  safesData:any[]=[];
  banksData:any[]=[];
  serviceAccountsData:any[]=[];
  leafAccounts: AccountOption[] = [];
  private lineAccountCtrls = new Map<number, FormControl<string | AccountOption>>();
  private lineFilteredAccounts = new Map<number, AccountOption[]>();
  readonly accountAutocompleteCap = 400;
  dateFrom: string = dateToIsoString(new Date()) || '';
  time!:string;
  paymentType: 'safe' | 'bank' | 'service_account' = 'safe';
  isEditMode = false;
  expenseId: number | null = null;
  loadingExpense = false;
  loadingAccounts = false;

  constructor(
    private paymentSourcesService: PaymentSourcesService,
    private accountingReportService: AccountingReportService,
    private expenseService: ExpenseService,
    private route: Router,
    private activatedRoute: ActivatedRoute,
  ){
    this.time = this.getCurrentTime();
  }

  getCurrentTime(): string {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    return `${hours}:${minutes}:${seconds}`;
  }

  ngOnInit(): void {
    const idParam = this.activatedRoute.snapshot.paramMap.get('id');
    this.isEditMode = !!idParam;
    this.expenseId = idParam ? Number(idParam) : null;

    this.form.patchValue({
      payment_type: 'safe',
      created_at: this.dateFrom,
    });
    this.paymentType = 'safe';

    if (!this.isEditMode) {
      this.addSplitLine();
    }

    this.splitLines.valueChanges.subscribe(() => this.syncTotalFromLines());

    this.loadingAccounts = true;
    this.accountingReportService.getAccountingTree().subscribe({
      next: (response: any) => {
        this.loadingAccounts = false;
        const tree = Array.isArray(response) ? response : [];
        const flat = this.flattenTree(tree);
        this.leafAccounts = flat
          .filter((a: any) => !a.children || !a.children.length)
          .map((a: any) => this.toAccountOption(a))
          .sort((a, b) =>
            a.label.localeCompare(b.label, 'ar', { numeric: true })
          );

        if (this.isEditMode && this.expenseId) {
          this.loadExpenseForEdit(this.expenseId);
        }
      },
      error: () => {
        this.loadingAccounts = false;
        this.submitError = 'تعذر تحميل شجرة الحسابات';
      },
    });

    this.paymentSourcesService.getPaymentSources().subscribe((res: any) => {
      this.safesData = res.safes || [];
      this.banksData = res.banks || [];
      this.serviceAccountsData = res.service_accounts || [];
    });
  }

  private expenseLoaded = false;

  private loadExpenseForEdit(id: number): void {
    if (this.expenseLoaded || this.loadingExpense || !this.leafAccounts.length) {
      return;
    }
    this.loadingExpense = true;
    this.expenseService.getByID(id).subscribe({
      next: (res) => {
        this.loadingExpense = false;
        this.expenseLoaded = true;
        if (!res || Number(res.status) === 1 || Number(res.amount) < 0) {
          this.route.navigate(['/dashboard/financial/expenses']);
          return;
        }

        const pt = this.resolvePaymentType(res);
        this.paymentType = pt;

        let createdDate = this.dateFrom;
        if (res.created_at) {
          const raw = String(res.created_at);
          createdDate = raw.includes('T') ? raw.slice(0, 10) : raw.slice(0, 10);
        }

        this.form.patchValue({
          payment_type: pt,
          safe_id: res.safe_id ?? null,
          bank_id: res.bank_id ?? null,
          service_account_id: res.service_account_id ?? null,
          expens_statement: res.expens_statement,
          note: res.note,
          created_at: createdDate,
        });

        while (this.splitLines.length) {
          this.splitLines.removeAt(0);
        }

        const sourceLines = Array.isArray(res.lines) && res.lines.length > 0
          ? res.lines
          : [{
              expense_type: res.expense_type,
              tree_account_id: res.tree_account_id ?? res.debit_tree_account?.id ?? null,
              amount: res.amount,
            }];

        for (const line of sourceLines) {
          const treeAccountId = line.tree_account_id
            ?? line.debit_tree_account?.id
            ?? line.tree_account?.id
            ?? null;
          this.splitLines.push(new FormGroup({
            expense_type: new FormControl(line.expense_type, Validators.required),
            tree_account_id: new FormControl(treeAccountId ? Number(treeAccountId) : null, Validators.required),
            statement: new FormControl(line.statement ?? res.expens_statement ?? '', Validators.required),
            amount: new FormControl(Number(line.amount), [Validators.required, Validators.min(0.01)]),
          }));
        }
        this.rebuildLineAccountMaps();
        this.syncTotalFromLines();
      },
      error: () => {
        this.loadingExpense = false;
        this.route.navigate(['/dashboard/financial/expenses']);
      },
    });
  }

  private resolvePaymentType(row: any): 'safe' | 'bank' | 'service_account' {
    if (row?.payment_type === 'safe' || row?.payment_type === 'bank' || row?.payment_type === 'service_account') {
      return row.payment_type;
    }
    if (row?.safe_id) {
      return 'safe';
    }
    if (row?.service_account_id) {
      return 'service_account';
    }
    return 'bank';
  }

  get splitLines(): FormArray {
    return this.form.get('lines') as FormArray;
  }

  createSplitLineGroup(): FormGroup {
    return new FormGroup({
      expense_type: new FormControl('مصروف تشغيل', Validators.required),
      tree_account_id: new FormControl(null, Validators.required),
      statement: new FormControl('', Validators.required),
      amount: new FormControl(null, [Validators.required, Validators.min(0.01)]),
    });
  }

  addSplitLine(): void {
    this.splitLines.push(this.createSplitLineGroup());
    const idx = this.splitLines.length - 1;
    this.getLineAccountCtrl(idx);
    this.lineFilteredAccounts.set(idx, this.filterAccountOptions(''));
    this.syncTotalFromLines();
  }

  removeSplitLine(index: number): void {
    if (this.splitLines.length <= 1) {
      return;
    }
    this.splitLines.removeAt(index);
    this.rebuildLineAccountMaps();
    this.syncTotalFromLines();
  }

  onPaymentTypeChange(): void {
    this.paymentType = this.form.get('payment_type')?.value || 'safe';
    this.form.patchValue({
      safe_id: null,
      bank_id: null,
      service_account_id: null,
    });
  }

  getLineAccountCtrl(index: number): FormControl<string | AccountOption> {
    if (!this.lineAccountCtrls.has(index)) {
      const ctrl = new FormControl<string | AccountOption>('', { nonNullable: true });
      ctrl.valueChanges.subscribe((v) => {
        this.lineFilteredAccounts.set(
          index,
          this.filterAccountOptions(typeof v === 'string' ? v : '')
        );
      });
      this.lineAccountCtrls.set(index, ctrl);
      this.lineFilteredAccounts.set(index, this.filterAccountOptions(''));
    }
    return this.lineAccountCtrls.get(index)!;
  }

  getLineFilteredAccounts(index: number): AccountOption[] {
    return this.lineFilteredAccounts.get(index) ?? this.filterAccountOptions('');
  }

  displayAccountOption = (value: string | AccountOption | null): string => {
    if (!value) {
      return '';
    }
    if (typeof value === 'string') {
      return value;
    }
    return value.label;
  };

  accountOptionLabel(acc: AccountOption): string {
    return acc.label;
  }

  onLineAccountFocus(index: number): void {
    const ctrl = this.getLineAccountCtrl(index);
    const v = ctrl.value;
    this.lineFilteredAccounts.set(
      index,
      this.filterAccountOptions(typeof v === 'string' ? v : '')
    );
  }

  onLineAccountSelected(index: number, event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as AccountOption;
    const row = this.splitLines.at(index) as FormGroup;
    row.patchValue({ tree_account_id: acc.id });
    this.getLineAccountCtrl(index).setValue(acc, { emitEvent: false });
    this.lineFilteredAccounts.set(index, this.filterAccountOptions(''));
  }

  onLineAccountBlur(index: number): void {
    setTimeout(() => this.syncLineAccountOnBlur(index), 150);
  }

  private syncLineAccountOnBlur(index: number): void {
    const ctrl = this.getLineAccountCtrl(index);
    const row = this.splitLines.at(index) as FormGroup;
    const v = ctrl.value;

    if (v && typeof v === 'object') {
      row.patchValue({ tree_account_id: v.id });
      return;
    }

    const str = typeof v === 'string' ? v.trim() : '';
    if (!str) {
      row.patchValue({ tree_account_id: null });
      return;
    }

    const exact = this.leafAccounts.find((a) => a.label === str);
    if (exact) {
      row.patchValue({ tree_account_id: exact.id });
      ctrl.setValue(exact, { emitEvent: false });
      return;
    }

    const partial = this.filterAccountOptions(str);
    if (partial.length === 1) {
      row.patchValue({ tree_account_id: partial[0].id });
      ctrl.setValue(partial[0], { emitEvent: false });
    }
  }

  private filterAccountOptions(term: string): AccountOption[] {
    const raw = String(term ?? '').trim();
    const q = raw.toLowerCase();
    let list = this.leafAccounts;

    if (q) {
      const words = q.split(/\s+/).filter(Boolean);
      list = list.filter((a) => {
        const typeAr = this.accountTypeLabel(a.type);
        const haystack = `${a.id} ${a.code} ${a.name} ${a.type} ${typeAr} ${a.label}`.toLowerCase();
        return words.every((word) => haystack.includes(word))
          || haystack.includes(q)
          || String(a.id).includes(raw);
      });
    }

    return list.slice(0, this.accountAutocompleteCap);
  }

  private toAccountOption(a: any): AccountOption {
    const code = a.code != null && String(a.code).trim() !== '' ? String(a.code) : '';
    const name = String(a.name ?? '');
    const type = String(a.type ?? '').trim().toLowerCase();
    const typeAr = this.accountTypeLabel(type);
    const typeSuffix = typeAr ? ` (${typeAr})` : '';
    return {
      id: Number(a.id),
      code,
      name,
      type,
      label: code ? `${code} · ${name}${typeSuffix}` : `${name}${typeSuffix}`,
    };
  }

  private accountTypeLabel(type: string): string {
    const labels: Record<string, string> = {
      asset: 'أصول',
      liability: 'خصوم',
      equity: 'حقوق ملكية',
      revenue: 'إيرادات',
      income: 'إيرادات',
      expense: 'مصروفات',
      settlement: 'تسوية',
    };
    const key = type.trim().toLowerCase();
    return labels[key] ?? (type ? type : '');
  }

  private findAccountOption(id: number): AccountOption | undefined {
    return this.leafAccounts.find((a) => a.id === id);
  }

  private rebuildLineAccountMaps(): void {
    this.lineAccountCtrls.clear();
    this.lineFilteredAccounts.clear();
    for (let i = 0; i < this.splitLines.length; i++) {
      const id = (this.splitLines.at(i) as FormGroup).get('tree_account_id')?.value;
      const ctrl = this.getLineAccountCtrl(i);
      if (id) {
        const acc = this.findAccountOption(Number(id));
        if (acc) {
          ctrl.setValue(acc, { emitEvent: false });
        }
      } else {
        ctrl.setValue('', { emitEvent: false });
      }
      this.lineFilteredAccounts.set(i, this.filterAccountOptions(''));
    }
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

  get linesTotal(): number {
    return this.splitLines.controls.reduce((sum, ctrl) => {
      const v = parseFloat((ctrl as FormGroup).get('amount')?.value);
      return sum + (isNaN(v) ? 0 : v);
    }, 0);
  }

  private syncTotalFromLines(): void {
    const total = Math.round(this.linesTotal * 100) / 100;
    const amountCtrl = this.form.get('amount');
    if (total > 0) {
      amountCtrl?.setValue(total, { emitEvent: false });
      amountCtrl?.setErrors(null);
    } else {
      amountCtrl?.setValue(null, { emitEvent: false });
    }
  }

  form:FormGroup = new FormGroup({
    'payment_type' : new FormControl('safe'),
    'safe_id' : new FormControl(null),
    'bank_id' : new FormControl(null),
    'service_account_id' : new FormControl(null),
    'expens_statement' : new FormControl(null, [Validators.required]),
    'amount' : new FormControl(null, [Validators.required, Validators.min(0.01)]),
    'note' : new FormControl(null, [Validators.required]),
    'created_at' : new FormControl(null, [Validators.required]),
    'expense_image' : new FormControl(null),
    'lines': new FormArray([]),
  })

  imgtext:string="صورة "
  fileopend:boolean=false;

  openFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
      this.fileopend=true;
    }
  }

  selectedFile: any;
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.imgtext = this.selectedFile?.name || 'No image selected';
  }


  get isSourceSelected(): boolean {
    const pt = this.form.get('payment_type')?.value;
    if (pt === 'safe') return !!this.form.get('safe_id')?.value;
    if (pt === 'bank') return !!this.form.get('bank_id')?.value;
    if (pt === 'service_account') return !!this.form.get('service_account_id')?.value;
    return false;
  }

  get splitLinesValid(): boolean {
    return this.splitLines.length > 0 && this.splitLines.valid;
  }

  get canSubmit(): boolean {
    return !this.loadingExpense
      && !this.loadingAccounts
      && this.leafAccounts.length > 0
      && this.isSourceSelected
      && this.form.valid
      && this.splitLinesValid
      && this.linesTotal > 0;
  }

  private buildFormData(): FormData {
    const data = this.form.value;
    const lines = (data.lines || []).map((row: any) => ({
      expense_type: row.expense_type,
      tree_account_id: Number(row.tree_account_id),
      statement: row.statement,
      amount: Number(row.amount),
    }));

    const formData = new FormData();
    formData.append('payment_type', data.payment_type || 'safe');
    formData.append('expens_statement', data.expens_statement);
    formData.append('amount', String(data.amount ?? this.linesTotal));
    formData.append('note', data.note);
    formData.append('address', data.expens_statement || '');
    formData.append('created_at', `${data.created_at} ${this.time}`);
    formData.append('lines', JSON.stringify(lines));

    if (data.payment_type === 'safe' && data.safe_id) {
      formData.append('safe_id', data.safe_id);
    } else if (data.payment_type === 'bank' && data.bank_id) {
      formData.append('bank_id', data.bank_id);
    } else if (data.payment_type === 'service_account' && data.service_account_id) {
      formData.append('service_account_id', data.service_account_id);
    }

    if (this.selectedFile) {
      formData.append('expense_image', this.selectedFile, this.selectedFile.name);
    }

    return formData;
  }

  submitform(){
    if (!this.canSubmit) {
      this.errormessage = true;
      this.form.markAllAsTouched();
      this.splitLines.markAllAsTouched();
      return;
    }

    this.submitError = '';
    const formData = this.buildFormData();
    const request$ = this.isEditMode && this.expenseId
      ? this.expenseService.edit(this.expenseId, formData)
      : this.expenseService.add(formData);

    request$.subscribe({
      next: (result) => {
        if (result) {
          this.route.navigate(['/dashboard/financial/expenses']);
        }
      },
      error: (err) => {
        this.submitError = err?.error?.message || 'تعذر حفظ المصروف';
        this.errormessage = true;
      },
    });
  }
}
