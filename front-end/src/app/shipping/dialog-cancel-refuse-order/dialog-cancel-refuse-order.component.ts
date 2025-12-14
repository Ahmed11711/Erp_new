import { HttpClient } from '@angular/common/http';
import { Component, Inject } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { DialogPayMoneyForSupplierComponent } from 'src/app/suppliers/dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import { CompaniesService } from '../services/companies.service';
import { OrderService } from '../services/order.service';
import { BanksService } from 'src/app/financial/services/banks.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-dialog-cancel-refuse-order',
  templateUrl: './dialog-cancel-refuse-order.component.html',
  styleUrls: ['./dialog-cancel-refuse-order.component.css']
})
export class DialogCancelRefuseOrderComponent {

  banks:any[]=[];
  title:string= '';
  selectedBank:boolean=false;
  amount!:number;
  receivedOrder:any='هل تم استلام المنتج من شركة الشحن؟';
  constructor(public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private order:OrderService , private bankService:BanksService,
    private http:HttpClient
  ) {}

  ngOnInit(){
    console.log(this.data)
    if (this.data?.data?.action == "refused") {
      this.title = 'رفض استلام'
    } else {
      this.title = 'الغاء الطلب'
    }
    this.bankService.bankSelect().subscribe((res:any)=>this.banks=res)

    this.form.patchValue({
      'bank':'الخزينة',
      'receivedOrder':'هل تم استلام المنتج من شركة الشحن؟'
    })


  }

  form:FormGroup = new FormGroup({
    'reason' :new FormControl(null  , [Validators.required ]),
    'amount' :new FormControl(null),
    'bank' :new FormControl(null),
    'receivedOrder' :new FormControl(null  , [Validators.required ])
  })

  onCloseClick(): void {
    this.dialogRef.close();
  }

  bank(e:any){
    this.selectedBank = true;
  }

  submitform(){
    if (this.form.valid) {
      let data = this.form.value
      console.log(data);

      const formData = new FormData();
      formData.append('reason', data.reason);
      formData.append('amount', data.amount);
      formData.append('bank', data.bank);

      console.log(this.form.value);

      const id = this.data?.data.id;
      const action = this.data?.data.action;
      const reason =this.form.value.reason;
      const amount =this.form.value.amount || 0;
      const bank =this.form.value.bank;
      Swal.fire({
        title: 'هل تم استلام المنتج',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'تم الاستلام',
        cancelButtonText: 'لم يتم الاستلام',
      }).then((result) => {
        let receviedOrder;
        if (result.isConfirmed) {
          receviedOrder = true;
          this.order.refuseOrder(id,reason,amount,bank,true,'').subscribe((result:any)=>{
            if (result) {
              this.data.refreshData();
              Swal.fire({
                icon : 'success',
                timer:3000,
                showConfirmButton:false,
                titleText: 'تم ارسال اشعار للادمن',
                position: 'bottom-end',
                toast: true,
                timerProgressBar: true,
              });
            };
          })
        } else if (result.isDismissed) {
          receviedOrder = false;
          Swal.fire({
            icon:'info',
            input: 'text',
            inputPlaceholder: 'السبب',
            showCancelButton: true,
            inputValidator: (value) => {
              if (!value) {
                return 'يجب ادخال ملاحظة'
              }
              if (value !== '') {
                this.order.refuseOrder(id,reason,amount,bank,false,value).subscribe((result:any)=>{
                  if (result) {
                    this.data.refreshData();
                    Swal.fire({
                      icon : 'success',
                      timer:3000,
                      showConfirmButton:false,
                      titleText: 'تم ارسال اشعار للادمن',
                      position: 'bottom-end',
                      toast: true,
                      timerProgressBar: true,
                    });
                  };
                });
              }
              return undefined
            }
          })
        }

        this.onCloseClick();


      })

    }
  }


}
