import { Component } from '@angular/core';
import { UserService } from 'src/app/manage-system/services/user.service';
import { NotificationService } from '../service/notification.service';
import { AuthService } from 'src/app/auth/auth.service';

@Component({
  selector: 'app-moves-notification',
  templateUrl: './moves-notification.component.html',
  styleUrls: ['./moves-notification.component.css']
})
export class MovesNotificationComponent {
  user!:string;
  data:any[]=[];

  recieveDate!:string
  status!:string

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  reviewStatus:string='0,1';


  userdata:any[]=[];

  constructor(private notificationService:NotificationService , private userService:UserService , private authService:AuthService  ) { }

  ngOnInit(){
    this.user = this.authService.getUser();
    this.search(arguments);
    this.getUsers();
  }

  getUsers(){
    this.userService.data().subscribe((res:any)=>this.userdata = res);
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
    if(event?.target?.id == 'send_from'){
      this.param['send_from']=event.target.value;
    }
    if(event?.target?.id == 'status'){
      this.param['is_read']=event.target.value;
    }
    if(event?.target?.id == 'review_status'){
      this.param['review_status']=event.target.value;
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

    this.notificationService.allNotifiy(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      console.log(res);

      this.data = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;

    })
  }
}
