import { Component } from '@angular/core';
import { TransactionService } from '../services/transaction.service';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from 'src/app/auth/auth.service';

@Component({
  selector: 'app-customer-account-details',
  templateUrl: './customer-account-details.component.html',
  styleUrls: ['./customer-account-details.component.css']
})
export class CustomerAccountDetailsComponent {
  customer: string | null = null;
  user: any;
  param: any = {};

  data:any[] = [];

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  constructor(
    private transactionService: TransactionService,
    private route: ActivatedRoute,
    private router: Router,
    private authService: AuthService
  ) {}

  ngOnInit() {
    this.user = this.authService.getUser();

    this.route.queryParams.subscribe(params => {
      const customer = params['customer'];
      if (customer) {
        this.param.customer = customer;
        this.customer = customer;
        this.getCustomerDetails();
      }
    });
  }

  getCustomerDetails(){
    this.transactionService.getCustomerDetails(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      this.data = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.getCustomerDetails();
  }
}
