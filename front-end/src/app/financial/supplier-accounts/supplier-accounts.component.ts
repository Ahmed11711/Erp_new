import { Component, OnInit, ViewChild } from '@angular/core';
import { MatPaginator } from '@angular/material/paginator';
import { AuthService } from 'src/app/auth/auth.service';
import { TransactionService } from '../services/transaction.service';

@Component({
  selector: 'app-supplier-accounts',
  templateUrl: './supplier-accounts.component.html',
  styleUrls: ['./supplier-accounts.component.css']
})
export class SupplierAccountsComponent implements OnInit {

  data: any[] = [];

  length = 0;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15, 50, 100];
  user: any;

  /** فلاتر البحث */
  supplier_name = '';
  supplier_phone = '';
  /** '' = الكل؛ want/own كما في API الموردين */
  selectedStatus: '' | 'want' | 'own' = '';

  param: Record<string, string> = {};

  @ViewChild(MatPaginator, { static: false }) paginator!: MatPaginator;

  constructor(
    private TransactionService: TransactionService,
    private authService: AuthService
  ) {}

  ngOnInit() {
    this.user = this.authService.getUser();
    this.applyParamsFromForm();
    this.loadData();
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.loadData();
  }

  applyParamsFromForm(): void {
    this.param = {};
    const name = (this.supplier_name ?? '').trim();
    if (name) {
      this.param['supplier_name'] = name;
    }
    const phone = (this.supplier_phone ?? '').trim();
    if (phone) {
      this.param['supplier_phone'] = phone;
    }
    if (this.selectedStatus === 'want' || this.selectedStatus === 'own') {
      this.param['status'] = this.selectedStatus;
    }
  }

  /** تنفيذ البحث من النموذج */
  runSearch(): void {
    this.page = 0;
    this.paginator?.firstPage();
    this.applyParamsFromForm();
    this.loadData();
  }

  clearSearch(): void {
    this.supplier_name = '';
    this.supplier_phone = '';
    this.selectedStatus = '';
    this.page = 0;
    this.paginator?.firstPage();
    this.param = {};
    this.loadData();
  }

  loadData(): void {
    this.TransactionService.searchSupplier(this.pageSize, this.page + 1, this.param).subscribe({
      next: (res: any) => {
        this.data = Array.isArray(res?.data) ? res.data : [];
        this.length = typeof res?.total === 'number' ? res.total : 0;
        if (typeof res?.per_page === 'number') {
          this.pageSize = res.per_page;
        }
      },
      error: () => {
        this.data = [];
        this.length = 0;
      },
    });
  }
}
