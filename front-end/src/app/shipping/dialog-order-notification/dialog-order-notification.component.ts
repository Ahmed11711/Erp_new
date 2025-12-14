import { HttpClient } from '@angular/common/http';
import { Component, Inject } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { NotificationService } from 'src/app/notification/service/notification.service';
import { DialogPayMoneyForSupplierComponent } from 'src/app/suppliers/dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-dialog-order-notification',
  templateUrl: './dialog-order-notification.component.html',
  styleUrls: ['./dialog-order-notification.component.css']
})
export class DialogOrderNotificationComponent {


  constructor(public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private http:HttpClient , private notificatioService:NotificationService
  ) {}

  ngOnInit(){
    this.notificatioService.readOrderNotify(this.data?.notifications[0]?.id,this.data?.notifications).subscribe(res=>{
      console.log(res);

    })


  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

}
