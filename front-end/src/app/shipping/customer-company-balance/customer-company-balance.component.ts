import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { CompaniesService } from '../services/companies.service';

@Component({
  selector: 'app-customer-company-balance',
  templateUrl: './customer-company-balance.component.html',
  styleUrls: ['./customer-company-balance.component.css']
})
export class CustomerCompanyBalanceComponent {
  id!:any;
  data: any = [];
  name!:string;

  length = 50;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15,50];

  constructor(private company:CompaniesService, private router:ActivatedRoute) {
    this.id = this.router.snapshot.paramMap.get('id');
  }

  ngOnInit(){
    this.getData();
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
      this.page = event.pageIndex;
      this.getData();
  }


  getData(){
    this.company.companyBalanceDetails(this.id , this.pageSize,this.page+1).subscribe((res:any)=>{
      this.name = res[0]
      this.data = res.data.data;
      this.length=res.data.total;
      this.pageSize=res.data.per_page;
    })
  }
}
