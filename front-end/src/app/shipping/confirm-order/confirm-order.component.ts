import { DatePipe } from '@angular/common';
import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { from } from 'rxjs';
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
  constructor(
    private line:ShippingLinesService,
    private datePipe :DatePipe,
    private order:OrderService,
    private route:ActivatedRoute,
    private router:Router
    ) { }

  ngOnInit(): void {
    this.getData();
  }
  getOrderType(data:any){
    this.orderType = data.orderType;
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

    if (form.valid) {

    // let maintenReason:string='';

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
                // maintenReason = value;
                this.order.confirmOrder(id, this.date,form.value.line,form.value.note, value).subscribe((result:any)=>{
                  console.log(result);
                  if(result.message == "success"){
                    this.router.navigate(['/dashboard/shipping/listorders']);
                  }
                });
            }
            return undefined
          }
        })
      } else {
        this.order.confirmOrder(id, this.date,form.value.line,form.value.note, '').subscribe((result:any)=>{
          console.log(result);
          if(result.message == "success"){
            this.router.navigate(['/dashboard/shipping/listorders']);
          }
        });
      }

    }
  }
}
