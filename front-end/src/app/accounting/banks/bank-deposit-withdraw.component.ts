import { Component, OnInit } from '@angular/core';
import { FormControl } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { BankService } from '../services/bank.service';
import { TreeAccountService } from '../services/tree-account.service';

interface AccountOption {
  id: number;
  label: string;
}

interface DirectTxnRow {
  id: number;
  bank_id: number;
  bank_name?: string;
  type: 'receipt' | 'payment';
  counter_account_id: number;
  counter_account_name?: string;
  counter_account_code?: string;
  amount: number;
  date: string;
  notes?: string;
  editable: boolean;
  user_name?: string;
}

@Component({
  selector: 'app-bank-deposit-withdraw',
  template: `
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>سحب وإيداع نقدي (بنك)</h2>
        <button *ngIf="editingId" type="button" class="btn btn-outline-secondary btn-sm" (click)="cancelEdit()">
          إلغاء التعديل — عملية جديدة
        </button>
      </div>
      <div class="alert alert-info">
        ملاحظة: تُسجّل قيوداً على حساب البنك والحساب المقابل. يمكن تعديل العمليات المسجّلة من الجدول أدناه (عكس القيود القديمة ثم إنشاء قيود جديدة).
      </div>
      <div class="card p-3 mb-4">
        <h5 class="mb-3">{{ editingId ? 'تعديل عملية #' + editingId : 'عملية جديدة' }}</h5>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label>البنك</label>
            <select class="form-control" [(ngModel)]="bankId">
              <option [ngValue]="null" disabled>اختر البنك</option>
              <option *ngFor="let bank of banks" [ngValue]="bank.id">
                {{ bank.name }} ({{ bank.balance }})
              </option>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label>العملية</label>
            <select class="form-control" [(ngModel)]="type">
              <option value="receipt">إيداع (قبض)</option>
              <option value="payment">سحب (صرف)</option>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label>الحساب المقابل (مصدر/وجهة الأموال)</label>
            <mat-form-field appearance="outline" class="w-100 banks-account-field" dir="rtl">
              <input
                matInput
                [formControl]="counterAccountCtrl"
                [matAutocomplete]="counterAccAuto"
                (focus)="onCounterAccountFocus()"
                (blur)="onCounterAccountBlur()"
                placeholder="ابحث بالاسم أو رمز الحساب"
                autocomplete="off"
              />
              <mat-autocomplete
                #counterAccAuto="matAutocomplete"
                panelClass="banks-account-autocomplete-panel"
                [displayWith]="displayAccountOption"
                (optionSelected)="onCounterAccountSelected($event)"
                [autoActiveFirstOption]="true"
              >
                <mat-option *ngFor="let acc of filteredCounterAccounts" [value]="acc">
                  {{ acc.label }}
                </mat-option>
              </mat-autocomplete>
            </mat-form-field>
          </div>
          <div class="col-md-6 mb-3">
            <label>المبلغ</label>
            <input type="number" class="form-control" [(ngModel)]="amount" min="0.01" step="0.01" />
          </div>
          <div class="col-md-6 mb-3">
            <label>التاريخ</label>
            <input type="date" class="form-control" [(ngModel)]="date" />
          </div>
          <div class="col-12 mb-3">
            <label>ملاحظات</label>
            <textarea class="form-control" [(ngModel)]="notes"></textarea>
          </div>
        </div>
        <div class="text-end">
          <button class="btn btn-primary" (click)="submit()" [disabled]="loading">
            <span *ngIf="loading">جاري التنفيذ...</span>
            <span *ngIf="!loading">{{ editingId ? 'حفظ التعديل' : 'حفظ' }}</span>
          </button>
        </div>
      </div>

      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">سجل العمليات</h5>
          <div class="d-flex gap-2 align-items-center">
            <select class="form-control form-control-sm" style="width: 180px" [(ngModel)]="filterBankId" (change)="loadHistory()">
              <option [ngValue]="null">كل البنوك</option>
              <option *ngFor="let bank of banks" [ngValue]="bank.id">{{ bank.name }}</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-primary" (click)="loadHistory()">تحديث</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>التاريخ</th>
                <th>البنك</th>
                <th>العملية</th>
                <th>الحساب المقابل</th>
                <th>المبلغ</th>
                <th>ملاحظات</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr *ngIf="historyLoading">
                <td colspan="8" class="text-center text-muted">جاري التحميل...</td>
              </tr>
              <tr *ngIf="!historyLoading && historyRows.length === 0">
                <td colspan="8" class="text-center text-muted">لا توجد عمليات مسجّلة قابلة للتعديل</td>
              </tr>
              <tr *ngFor="let row of historyRows">
                <td>{{ row.id }}</td>
                <td>{{ row.date }}</td>
                <td>{{ row.bank_name }}</td>
                <td>
                  <span class="badge" [ngClass]="row.type === 'receipt' ? 'bg-success' : 'bg-warning text-dark'">
                    {{ row.type === 'receipt' ? 'إيداع' : 'سحب' }}
                  </span>
                </td>
                <td>{{ row.counter_account_name }}{{ row.counter_account_code ? ' — ' + row.counter_account_code : '' }}</td>
                <td>{{ row.amount | number:'1.2-2' }}</td>
                <td>{{ row.notes || '—' }}</td>
                <td>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    [disabled]="!row.editable"
                    (click)="startEdit(row)"
                    [title]="row.editable ? '' : 'عملية قديمة غير مرتبطة بسجل قيود'"
                  >
                    تعديل
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  `,
  styles: [
    `
      .banks-account-field {
        width: 100%;
        margin-bottom: 0;
      }
      .banks-account-field ::ng-deep .mat-mdc-form-field-subscript-wrapper {
        margin-top: 4px;
      }
    `
  ]
})
export class BankDepositWithdrawComponent implements OnInit {
  banks: any[] = [];
  accountOptions: AccountOption[] = [];
  filteredCounterAccounts: AccountOption[] = [];
  counterAccountCtrl = new FormControl<string | AccountOption>('', { nonNullable: true });
  readonly accountAutocompleteCap = 400;

