import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { SuppliersService } from 'src/app/suppliers/services/suppliers.service';
import { BanksService } from '../services/banks.service';

@Component({
  selector: 'app-bank-details',
  templateUrl: './bank-details.component.html',
  styleUrls: ['./bank-details.component.css']
})
export class BankDetailsComponent {
  id!:any;
  suppliersData: any = [];
  name!:string;

  length = 50;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15,50];

  constructor(private bank:BanksService, private router:ActivatedRoute) {
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

  getRouterLink(ref: string): any[] {
    if (ref.match(/^\d/)) {
      return ['/dashboard/shipping/orderdetails', ref];
    } else if (ref.startsWith('P')) {
      return ['/dashboard/purchases/invoice', ref.slice(2)];
    } else if (ref.startsWith('EX')) {
      return ['/dashboard/financial/expense_details', ref.slice(2)];
    } else {
      return ['/dashboard/shipping/defaultdetails', ref.slice(2)];
    }
  }

  getData(){
    this.bank.bankDetails(this.id , this.pageSize,this.page+1).subscribe((res:any)=>{

      this.name = res[0]

      this.suppliersData = res.data.data;
      this.suppliersData.forEach(item => {
        item.balance_after = item.balance_after.toFixed(2);
        item.balance_before = item.balance_before.toFixed(2);
      });

      this.length=res.data.total;
      this.pageSize=res.data.per_page;


    })
  }

}
