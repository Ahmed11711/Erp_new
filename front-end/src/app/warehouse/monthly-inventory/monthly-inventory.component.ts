import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { CategoryService } from 'src/app/categories/services/category.service';
import {Location} from '@angular/common';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-monthly-inventory',
  templateUrl: './monthly-inventory.component.html',
  styleUrls: ['./monthly-inventory.component.css']
})
export class MonthlyInventoryComponent {

  warehouse:string = "";
  categories:any;
  prevMonthValue!:any
  month!:any
  year!:any

  length = 50;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15,50];

  constructor(private category:CategoryService, private route:ActivatedRoute, private _location:Location) {
    this.warehouse =  this.route.snapshot.queryParams['warehouse'];
  }


  ngOnInit() {
    const today = new Date();
    let year = today.getFullYear();
    let month = today.getMonth() + 1;
    let prevYear = year;
    let prevMonth = month - 1;
    if (prevMonth === 0) {
        prevMonth = 12;
        prevYear--;
    }
    this.year = prevYear;
    this.month = prevMonth;
    this.prevMonthValue = `${this.year}-${this.month.toString().padStart(2, '0')}`;
    this.getData();
}



  onMonthChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.prevMonthValue = target.value;
    const [year, month] = this.prevMonthValue.split('-');

    this.month = month;
    this.year = +year;
    this.getData();
  }

  getData(){
    this.category.monthlyInventoryDetails(this.warehouse, this.pageSize,this.page+1 , this.prevMonthValue , this.param).subscribe((res:any)=>{
      this.categories = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
      this.page = event.pageIndex;
      this.getData();
  }

  back() {
    this._location.back();
  }

  param = {};
  onCategorychange(event :any){
    if (event.target.id == 'type') {
      this.param['name']=event.target.value;
    }
    if (event.target.id == 'check') {
      console.log(event.target.checked);
      if (event.target.checked) {
        this.param['sort']= true;
      } else {
        delete this.param['sort'];
      }

    }
    this.getData();
  }

}
