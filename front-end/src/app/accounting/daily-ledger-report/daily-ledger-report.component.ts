import { Component, OnInit } from '@angular/core';
import { AccountingReportService } from '../services/accounting-report.service';
import { TreeAccountService } from '../services/tree-account.service';
import { DailyEntryService } from '../services/daily-entry.service';
import { AuthService } from 'src/app/auth/auth.service';
import { TreeAccount } from '../interfaces/tree-account.interface';
import { FormBuilder, FormControl, FormGroup } from '@angular/forms';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { catchError, of } from 'rxjs';
import Swal from 'sweetalert2';

interface AccountOption {
  id: number | '';
  label: string;
}

@Component({
  selector: 'app-daily-ledger-report',
  templateUrl: './daily-ledger-report.component.html',
  styleUrls: ['./daily-ledger-report.component.css']
})
export class DailyLedgerReportComponent implements OnInit {
  entries: any[] = [];
  accounts: TreeAccount[] = [];
  accountOptions: AccountOption[] = [];
  filteredAccounts: AccountOption[] = [];
  loading = false;
  readonly allAccountsOption: AccountOption = { id: '', label: 'كل الحسابات' };
  readonly accountAutocompleteCap = 400;
  accountInputCtrl = new FormControl<string | AccountOption>(this.allAccountsOption);

  // Pagination
  currentPage = 1;
  perPage = 25;
  totalPages = 1;
  totalItems = 0;

  // Totals
  totals = {
    total_debit: 0,
    total_credit: 0
  };

  filterForm: FormGroup;
  users: { id: number; name: string }[] = [];

  constructor(
    private reportService: AccountingReportService,
    private treeAccountService: TreeAccountService,
    private dailyEntryService: DailyEntryService,
    private authService: AuthService,
    private fb: FormBuilder
  ) {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    this.filterForm = this.fb.group({
      date_from: [firstDay.toISOString().split('T')[0]],
      date_to: [today.toISOString().split('T')[0]],
      account_id: [''],
      user_id: [null as number | null],
      /** إظهار حركات مرتبطة برأس قيد يومي فقط (مثل القيود من شاشة «القيود اليومية») */
      daily_entry_only: [false]
    });
  }

  ngOnInit(): void {
    this.accountInputCtrl.valueChanges.subscribe((val) => {
      if (typeof val !== 'string') {
        return;
      }
      this.updateFilteredAccounts(val);
      if (!val.trim()) {
        this.filterForm.patchValue({ account_id: '' });
      }
    });
    this.loadAccounts();
    this.initUserFilter();
  }

  private initUserFilter(): void {
    let myId = 0;
    let myName = '';

    this.authService.fetchMe().pipe(
      catchError(() => of(null))
    ).subscribe((me) => {
      myId = Number(me?.id ?? 0);
      myName = String(me?.name ?? '').trim();
      if (myId > 0) {
        this.filterForm.patchValue({ user_id: myId }, { emitEvent: false });
      }
      this.loadEntryUsers(myId, myName);
    });
  }

  private loadEntryUsers(currentUserId = 0, currentUserName = ''): void {
    this.dailyEntryService.getUsers().pipe(
      catchError(() => of({ data: [] as { id: number; name: string }[] }))
    ).subscribe((res) => {
      this.users = Array.isArray(res?.data) ? res.data : [];
      if (currentUserId > 0 && !this.users.some((u) => u.id === currentUserId)) {
        this.users.unshift({
          id: currentUserId,
          name: currentUserName || 'أنا',
        });
      }
      this.loadReport();
    });
  }

