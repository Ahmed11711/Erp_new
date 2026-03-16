import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AccountingReportService } from '../services/accounting-report.service';

@Component({
  selector: 'app-account-statement',
  templateUrl: './account-statement.component.html',
  styleUrls: ['./account-statement.component.css']
})
export class AccountStatementComponent implements OnInit {
  filterForm: FormGroup;
  loading = false;
  error = '';

  // Accounts
  cashAccounts: any[] = [];
  bankAccounts: any[] = [];
  serviceAccounts: any[] = [];
  filteredAccounts: any[] = [];
  accountSearchTerm = '';

  // Report data
  account: any = null;
  entries: any[] = [];
  openingBalance = 0;
  closingBalance = 0;
  totalDebit = 0;
  totalCredit = 0;

  accountTypes = [
    { value: 'all', label: 'الخزن + البنوك + الخدمية' },
    { value: 'cash', label: 'الخزن' },
    { value: 'bank', label: 'البنوك' },
    { value: 'service', label: 'الحسابات الخدمية' }
  ];

  constructor(
    private fb: FormBuilder,
    private accountingReportService: AccountingReportService
  ) {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    this.filterForm = this.fb.group({
      source_type: ['all', Validators.required],
      account_id: ['', Validators.required],
      date_from: [firstDay.toISOString().split('T')[0]],
      date_to: [today.toISOString().split('T')[0]]
    });
  }

  ngOnInit(): void {
    this.loadAccounts();
  }

  loadAccounts(): void {
    this.accountingReportService.getAccountingTree().subscribe({
      next: (response: any) => {
        const tree = Array.isArray(response) ? response : [];
        this.cashAccounts = this.extractCashAccounts(tree);
        this.bankAccounts = this.extractBankAccounts(tree);
        this.serviceAccounts = this.extractServiceAccounts(tree);
        this.updateFilteredAccounts();
      },
      error: () => {
        this.error = 'فشل تحميل الحسابات، يرجى المحاولة مرة أخرى';
      }
    });
  }

  extractCashAccounts(accounts: any[]): any[] {
    let result: any[] = [];
    accounts.forEach(account => {
      if (
        account.type === 'asset' &&
        (account.name?.includes('خزينة') ||
          account.name_en?.toLowerCase().includes('cash'))
      ) {
        result.push(account);
      }
      if (account.children && account.children.length > 0) {
        result = result.concat(this.extractCashAccounts(account.children));
      }
    });
    return result;
  }

  extractBankAccounts(accounts: any[]): any[] {
    let result: any[] = [];
    accounts.forEach(account => {
      if (
        account.type === 'asset' &&
        (account.name?.includes('بنك') ||
          account.name?.includes('Bank') ||
          account.name_en?.toLowerCase().includes('bank'))
      ) {
        result.push(account);
      }
      if (account.children && account.children.length > 0) {
        result = result.concat(this.extractBankAccounts(account.children));
      }
    });
    return result;
  }

  extractServiceAccounts(accounts: any[]): any[] {
    let result: any[] = [];
    accounts.forEach(account => {
      if (
        account.type === 'expense' ||
        account.type === 'revenue' ||
        account.name?.includes('خدمة') ||
        account.name?.includes('خدمات') ||
        account.name_en?.toLowerCase().includes('service')
      ) {
        result.push(account);
      }
      if (account.children && account.children.length > 0) {
        result = result.concat(this.extractServiceAccounts(account.children));
      }
    });
    return result;
  }

  onSourceTypeChange(): void {
    this.updateFilteredAccounts();
    this.filterForm.patchValue({ account_id: '' });
  }

  onAccountSearchChange(): void {
    this.updateFilteredAccounts();
  }

  updateFilteredAccounts(): void {
    const sourceType = this.filterForm.value.source_type;
    let base: any[] = [];

    if (sourceType === 'cash') {
      base = this.cashAccounts;
    } else if (sourceType === 'bank') {
      base = this.bankAccounts;
    } else if (sourceType === 'service') {
      base = this.serviceAccounts;
    } else {
      base = [...this.cashAccounts, ...this.bankAccounts, ...this.serviceAccounts];
    }

    if (this.accountSearchTerm) {
      const term = this.accountSearchTerm.toLowerCase();
      this.filteredAccounts = base.filter(acc =>
        acc.name?.toLowerCase().includes(term) ||
        acc.code?.toString().includes(term) ||
        acc.name_en?.toLowerCase().includes(term)
      );
    } else {
      this.filteredAccounts = base;
    }
  }

  onSearch(): void {
    if (this.filterForm.invalid) {
      this.error = 'يرجى اختيار نوع الحساب والحساب قبل البحث';
      return;
    }

    this.loading = true;
    this.error = '';

    const params: any = {
      account_id: this.filterForm.value.account_id
    };

    if (this.filterForm.value.date_from) {
      params.date_from = this.filterForm.value.date_from;
    }
    if (this.filterForm.value.date_to) {
      params.date_to = this.filterForm.value.date_to;
    }

    this.accountingReportService.getAccountStatement(params).subscribe({
      next: (response: any) => {
        this.account = response.account;
        this.entries = response.entries || [];
        this.openingBalance = response.opening_balance || 0;
        this.closingBalance = response.closing_balance || 0;
        this.totalDebit = response.total_debit || 0;
        this.totalCredit = response.total_credit || 0;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.error = 'فشل تحميل تقرير حركة الحساب، يرجى المحاولة مرة أخرى';
      }
    });
  }

  reset(): void {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    this.filterForm.patchValue({
      source_type: 'all',
      account_id: '',
      date_from: firstDay.toISOString().split('T')[0],
      date_to: today.toISOString().split('T')[0]
    });
    this.accountSearchTerm = '';
    this.updateFilteredAccounts();
    this.account = null;
    this.entries = [];
    this.openingBalance = 0;
    this.closingBalance = 0;
    this.totalDebit = 0;
    this.totalCredit = 0;
    this.error = '';
  }
}

