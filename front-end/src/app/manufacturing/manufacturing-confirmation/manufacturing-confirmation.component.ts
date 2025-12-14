import { DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ManufacturingService } from '../services/manufacturing.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-manufacturing-confirmation',
  templateUrl: './manufacturing-confirmation.component.html',
  styleUrls: ['./manufacturing-confirmation.component.css']
})
export class ManufacturingConfirmationComponent implements OnInit{

  product_id!:any;
  total:number=0;
  quantity:number=1;
  productPrice:number = 0;
  status:string='تم الانتهاء';

  constructor(private datePipe:DatePipe ,
    private manufacturingService:ManufacturingService,
    private route:Router
    ){}

  ngOnInit(): void {
    this.manufacturingService.manfuctureByWarhouse('مخزن منتج تحت التشغيل').subscribe((result:any)=>this.products=result);
  }

  products:any[]=[];
  catword = 'category_name';

  more:any[]=[];
  catword2 = 'category_name';

  warehouseType(e){
    this.manufacturingService.manfuctureByWarhouse(e.target.value).subscribe((result:any)=>this.products=result);
  }

  productChange(event) {
    this.productPrice = event.cost;
    this.product_id = event.id;
    this.total = Number(this.productPrice)  * Number(this.quantity);
  }

  quantityFun(e){
    if (e.target.value === '') {
      this.quantity = 1
    } else{
      this.quantity = e.target.value;
    }
    this.total = Number(this.productPrice)  * Number(this.quantity);
  }

  moreChange(event) {
    // const ID = event.id
    // this.category_price = event.category_price
    // this.category_image = event.category_image
    // this.category_name = event.category_name
    // this.category_id = event.id
  }

  dateSelected = false;
  date:any;

  // myFilter = (d: Date | null): boolean => {
  //   const today = new Date();
  //   const selectedDate = d || today;
  //   const timeDifference = Math.floor((today.getTime() - selectedDate.getTime()) / (1000 * 60 * 60 * 24));
  //   return timeDifference >= 0 && timeDifference <= 3;
  // };

  OnDateChange(event){
    const inputDate = new Date(event);
    this.date = this.datePipe.transform(inputDate, 'yyyy-M-d');
    this.dateSelected = true;
  }

  isSubmitting: boolean = false;
  submit(){
    if (this.product_id && this.date && !this.isSubmitting) {
      this.isSubmitting = true;
      let data = {
        total:this.total,
        product_id:this.product_id ,
        status:this.status ,
        date:this.date ,
        quantity : this.quantity
      };
      this.manufacturingService.confirm(data).subscribe(result=>{
        console.log(result);

        if (result) {
          this.route.navigate(['/dashboard/manufacturing/orders']);
        }
      })
    }
  }

  resetInp(){
    this.productPrice=0;
    this.total = Number(this.productPrice)  * Number(this.quantity);
  }
}
