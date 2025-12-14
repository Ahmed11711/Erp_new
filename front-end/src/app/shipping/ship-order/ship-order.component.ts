import { DatePipe } from '@angular/common';
import { Component } from '@angular/core';
import { ActivatedRoute, Route, Router } from '@angular/router';
import { OrderService } from '../services/order.service';
import { ShippingCompanyService } from '../services/shipping-company.service';
import { ShippingLinesService } from '../services/shipping-lines.service';

@Component({
  selector: 'app-ship-order',
  templateUrl: './ship-order.component.html',
  styleUrls: ['./ship-order.component.css']
})
export class ShipOrderComponent {
lines:any = []
companies :any = []
constructor(
  private order:OrderService,private route:ActivatedRoute, private datePipe:DatePipe, private company:ShippingCompanyService,
   private router:Router
  ){}

customer_type!:string;
paymentTypeActive:boolean = false;

completedShip:boolean=false;

ngOnInit(){
  const  id  = this.route.snapshot.params['id'];
  this.order.getOrderById(id).subscribe((res:any)=>{
    this.customer_type = res?.customer_type;
    if (res?.customer_type == 'شركة') {
      this.paymentTypeActive = true;
      let status = res?.order_products.every(elm => elm.quantity - elm.shipped_quantity == 0);
      if (status) {
        this.completedShip = true;
        this.router.navigate(['/dashboard/shipping/listorders']);
      }
    }

    this.lines=res.order_details.shipping_line
  })

  this.company.shippingCompanySelect().subscribe((res:any)=>{
    this.companies = res
  });
}
myFilter = (d: Date | null): boolean => {
  const today = new Date();
  const selectedDate = d || today;
  const timeDifference = Math.ceil((selectedDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
  // return timeDifference >= 0;
  return timeDifference >= 0 && timeDifference <= 2;
};

date : any
dateSelected
OnDateChange(event){
  const inputDate = new Date(event);
  this.date = this.datePipe.transform(inputDate, 'yyyy-M-d');
  this.dateSelected = true;
}

selectedFile: any;
imgselect = false;
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.imgselect = true;
  }
  productsArray:any = [];
  shippStatus:boolean=true;
  // getshippedquantity(newProduct:any){
  //   this.shippStatus = newProduct.shippstatus;

  //  if(newProduct.quantity == '' || newProduct.quantity == 0){
  //   this.productsArray = this.productsArray.filter((product) => product.id !== newProduct.id);
  //  }else{
  //   this.productsArray = this.productsArray
  //   .filter((product) => product.quantity !== '' && product.id !== newProduct.id)
  //   .concat(newProduct);
  //  }
  // }
  getshippedquantity(data:any){
    this.shippStatus = data.shippstatus;

    this.productsArray = data.shipProducts.map(elm=>{
      return {id:elm.id , quantity:elm.requiredQuantity}
    })
  }
  payment!:string;
  cashType:boolean = false;
  cashval:boolean = false;
  cash:number=0;
  paymentType(e:any){
    if (e.target.id == 'paymentType') {
      this.payment = e.target.value;
      if (this.payment == 'أجل' || this.payment == 'نقدي') {
        this.paymentTypeActive = false;
      }
      if (this.payment == 'نقدي') {
        this.cashType = true;
      } else if (this.payment == 'أجل') {
        this.cashType = false;
        this.cashval = false;
        this.cash = 0;
      }
    }
    if (e.target.id == 'cash') {
      this.cash = e.target.value;
      if (this.cash >0) {
        this.cashType = false;
        this.cashval = true;
      } else {
        this.cashType = true;
      }
    }
  }

shipOrder(form:any){
  const formData = new FormData();
  formData.append('company_id', form.value.company);
  // formData.append('shipping_line_id', form.value.line);


  formData.append('date', this.date);
  // formData.append('shipping_image', this.selectedFile, this.selectedFile.name);
  formData.append('shippment_number', form.value.shippment_number);
  formData.append('productsToShip', JSON.stringify(this.productsArray));
  const id = this.route.snapshot.params['id'];
  // console.log(this.productsArray);

  if (this.customer_type == 'شركة') {
    formData.append('payment_way', this.payment);
    if (this.payment == 'نقدي') {
      let cash = String(this.cash) ;
      formData.append('cash', cash);
    }
  }


  this.order.shipOrder(formData,id).subscribe((res:any)=>{
    console.log(res);

    if(res.message =="success"){
      this.router.navigate(['/dashboard/shipping/listorders']);
    }
  });
}
}

