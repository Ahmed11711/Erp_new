import { Component, ViewChild } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from 'src/app/auth/auth.service';
import { UserService } from 'src/app/manage-system/services/user.service';
import { NotificationService } from 'src/app/notification/service/notification.service';
import { FilterOrderService } from 'src/app/shipping/services/filter-order.service';

@Component({
  selector: 'app-recieved-notification',
  templateUrl: './recieved-notification.component.html',
  styleUrls: ['./recieved-notification.component.css']
})
export class RecievedNotificationComponent {
  user!:string;

  data:any[]=[];

  recieveDate!:string
  status!:string

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  userdata:any[]=[];

  constructor(private notificationService:NotificationService , private userService:UserService , private router:Router,
    private orderFilter :FilterOrderService, private authService:AuthService ) { }

  ngOnInit(){
    this.user = this.authService.getUser();
    if (this.notificationService.recievedParam) {
      if (this.notificationService.recievedParam['type']) {
        let type:any = document.getElementById('type');
        type.value = this.notificationService.recievedParam['type'];
      }
      if (this.notificationService.recievedParam['send_from']) {
        let send_from:any = document.getElementById('send_from');
        send_from.value = this.notificationService.recievedParam['send_from'];
      }
      console.log(this.notificationService.recievedParam);

      if (this.notificationService.recievedParam['is_read']) {
        let status:any = document.getElementById('status');
        status.value = this.notificationService.recievedParam['is_read'];
      }
      if (this.notificationService.recievedParam['order_id']) {
        document.getElementById('order_number')?.setAttribute('value', this.notificationService.recievedParam['order_id']);
      }
    }
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

  clearSearch() {
    this.notificationService.recievedParam = {};
    let type:any = document.getElementById('type');
    type.value = 'نوع الاشعار';
    let send_from:any = document.getElementById('send_from');
    send_from.value = 'المرسل';
    let status:any = document.getElementById('status');
    status.status = 'الحاله';
    let order_number:any = document.getElementById('order_number');
    order_number.value = '';
    let review_status_user:any = document.getElementById('review_status_user');
    review_status_user.value = 'حالة المراجعة';
    this.search(arguments);
  }

  param = {};
  search(event:any){

    if(event?.target?.id == 'type'){
      // this.param['type']=event.target.value;
      this.notificationService.recievedParam['type'] = event.target.value;
    }
    if(event?.target?.id == 'send_from'){
      // this.param['send_from']=event.target.value;
      this.notificationService.recievedParam['send_from'] = event.target.value;
    }
    if(event?.target?.id == 'status'){
      // this.param['is_read']=event.target.value;
      this.notificationService.recievedParam['is_read'] = event.target.value;
    }
    if(event?.target?.id == 'order_number'){
      // this.param['order_id']=event.target.value;
      this.notificationService.recievedParam['order_id'] = event.target.value;
    }
    if(event?.target?.id == 'review_status_user'){
      // this.param['review_status_user']=event.target.value;
      this.notificationService.recievedParam['review_status_user'] = event.target.value;
    }

    this.notificationService.recievedNotifiy(this.pageSize,this.page+1).subscribe((res:any)=>{
      this.data = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

  openNotifiy(elm:any){

    if (elm?.is_read == 0) {
      console.log('here');

      this.notificationService.readNotify(elm.id).subscribe(res=>{

      })
    }

    this.orderFilter.order_number = elm.ref;
    this.orderFilter.customer_type = '';
    this.orderFilter.order_type = '';
    this.orderFilter.order_status = '';
    this.orderFilter.shipping_company_id = '';
    this.orderFilter.need_by_date = '';
    this.orderFilter.status_date = '';
    this.orderFilter.vip = '';
    this.orderFilter.shortage = '';
    this.orderFilter.governorate = '';
    this.orderFilter.city = '';
    this.orderFilter.customer_name = '';
    this.orderFilter.customer_phone = '';
    this.orderFilter.shippment_number = '';
    this.orderFilter.order_source_id = '';
    this.orderFilter.shipping_method_id = '';
    this.orderFilter.shipping_line_id = '';
    this.orderFilter.private_order = '';
    this.orderFilter.collectType = '';

    this.router.navigate(['/dashboard/shipping/listorders']);
    this.orderFilter.triggerSearchFn();
  }

}
