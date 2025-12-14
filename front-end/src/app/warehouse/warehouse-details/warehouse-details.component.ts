import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { CategoryService } from 'src/app/categories/services/category.service';
import {Location} from '@angular/common';

@Component({
  selector: 'app-warehouse-details',
  templateUrl: './warehouse-details.component.html',
  styleUrls: ['./warehouse-details.component.css']
})
export class WarehouseDetailsComponent {
  warehouse!:any;
  data:any = [];
  type!:string;

  length = 50;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15,50,100];

constructor(private cat:CategoryService, private route:ActivatedRoute , private _location:Location){}


  ngOnInit(){
    this.warehouse =  this.route.snapshot.queryParams['warehouse'];
    this.param['warehouse']=this.warehouse;
    this.getData();
  }

  getData(){
    this.cat.warehouseDetails(this.pageSize,this.page+1 , this.param).subscribe((res:any)=>{
      this.length=res.total;
      this.pageSize=res.per_page;
      this.data = res.data;
      this.type = this.data[0].type;
    })
  }

  param = {};
  onDateFromChange(event :any){
    if (event) {
      this.param['date']=event.target.value;
    }
    console.log(event.target.value);

    this.getData();
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
      this.page = event.pageIndex;
      this.getData();
  }

  back() {
    this._location.back();
  }
}