  loading = false;
  historyLoading = false;
  historyRows: DirectTxnRow[] = [];
  filterBankId: number | null = null;
  editingId: number | null = null;

  bankId: number | null = null;
  type: 'receipt' | 'payment' = 'receipt';
  counterAccountId: number | null = null;
  amount = 0;
  date = new Date().toISOString().split('T')[0];
  notes = '';

  constructor(
    private bankService: BankService,
    private treeAccountService: TreeAccountService,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.getBanks();
    this.getTreeAccounts();
    this.counterAccountCtrl.valueChanges.subscribe(v => {
      this.applyCounterAccountFilter(typeof v === 'string' ? v : '');
    });
    this.route.queryParamMap.subscribe(params => {
      const editId = Number(params.get('edit') ?? 0);
      if (editId > 0) {
        this.pendingEditId = editId;
        this.tryOpenPendingEdit();
      }
    });
  }

  private pendingEditId: number | null = null;

  private tryOpenPendingEdit(): void {
    if (!this.pendingEditId || this.historyLoading) {
      return;
    }
    const row = this.historyRows.find(r => Number(r.id) === this.pendingEditId);
    if (row) {
      this.startEdit(row);
      this.pendingEditId = null;
      return;
    }
    this.bankService.getDirectTransaction(this.pendingEditId).subscribe({
      next: res => {
        const data = res?.data;
        if (data) {
          this.startEdit(data as DirectTxnRow);
        }
        this.pendingEditId = null;
      },
      error: () => {
        this.pendingEditId = null;
      }
    });
  }

  displayAccountOption = (value: string | AccountOption | null): string => {
    if (!value) return '';
    return typeof value === 'string' ? value : value.label;
  };

  getBanks(): void {
    this.bankService.getAll().subscribe(res => {
      this.banks = res.data || (Array.isArray(res) ? res : []);
      this.loadHistory();
    });
  }

  loadHistory(): void {
    this.historyLoading = true;
    const params: Record<string, string | number> = { per_page: 50 };
    if (this.filterBankId) {
      params['bank_id'] = this.filterBankId;
    }
    this.bankService.listDirectTransactions(params).subscribe({
      next: res => {
        this.historyRows = res.data || [];
        this.historyLoading = false;
        this.tryOpenPendingEdit();
      },
      error: () => {
        this.historyRows = [];
        this.historyLoading = false;
      }
    });
  }

  getTreeAccounts(): void {
    this.treeAccountService.getAll().subscribe({
      next: res => {
        const raw = (res as any)?.data;
        const arr = Array.isArray(raw) ? raw : raw ? [raw] : Array.isArray(res) ? res : [];
        const flat = this.flattenAccounts(arr);
        if (flat.length > 0) {
          this.setAccountOptions(flat);
          return;
        }
        this.treeAccountService.getTree().subscribe({
          next: (treeRes: any) => {
            const t = treeRes?.data ?? treeRes;
            const tArr = Array.isArray(t) ? t : t ? [t] : [];
            this.setAccountOptions(this.flattenAccounts(tArr));
          },
          error: () => alert('تعذر تحميل شجرة الحسابات')
        });
      },
      error: () => alert('تعذر تحميل شجرة الحسابات')
    });
  }

  private setAccountOptions(options: AccountOption[]): void {
    this.accountOptions = options;
    this.applyCounterAccountFilter('');
  }

  private flattenAccounts(nodes: any[]): AccountOption[] {
    const out: AccountOption[] = [];
    const walk = (list: any[]) => {
      for (const n of list || []) {
        if (n?.id != null && n?.name) {
          const code = n.code != null && n.code !== '' ? String(n.code) : '';
          out.push({
            id: Number(n.id),
            label: code ? `${n.name} — ${code}` : String(n.name)
          });
        }
        if (Array.isArray(n?.children) && n.children.length) {
          walk(n.children);
        }
      }
    };
    walk(nodes);
    return out.sort((a, b) => a.label.localeCompare(b.label, 'ar'));
  }

