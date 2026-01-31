import { Component, OnInit } from '@angular/core';
import { AccountingReportService } from '../services/accounting-report.service';
import { TreeAccountService } from '../services/tree-account.service';
import { TreeAccount } from '../interfaces/tree-account.interface';
import { FormBuilder, FormGroup } from '@angular/forms';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-daily-ledger-report',
  templateUrl: './daily-ledger-report.component.html',
  styleUrls: ['./daily-ledger-report.component.css']
})
export class DailyLedgerReportComponent implements OnInit {
  entries: any[] = [];
  accounts: TreeAccount[] = [];
  filteredAccounts: TreeAccount[] = [];
  loading = false;
  accountSearchTerm = '';

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

  constructor(
    private reportService: AccountingReportService,
    private treeAccountService: TreeAccountService,
    private fb: FormBuilder
  ) {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    this.filterForm = this.fb.group({
      date_from: [firstDay.toISOString().split('T')[0]],
      date_to: [today.toISOString().split('T')[0]],
      account_id: ['']
    });
  }

  ngOnInit(): void {
    this.loadAccounts();
    this.loadReport();
  }

  loadAccounts(): void {
    this.treeAccountService.getAll().subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.accounts = Array.isArray(response.data) ? response.data : [];
          this.filteredAccounts = this.accounts;
        }
      },
      error: (error) => console.error('Error loading accounts:', error)
    });
  }

  filterAccounts(): void {
    if (!this.accountSearchTerm) {
      this.filteredAccounts = this.accounts;
      return;
    }
    const term = this.accountSearchTerm.toLowerCase();
    this.filteredAccounts = this.accounts.filter(acc =>
      acc.name?.toLowerCase().includes(term) ||
      acc.code?.toString().includes(term) ||
      acc.name_en?.toLowerCase().includes(term)
    );
  }

  loadReport(): void {
    this.loading = true;
    const filters = this.filterForm.value;

    const params = {
      ...filters,
      page: this.currentPage,
      per_page: this.perPage
    };

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

