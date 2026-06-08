import { Component, OnInit } from '@angular/core';
import { FormControl } from '@angular/forms';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { SafeService } from '../services/safe.service';
import { TreeAccountService } from '../services/tree-account.service';

interface AccountOption {
  id: number;
  label: string;
}

@Component({
  selector: 'app-safe-deposit-withdraw',
  template: `
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>سحب وإيداع نقدي (خزينة)</h2>
      </div>
      <div class="alert alert-info">
        ملاحظة: هذه العملية تنتج قيد يومي وتسجل حركة في الخزينة والحساب المقابل.
      </div>
      <div class="card p-3">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label>الخزينة</label>
            <select class="form-control" [(ngModel)]="safeId">
              <option [ngValue]="null" disabled>اختر الخزينة</option>
              <option *ngFor="let safe of safes" [ngValue]="safe.id">
                {{ safe.name }} ({{ safe.balance }})
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
            <span *ngIf="!loading">حفظ</span>
          </button>
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
export class SafeDepositWithdrawComponent implements OnInit {
  safes: any[] = [];
  accountOptions: AccountOption[] = [];
  filteredCounterAccounts: AccountOption[] = [];
  counterAccountCtrl = new FormControl<string | AccountOption>('', { nonNullable: true });
  readonly accountAutocompleteCap = 400;

  loading = false;
  safeId: number | null = null;
  type: 'receipt' | 'payment' = 'receipt';
  counterAccountId: number | null = null;
  amount = 0;
  date = new Date().toISOString().split('T')[0];
  notes = '';

  constructor(
    private safeService: SafeService,
    private treeAccountService: TreeAccountService
  ) {}

  ngOnInit(): void {
    this.getSafes();
    this.getTreeAccounts();
    this.counterAccountCtrl.valueChanges.subscribe(v => {
      this.applyCounterAccountFilter(typeof v === 'string' ? v : '');
    });
  }

  displayAccountOption = (value: string | AccountOption | null): string => {
    if (!value) return '';
    return typeof value === 'string' ? value : value.label;
  };

  getSafes(): void {
    this.safeService.getAll().subscribe(res => {
      this.safes = res.data || (Array.isArray(res) ? res : []);
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

  submit(): void {
    this.syncCounterAccountOnBlur();

    if (!this.safeId || !this.resolveCounterAccountId() || this.amount <= 0) {
      alert('يرجى تعبئة جميع الحقول واختيار حساب مقابل صحيح من القائمة');
      return;
    }

    this.loading = true;
    const payload = {
      safe_id: Number(this.safeId),
      type: this.type,
      counter_account_id: Number(this.counterAccountId),
      amount: Number(this.amount),
      date: this.date,
      notes: this.notes
    };

    this.safeService.directTransaction(payload).subscribe({
      next: () => {
        alert('تمت العملية بنجاح');
        this.loading = false;
        this.amount = 0;
        this.notes = '';
        this.counterAccountId = null;
        this.counterAccountCtrl.setValue('', { emitEvent: false });
        this.applyCounterAccountFilter('');
        this.getSafes();
      },
      error: (err: any) => {
        alert(err.error?.message || err.error?.errors?.counter_account_id?.[0] || 'حدث خطأ');
        this.loading = false;
      }
    });
  }
}
