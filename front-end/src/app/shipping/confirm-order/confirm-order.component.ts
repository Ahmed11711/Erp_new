import { DatePipe } from '@angular/common';
import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { OrderService } from '../services/order.service';
import { ShippingLinesService } from '../services/shipping-lines.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-confirm-order',
  templateUrl: './confirm-order.component.html',
  styleUrls: ['./confirm-order.component.css']
})
export class ConfirmOrderComponent {

lines:any[]=[];
dateSelected = false;
date:any;
orderType!:string;
isOfferOrder = false;
  constructor(
    private line:ShippingLinesService,
    private datePipe :DatePipe,
    private order:OrderService,
    private route:ActivatedRoute,
    private router:Router
    ) { }

  ngOnInit(): void {
    this.getData();
    const id = this.route.snapshot.params['id'];
    this.order.getOrderById(id).subscribe((res: any) => {
      this.isOfferOrder = !!(res?.offer_id || res?.offer_debt_posted);
      this.orderType = res?.order_type || this.orderType;
    });
  }
  getOrderType(data:any){
    this.orderType = data.orderType;
  }

  private afterConfirmPath(): string {
    return this.isOfferOrder
      ? '/dashboard/shipping/offer-orders'
      : '/dashboard/shipping/listorders';
  }

  getData(){
    return this.line.dataLines().subscribe(result=>{
      this.lines=result;
    })
  }
  myFilter = (d: Date | null): boolean => {
    const today = new Date();
    const selectedDate = d || today;
    const timeDifference = Math.ceil((selectedDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    return timeDifference >= 0;
  };

  OnDateChange(event){
    const inputDate = new Date(event);
    this.date = this.datePipe.transform(inputDate, 'yyyy-M-d');
    this.dateSelected = true;
  }

  async confirmOrder(form:any){
    const  id  = this.route.snapshot.params['id'];

    if (!this.dateSelected) {
      return;
    }
    if (!this.isOfferOrder && !form.value.line) {
      return;
    }

    const lineId = form.value.line || null;

      if (this.orderType == 'طلب صيانة') {
        await Swal.fire({
          title: ' سبب الصيانة',
          input: 'text',
          showCancelButton: true,
          inputValidator: (value) => {
            if (!value) {
              return 'يجب ادخال قيمة'
            }
            if (value !== '') {
                this.order.confirmOrder(id, this.date, lineId, form.value.note, value).subscribe((result:any)=>{
                  if(result.message == "success"){
                    this.router.navigate([this.afterConfirmPath()]);
                  }
                });
            }
            return undefined
          }
        })
      } else {
        this.order.confirmOrder(id, this.date, lineId, form.value.note, '').subscribe((result:any)=>{
          if(result.message == "success"){
            this.router.navigate([this.afterConfirmPath()]);
          }
        });
      }
  }
}
