import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from 'src/app/auth/auth.service';
import { TransactionService } from '../services/transaction.service';

@Component({
  selector: 'app-customer-accounts',
  templateUrl: './customer-accounts.component.html',
  styleUrls: ['./customer-accounts.component.css']
})
export class CustomerAccountsComponent {

  data:any[] = [];

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];
  user: any;

  /** بحث داخل الصفحة (اسم أو موبايل) */
  searchQuery = '';

  constructor(private TransactionService : TransactionService, private route:Router, private authService:AuthService ) {
  }

  ngOnInit(){
    this.user = this.authService.getUser();
    this.load();
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.load();
  }

  param = {};
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
    const params: Record<string, string> = { ...this.param } as Record<string, string>;
    const q = this.searchQuery.trim();
    if (q) {
      params['search'] = q;
    }
    this.TransactionService.searchCustomer(this.pageSize, this.page + 1, params).subscribe((res:any)=>{
      this.data = res.data;
      this.length = res.total ?? res.data?.length ?? 0;
      this.pageSize = res.per_page ?? this.pageSize;
    })
  }
}
