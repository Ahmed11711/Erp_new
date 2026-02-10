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
    this.form.get('type')?.valueChanges.subscribe(val => {
      this.form.patchValue({ entity_id: null });
      this.data = [];
      this.details = null;
    });
  }

  loadSafes() {
    this.safeService.getAll().subscribe((res: any) => {
      this.safes = res.data || res;
    });
  }

  loadBanks() {
    this.bankService.getAll().subscribe((res: any) => {
      this.banks = res.data || res;
    });
  }

  loadAccounts() {
    // Fetch General Accounts (Service Accounts or others?)
    // Currently using ServiceAccountsService as a placeholder for general accounts
    this.serviceAccountsService.index().subscribe((res: any) => {
      this.accounts = res;
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
      alert('الحساب المالي غير مرتبط بهذا العنصر');
      return;
    }

    this.reportsService.getAccountStatement(treeAccountId, from, to).subscribe((res: any) => {
      this.data = res.entries;
      this.details = {
        opening_balance: res.opening_balance,
        closing_balance: res.closing_balance,
        account: res.account
      };
      this.totals = {
        debit: res.total_debit,
        credit: res.total_credit
      };
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