  private applyCounterAccountFilter(term: string): void {
    this.filteredCounterAccounts = this.filterAccountOptions(term);
  }

  private filterAccountOptions(term: string): AccountOption[] {
    const raw = String(term ?? '').trim();
    const q = raw.toLowerCase();
    let list = this.accountOptions;
    if (q) {
      list = list.filter(a => {
        if (String(a.id).includes(raw)) return true;
        return a.label.toLowerCase().includes(q) || a.label.includes(raw);
      });
    }
    return list.slice(0, this.accountAutocompleteCap);
  }

  onCounterAccountFocus(): void {
    const v = this.counterAccountCtrl.value;
    this.applyCounterAccountFilter(typeof v === 'string' ? v : '');
  }

  onCounterAccountSelected(event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as AccountOption;
    if (!acc?.id) return;
    this.counterAccountId = acc.id;
    this.counterAccountCtrl.setValue(acc, { emitEvent: false });
    this.applyCounterAccountFilter('');
  }

  onCounterAccountBlur(): void {
    setTimeout(() => this.syncCounterAccountOnBlur(), 150);
  }

  private syncCounterAccountOnBlur(): void {
    const v = this.counterAccountCtrl.value;
    if (v && typeof v === 'object') {
      this.counterAccountId = v.id;
      return;
    }

    const str = typeof v === 'string' ? v.trim() : '';
    if (!str) {
      this.counterAccountId = null;
      return;
    }

    const exact = this.accountOptions.find(a => a.label === str);
    if (exact) {
      this.counterAccountId = exact.id;
      this.counterAccountCtrl.setValue(exact, { emitEvent: false });
      return;
    }

    this.counterAccountId = null;
    this.counterAccountCtrl.setValue(str, { emitEvent: false });
  }

  private resolveCounterAccountId(): boolean {
    const v = this.counterAccountCtrl.value;
    if (v && typeof v === 'object' && v.id) {
      this.counterAccountId = v.id;
      return true;
    }
    const str = typeof v === 'string' ? v.trim() : '';
    if (!str) {
      this.counterAccountId = null;
      return false;
    }
    const exact = this.accountOptions.find(a => a.label === str);
    if (exact) {
      this.counterAccountId = exact.id;
      this.counterAccountCtrl.setValue(exact, { emitEvent: false });
      return true;
    }
    return this.counterAccountId != null;
  }

  startEdit(row: DirectTxnRow): void {
    if (!row.editable) {
      alert('هذه العملية قديمة ولا يمكن تعديلها تلقائياً — أنشئ عملية تصحيحية جديدة');
      return;
    }
    this.editingId = row.id;
    this.bankId = row.bank_id;
    this.type = row.type;
    this.amount = row.amount;
    this.date = row.date;
    this.notes = row.notes || '';
    this.counterAccountId = row.counter_account_id;
    const opt = this.accountOptions.find(a => a.id === row.counter_account_id);
    if (opt) {
      this.counterAccountCtrl.setValue(opt, { emitEvent: false });
    } else if (row.counter_account_name) {
      const label = row.counter_account_code
        ? `${row.counter_account_name} — ${row.counter_account_code}`
        : row.counter_account_name;
      this.counterAccountCtrl.setValue(label, { emitEvent: false });
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  cancelEdit(): void {
    this.editingId = null;
    this.resetForm();
  }

  private resetForm(): void {
    this.amount = 0;
    this.notes = '';
    this.type = 'receipt';
    this.counterAccountId = null;
    this.counterAccountCtrl.setValue('', { emitEvent: false });
    this.applyCounterAccountFilter('');
    this.date = new Date().toISOString().split('T')[0];
  }

  submit(): void {
    this.syncCounterAccountOnBlur();

    if (!this.bankId || !this.resolveCounterAccountId() || this.amount <= 0) {
      alert('يرجى تعبئة جميع الحقول واختيار حساب مقابل صحيح من القائمة');
      return;
    }

    const selectedBank = this.banks.find(b => Number(b.id) === Number(this.bankId));
    if (!selectedBank?.asset_id) {
      alert('البنك المحدد غير مرتبط بحساب شجري');
      return;
    }

    this.loading = true;
    const payload = {
      bank_id: Number(this.bankId),
      type: this.type,
      counter_account_id: Number(this.counterAccountId),
      amount: Number(this.amount),
      date: this.date,
      notes: this.notes
    };

    const req = this.editingId
      ? this.bankService.updateDirectTransaction(this.editingId, payload)
      : this.bankService.directTransaction(payload);

    req.subscribe({
      next: () => {
        alert(this.editingId ? 'تم تعديل العملية بنجاح' : 'تمت العملية بنجاح');
        this.loading = false;
        this.editingId = null;
        this.resetForm();
        this.getBanks();
      },
      error: err => {
        alert(err.error?.message || err.error?.errors?.counter_account_id?.[0] || 'حدث خطأ');
        this.loading = false;
      }
    });
  }
}
