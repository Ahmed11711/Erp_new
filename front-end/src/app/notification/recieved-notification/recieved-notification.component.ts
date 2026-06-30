import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from 'src/app/auth/auth.service';
import { UserService } from 'src/app/manage-system/services/user.service';
import { NotificationService } from 'src/app/notification/service/notification.service';
import { FilterOrderService } from 'src/app/shipping/services/filter-order.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';

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

  param: Record<string, string | number> = {};

  constructor(private notificationService:NotificationService , private userService:UserService , private router:Router,
    private orderFilter :FilterOrderService, private authService:AuthService,
    public rbac: RbacService ) { }

  ngOnInit(){
    this.user = this.authService.getUser();
    if (this.notificationService.recievedParam && Object.keys(this.notificationService.recievedParam).length) {
      this.param = { ...this.notificationService.recievedParam };
    }
    this.applyParamToForm();
    this.loadData();
    this.getUsers();
  }

  private applyParamToForm(): void {
    setTimeout(() => {
      if (this.param['type']) {
        const type = document.getElementById('type') as HTMLSelectElement | null;
        if (type) type.value = String(this.param['type']);
      }
      if (this.param['send_from']) {
        const sendFrom = document.getElementById('send_from') as HTMLSelectElement | null;
        if (sendFrom) sendFrom.value = String(this.param['send_from']);
      }
      if (this.param['is_read'] !== undefined) {
        const status = document.getElementById('status') as HTMLSelectElement | null;
        if (status) status.value = String(this.param['is_read']);
      }
      if (this.param['order_id']) {
        const orderNumber = document.getElementById('order_number') as HTMLInputElement | null;
        if (orderNumber) orderNumber.value = String(this.param['order_id']);
      }
      if (this.param['review_status_user'] !== undefined) {
        const reviewStatus = document.getElementById('review_status_user') as HTMLSelectElement | null;
        if (reviewStatus) reviewStatus.value = String(this.param['review_status_user']);
      }
    });
  }

  getUsers(){
    this.userService.usersForNotifi().subscribe((res:any)=>this.userdata = res);
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.loadData();
  }

  onrecieveDateChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.recieveDate = target.value;
    this.page = 0;
    this.loadData();
  }

  clearSearch() {
    this.param = {};
    this.notificationService.recievedParam = {};
    this.page = 0;

    const type = document.getElementById('type') as HTMLSelectElement | null;
    if (type) type.selectedIndex = 0;
    const sendFrom = document.getElementById('send_from') as HTMLSelectElement | null;
    if (sendFrom) sendFrom.selectedIndex = 0;
    const status = document.getElementById('status') as HTMLSelectElement | null;
    if (status) status.selectedIndex = 0;
    const orderNumber = document.getElementById('order_number') as HTMLInputElement | null;
    if (orderNumber) orderNumber.value = '';
    const reviewStatus = document.getElementById('review_status_user') as HTMLSelectElement | null;
    if (reviewStatus) reviewStatus.selectedIndex = 0;

    this.loadData();
  }

  search(event?: Event){
    const target = event?.target as HTMLElement | undefined;
    if (target?.id === 'type') {
      this.param['type'] = (target as HTMLSelectElement).value;
      delete this.param['review_status_user'];
      this.page = 0;
    }
    if (target?.id === 'send_from') {
      this.param['send_from'] = (target as HTMLSelectElement).value;
      this.page = 0;
    }
    if (target?.id === 'status') {
      this.param['is_read'] = (target as HTMLSelectElement).value;
      this.page = 0;
    }
    if (target?.id === 'order_number') {
      this.param['order_id'] = (target as HTMLInputElement).value.trim();
      this.page = 0;
    }
    if (target?.id === 'review_status_user') {
      this.param['review_status_user'] = (target as HTMLSelectElement).value;
      this.page = 0;
    }

    this.notificationService.recievedParam = { ...this.param };
    this.loadData();
  }

  private loadData(): void {
    this.notificationService.recievedNotifiy(this.pageSize, this.page + 1, this.param).subscribe((res: any) => {
      this.data = res.data;
      this.length = res.total;
      this.pageSize = res.per_page;
    });
  }

  openNotifiy(elm:any){

    if (elm?.is_read == 0) {
      this.notificationService.readNotify(elm.id).subscribe(res=>{

      })
    }

    if (elm?.type === 'كشف حضور' && elm?.ref) {
      this.router.navigate(['/dashboard/hr/workinghoursdetails', elm.ref]);
      return;
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
    this.orderFilter.category_id = null;

    this.router.navigate(['/dashboard/shipping/listorders']);
    this.orderFilter.triggerSearchFn();
  }

}
