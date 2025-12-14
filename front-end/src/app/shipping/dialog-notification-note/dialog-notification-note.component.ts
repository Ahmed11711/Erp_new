import { HttpClient } from '@angular/common/http';
import { Component, Inject } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { AuthService } from 'src/app/auth/auth.service';
import { NotificationService } from 'src/app/notification/service/notification.service';
import { DialogPayMoneyForSupplierComponent } from 'src/app/suppliers/dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-dialog-notification-note',
  templateUrl: './dialog-notification-note.component.html',
  styleUrls: ['./dialog-notification-note.component.css']
})
export class DialogNotificationNoteComponent {

  user!:string;

  constructor(public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private http:HttpClient , private notificatioService:NotificationService, private authService:AuthService
  ) {
  }

  ngOnInit(){
    this.user = this.authService.getUser();
    this.form.patchValue({
      type:'نوع الاشعار'
    })

  }

  form:FormGroup = new FormGroup({
    'note' :new FormControl(null  , [Validators.required ]),
    'type' :new FormControl(null  , [Validators.required ]),
  })

  onCloseClick(): void {
    this.dialogRef.close();
  }

  submitform(){

    if (this.form.valid) {
      let data = this.form.value
      data.send_to = this.data.user.id
      data.content = this.data.orders
      const formData = new FormData();
      formData.append('note', data.name);
      formData.append('type', data.type);
      formData.append('send_to', data.send_to);
      formData.append('data', data.content);
      this.notificatioService.sendNotification(data).subscribe(res=>{
        console.log(res);

        if (res.length >= 1) {
          this.onCloseClick();
          Swal.fire({
            icon : 'success',
            timer:1500,
            showConfirmButton:false,
          })
          this.data.refreshData();
        }
      })

    }
  }

}