  loadAccounts(): void {
    this.treeAccountService.getPickerList().pipe(
      catchError(() => this.treeAccountService.getAll())
    ).subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.accounts = Array.isArray(response.data) ? response.data : [];
          this.buildAccountOptions();
        }
      },
      error: (error) => console.error('Error loading accounts:', error)
    });
  }

  displayAccountOption = (value: string | AccountOption | null): string => {
    if (!value) {
      return '';
    }
    return typeof value === 'string' ? value : value.label;
  };

  onAccountOptionSelected(event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as AccountOption;
    this.filterForm.patchValue({ account_id: acc?.id ?? '' });
    this.accountInputCtrl.setValue(acc ?? this.allAccountsOption, { emitEvent: false });
    this.updateFilteredAccounts('');
  }

  onAccountBlur(): void {
    setTimeout(() => this.syncAccountOnBlur(), 150);
  }

  refreshAccountFilter(): void {
    const v = this.accountInputCtrl.value;
    if (typeof v !== 'string') {
      this.updateFilteredAccounts('');
      return;
    }
    this.updateFilteredAccounts(v);
  }

  private buildAccountOptions(): void {
    this.accountOptions = this.accounts
      .filter((a) => a.id != null && a.name)
      .map((a) => {
        const code = a.code != null ? String(a.code) : '';
        return {
          id: Number(a.id),
          label: code ? `${code} - ${a.name}` : String(a.name)
        };
      })
      .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true }));
    this.updateFilteredAccounts('');
  }

  private updateFilteredAccounts(term: string): void {
    const raw = String(term ?? '').trim();
    const q = raw.toLowerCase();
    let list = this.accountOptions;
    if (q && q !== this.allAccountsOption.label.toLowerCase()) {
      list = list.filter((a) => {
        if (String(a.id).includes(raw)) {
          return true;
        }
        return a.label.toLowerCase().includes(q) || a.label.includes(raw);
      });
    }
    this.filteredAccounts = list.slice(0, this.accountAutocompleteCap);
  }

  private syncAccountOnBlur(): void {
    const v = this.accountInputCtrl.value;
    if (v && typeof v === 'object') {
      this.filterForm.patchValue({ account_id: v.id });
      return;
    }

    const str = typeof v === 'string' ? v.trim() : '';
    if (!str) {
      this.filterForm.patchValue({ account_id: '' });
      this.accountInputCtrl.setValue(this.allAccountsOption, { emitEvent: false });
      this.updateFilteredAccounts('');
      return;
    }

    if (str === this.allAccountsOption.label) {
      this.filterForm.patchValue({ account_id: '' });
      this.accountInputCtrl.setValue(this.allAccountsOption, { emitEvent: false });
      return;
    }

    const exact = this.accountOptions.find((a) => a.label === str);
    if (exact) {
      this.filterForm.patchValue({ account_id: exact.id });
      this.accountInputCtrl.setValue(exact, { emitEvent: false });
      return;
    }

    const partial = this.filteredAccounts;
    if (partial.length === 1) {
      this.filterForm.patchValue({ account_id: partial[0].id });
      this.accountInputCtrl.setValue(partial[0], { emitEvent: false });
      return;
    }

    const selectedId = this.filterForm.get('account_id')?.value;
    const selected = selectedId
      ? this.accountOptions.find((a) => a.id === Number(selectedId))
      : null;
    this.accountInputCtrl.setValue(selected ?? this.allAccountsOption, { emitEvent: false });
  }

  loadReport(): void {
    this.loading = true;
    const filters = this.filterForm.value;

    const params: Record<string, unknown> = {
      date_from: filters.date_from,
      date_to: filters.date_to,
      account_id: filters.account_id,
      page: this.currentPage,
      per_page: this.perPage
    };
    if (filters.daily_entry_only) {
      params['daily_entry_only'] = 1;
    }
    const userId = Number(filters.user_id ?? 0);
    if (userId > 0) {
      params['user_id'] = userId;
    }

    this.reportService.getDailyLedger(params).subscribe({
      next: (response) => {
        this.entries = response.data.data;
        this.totals = response.totals;
        this.currentPage = response.data.current_page;
        this.totalPages = response.data.last_page;
        this.totalItems = response.data.total;
        this.loading = false;
      },
      error: (error) => {
        this.loading = false;
        console.error('Error loading report:', error);
        Swal.fire('خطأ', 'حدث خطأ أثناء تحميل التقرير', 'error');
      }
    });
  }

  search(): void {
    this.currentPage = 1;
    this.loadReport();
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page;
      this.loadReport();
    }
  }

  print(): void {
    window.print();
  }
}

