import { HttpClient } from '@angular/common/http';
import { Component, Inject } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { DialogPayMoneyForSupplierComponent } from 'src/app/suppliers/dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
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
  submitting = false;

  constructor(public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private order:OrderService ,
    private bankService:BanksService,
    private http:HttpClient
  ) {}

  ngOnInit(){
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

  private completeRefuse(
    id: number,
    reason: string,
    amount: number,
    bank: string | number,
    received: boolean,
    reasoncat: string
  ): void {
    if (this.submitting) {
      return;
    }
    this.submitting = true;
    this.order.refuseOrder(id, reason, amount, bank, received, reasoncat).subscribe({
      next: () => {
        this.submitting = false;
        this.data.refreshData();
        this.onCloseClick();
        Swal.fire({
          icon : 'success',
          timer:3000,
          showConfirmButton:false,
          titleText: 'تم تسجيل رفض الاستلام',
          position: 'bottom-end',
          toast: true,
          timerProgressBar: true,
        });
      },
      error: (err) => {
        this.submitting = false;
        Swal.fire({
          icon: 'error',
          title: err?.error?.message || 'تعذر تنفيذ رفض الاستلام',
        });
      }
    });
  }

  submitform(){
    if (!this.form.valid || this.submitting) {
      return;
    }

    const id = this.data?.data.id;
    const reason = this.form.value.reason;
    const amount = this.form.value.amount || 0;
    const bank = this.form.value.bank;

    Swal.fire({
      title: 'هل تم استلام المنتج',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'تم الاستلام',
      cancelButtonText: 'لم يتم الاستلام',
    }).then((result) => {
      if (result.isConfirmed) {
        this.completeRefuse(id, reason, amount, bank, true, '');
        return;
      }

      if (result.dismiss === Swal.DismissReason.cancel) {
        Swal.fire({
          icon:'info',
          input: 'text',
          inputPlaceholder: 'السبب',
          showCancelButton: true,
          inputValidator: (value) => {
            if (!value) {
              return 'يجب ادخال ملاحظة';
            }
            return undefined;
          }
        }).then((noteResult) => {
          if (noteResult.isConfirmed && noteResult.value) {
            this.completeRefuse(id, reason, amount, bank, false, noteResult.value);
          }
        });
      }
    });
  }
}
