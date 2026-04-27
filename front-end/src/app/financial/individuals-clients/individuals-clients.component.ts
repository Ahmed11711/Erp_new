import { Component, OnInit } from '@angular/core';
import { TransactionService } from '../services/transaction.service';

@Component({
  selector: 'app-individuals-clients',
  templateUrl: './individuals-clients.component.html',
  styleUrls: ['./individuals-clients.component.css']
})
export class IndividualsClientsComponent implements OnInit {

  data: any[] = [];
  searchQuery = '';
  dateFrom!: string;
  dateTo!: string;

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15, 50, 100];

  constructor(private transactionService: TransactionService) {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const day = today.getDate();
    const d = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
    this.dateFrom = d;
    this.dateTo = d;
  }

  ngOnInit(): void {
    this.load();
  }

  onPageChange(event: any): void {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.load();
  }

  applySearch(): void {
    this.page = 0;
    this.load();
  }

  clearSearch(): void {
    this.searchQuery = '';
    this.page = 0;
    this.load();
  }

  private load(): void {
    const params: Record<string, string> = {};
    const q = this.searchQuery.trim();
    if (q) {
      params['search'] = q;
    }
    this.transactionService.searchCustomer(this.pageSize, this.page + 1, params).subscribe({
      next: (res: any) => {
        this.data = res.data ?? [];
        this.length = res.total ?? 0;
        this.pageSize = res.per_page ?? this.pageSize;
      },
      error: () => {
        this.data = [];
        this.length = 0;
      }
    });
  }

  /** مجموع المستحق (صافي الطلبات − المقدم) للصف الحالي */
  sumDue(row: any): number {
    const net = Number(row?.total_debit ?? 0);
    const pre = Number(row?.total_credit ?? 0);
    return net - pre;
  }

  footSumDue(): number {
    return this.data.reduce((s, row) => s + this.sumDue(row), 0);
  }

  footSumPrepaid(): number {
    return this.data.reduce((s, row) => s + Number(row?.total_credit ?? 0), 0);
  }

  footSumNet(): number {
    return this.data.reduce((s, row) => s + Number(row?.total_debit ?? 0), 0);
  }

  footOrdersCount(): number {
    return this.data.reduce((s, row) => s + Number(row?.orders_count ?? 0), 0);
  }
}
