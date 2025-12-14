import { Component, OnInit } from '@angular/core';
import { BanksService } from '../services/banks.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-pending',
  templateUrl: './pending.component.html',
  styleUrls: ['./pending.component.css']
})
export class PendingComponent implements OnInit{
  length = 50;
  pageSize = 30;
  page = 0;
  pageSizeOptions = [30,50,100];
  banks :any = [];
  data: any = [];

  constructor(private bank:BanksService,private bankService:BanksService) {}

  ngOnInit(): void {
    this.bankService.bankSelect().subscribe(res=>this.banks=res);
    this.getData();
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
      this.page = event.pageIndex;
      this.getData();
  }

  getData(){
    this.bank.pendingBanks(this.pageSize,this.page+1 , this.param).subscribe((res:any)=>{
      this.data = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
      this.data = this.data.map(elm => {
        return {
          id: elm.id,
          created_at: elm.created_at,
          type: elm.type,
          status: this.transformStatus(elm.status),
          details: elm.details,
          amount: elm.amount,
          bank_id: elm.bank_id,
          ref: elm.ref,
          user: elm.user.name,
          bank: elm?.bank?.name,
        };
      });
    });
  }

  transformStatus(status: string): string {
    switch (status) {
      case 'approved':
        return 'تم الموافقة';
      case 'rejected':
        return 'مرفوضه';
      case 'pending':
        return 'قيد الانتظار';
      default:
        return status;
    }
  }
  param:any ={};
  search(e){
    this.param['status'] = e.target.value;
    this.getData();
  }

  sendApprovelStatus(item , status){
    if (status == 'approved') {
      const banks = this.banks;
      const bankSelectOptions = banks.reduce((options, bank) => {
        options[bank.id] = bank.name;
        return options;
      }, {});
      console.log(status);

      let data={}
      Swal.fire({
        title: ' موافقة على '+item.details+` (${Math.abs(item.amount)}) `,
        input: 'select',
        inputOptions: bankSelectOptions,
        inputPlaceholder: 'اختر الخزينة',
        inputValue:item.bank_id,
        showCancelButton: true,
        confirmButtonText: 'تأكيد',
        cancelButtonText: 'الغاء',
        customClass: {
          input: 'text-center',
        },
      }).then((result:any) => {
        const selectedBankId = result.value
        if (result.isConfirmed) {
          data['status'] = 'approved'
          data['id'] = item.id
          data['bank_id'] = selectedBankId
          this.bankService.pendingBanksStatus(data).subscribe(result=>{
            this.getData();
            if (result) {
              Swal.fire({
                icon:'success',
                timer:1500,
                showConfirmButton:false
              })
            }
          })
        }
      });

    } else if (status == 'rejected') {
      let data ={}
      data['status'] = 'rejected'
      data['id'] = item.id
      this.bankService.pendingBanksStatus(data).subscribe(result=>{
        this.getData();
        if (result) {
          Swal.fire({
            icon:'success',
            timer:1500,
            showConfirmButton:false
          })
        }
      })
    }
  }

}
