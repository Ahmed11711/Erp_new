import { Component } from '@angular/core';

@Component({
  selector: 'app-order-print',
  templateUrl: './order-print.component.html',
  styleUrls: ['./order-print.component.css']
})
export class OrderPrintComponent {

  products:any[]=[];
  catword:any="name"
  currentMonthValue!:any

  productChange(e:any){

  }
}
