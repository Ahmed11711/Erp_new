import { Component } from '@angular/core';
import { ActivatedRoute, Params } from '@angular/router';
import { SuppliersService } from '../services/suppliers.service';

@Component({
  selector: 'app-supplier-details',
  templateUrl: './supplier-details.component.html',
  styleUrls: ['./supplier-details.component.css']
})
export class SupplierDetailsComponent {
  id!:any;
  suppliersData: any = [];
  name!:string;

  length = 50;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15,50];

  constructor(private supplier:SuppliersService, private router:ActivatedRoute) {
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
    this.supplier.supplierDetails(this.id , this.pageSize,this.page+1).subscribe((res:any)=>{
      console.log(res);

      this.name = res.name;

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
