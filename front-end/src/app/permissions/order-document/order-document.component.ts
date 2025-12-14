import { Component } from '@angular/core';

@Component({
  selector: 'app-order-document',
  templateUrl: './order-document.component.html',
  styleUrls: ['./order-document.component.css']
})
export class OrderDocumentComponent {
  products:any[]=[];
  catword:any="name"
  currentMonthValue!:any

  productChange(e:any){

  }
}
