import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BanksService } from 'src/app/financial/services/banks.service';
import Swal from 'sweetalert2';
import { OrderService } from '../services/order.service';

@Component({
  selector: 'app-part-collect',
  templateUrl: './part-collect.component.html',
  styleUrls: ['./part-collect.component.css']
})
export class PartCollectComponent {
  line!: string;
  company!: string;
  banks !:any[];
  total_balance !:number;
  order_type !:string;
    constructor(private order:OrderService, private route:ActivatedRoute, private bank:BanksService,
      private router:Router,
      ) { }

    ngOnInit(): void {
      const  id  = this.route.snapshot.params['id'];
      this.order.getOrderById(id).subscribe((res:any)=>{
        this.total_balance = res.net_total;
        this.order_type = res.order_type;
      })

      this.bank.bankSelect().subscribe((res:any)=>{
        this.banks = res;
      });
    }

  amount!:number;
  collectOrder(form:any){
    const  id  = this.route.snapshot.params['id'];
    const body ={amount:this.amount , bank_id:form.value.bank , note:form.value.note}
    this.order.partcollectOrder(id,body).subscribe((res:any)=>{
      console.log(res);
      if(res.message == 'success'){
        this.router.navigate(['/dashboard/shipping/listorders']);
      }
    })
  }

}
