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

  constructor(private TransactionService : TransactionService, private route:Router, private authService:AuthService ) {
  }

  ngOnInit(){
    this.user = this.authService.getUser();
    this.search(arguments);
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
  }

  param = {};
  search(event:any){
    this.TransactionService.searchCustomer(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      this.data = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }
}
