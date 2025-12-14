import { Component } from '@angular/core';
import { UserService } from 'src/app/manage-system/services/user.service';
import { NotificationService } from '../service/notification.service';
import { AuthService } from 'src/app/auth/auth.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-sent-notification',
  templateUrl: './sent-notification.component.html',
  styleUrls: ['./sent-notification.component.css']
})
export class SentNotificationComponent {
  user!:string;

  data:any[]=[];

  reviewStatus:string='0,1';

  recieveDate!:string
  status!:string

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  userdata:any[]=[];

  constructor(private notificationService:NotificationService , private userService:UserService , private authService:AuthService  ) { }

  ngOnInit(){
    this.user = this.authService.getUser();
    this.search(arguments);
    this.getUsers();
  }

  getUsers(){
    this.userService.usersForNotifi().subscribe((res:any)=>this.userdata = res);
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
  }


  onrecieveDateChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.recieveDate = target.value;
    this.search(event);

  }

  resetInp(){
    if ('supplier_id' in this.param) {
      delete this.param.supplier_id;
    }
    this.search(arguments);
  }

  param = {};
  search(event:any){


    if(event?.target?.id == 'type'){
      this.param['type']=event.target.value;
    }
    if(event?.target?.id == 'send_to'){
      this.param['send_to']=event.target.value;
    }

    if(event?.target?.id == 'status'){
      this.param['is_read']=event.target.value;
    }

    if(event?.target?.id == 'order_number'){
      this.param['order_id']=event.target.value;
    }

    if(event?.target?.id == 'review_status_admin'){
      this.param['review_status_admin']=event.target.value;
    }

    if(event?.target?.id == 'review_status_user'){
      this.param['review_status_user']=event.target.value;
    }

    this.notificationService.sentNotifiy(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      this.data = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

  deleteData(id:number){
    Swal.fire({
      title: ' تاكيد الحذف ؟',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم',
      cancelButtonText: 'لا',
    }).then((result:any) => {
      if (result.isConfirmed) {
        this.notificationService.delete(id).subscribe(res=>{
          console.log(res);
          if (res == "deleted sucuessfully") {
            this.search(arguments);
            Swal.fire({
              icon: 'success',
              timer: 3000,
              showConfirmButton:false,
            })
          }

        })

      }})

  }

}
