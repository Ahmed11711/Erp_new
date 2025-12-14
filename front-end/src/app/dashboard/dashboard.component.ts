import { Component, Renderer2 } from '@angular/core';
import { NavigationEnd, Route, Router } from '@angular/router';
import { AuthService } from '../auth/auth.service';
import { filter, interval, startWith, switchMap } from 'rxjs';
import { NotificationService } from '../notification/service/notification.service';
import Swal from 'sweetalert2';
import { FilterOrderService } from '../shipping/services/filter-order.service';


@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent {

  permission:any[]=[];

  notifications:any[]=[];
  confirmedOrder:any[]=[];
  confirmedOrderCount:number = 0;
  counter:number = 0;
  oldNotifiNumber:number = 0;

  user!:string;
  userName!:string;

  openASide:boolean = true;
  asideMode:string = 'side';



  constructor(private loginService:AuthService , private notificationService:NotificationService, private route:Router,
    private orderFilter :FilterOrderService, private renderer: Renderer2,

    ) {

    }


  ngOnInit() {
    const width = this.renderer.selectRootElement(window).innerWidth;
    if (width < 600) {
      this.openASide = false;
    } else {
      this.openASide = true;
    }


    this.user = this.loginService.getUser();
    this.userName = this.loginService.userName();

    this.getNotifiy();
    interval(10 * 60 * 1000)
    .pipe(
      startWith(0),
      switchMap(() => this.notificationService.getById())
    )
    .subscribe(res => {

      this.notifications = res.notifications;
      this.confirmedOrder = res.confirmedOrder;
      this.confirmedOrderCount = res.confirmedOrdersCount;

      const number = this.notifications.filter(elm => elm.is_read === 0).length;
      this.notificationService.setCounter(number);
      this.notificationService.counterVal.subscribe((value) => {
        this.counter = value;
      });
      if (this.oldNotifiNumber < this.notifications.length && this.oldNotifiNumber !=0) {
        const audio = new Audio('assets/sound/notifi.wav');
        Swal.fire({
          titleText: 'تنبيه: اشعار جديد',
          timer: 6000,
          showConfirmButton: false,
          position: 'bottom-end',
          icon: 'info',
          toast: true,
          timerProgressBar: true,
          didOpen: () => {
            audio.play();
          }
        });
        this.oldNotifiNumber = this.notifications.length;
      }
    });

  }

  openNotifiy(e:any){
    this.orderFilter.order_number = e;
    this.orderFilter.customer_type = '';
    this.orderFilter.order_type = '';
    this.orderFilter.order_status = '';
    this.orderFilter.shipping_company_id = '';
    this.orderFilter.need_by_date = '';
    this.orderFilter.status_date = '';
    this.orderFilter.delivery_date = '';
    this.orderFilter.order_date = '';
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
    this.orderFilter.confimedOrderNotifi = false;
    if (e === 'confirmedOrder') {
      this.orderFilter.order_number = '';
      this.orderFilter.confimedOrderNotifi = true;
    }

    this.route.navigate(['/dashboard/shipping/listorders']);
    this.orderFilter.triggerSearchFn();
  }

  getNotifiy(){
    this.notificationService.getById().subscribe(res=>{
      this.notifications = res.notifications;
      this.confirmedOrder = res.confirmedOrder;
      this.confirmedOrderCount = res.confirmedOrdersCount;
      this.oldNotifiNumber = this.notifications.length;
      const number = this.notifications.filter(elm => elm.is_read === 0).length;
      this.notificationService.setCounter(number);
      this.notificationService.counterVal.subscribe((value) => {
        this.counter = value;
      });
    });
  }


  backPage(){
    window.history.back();
  }

  clickNotification(elm:any){
    if (elm?.is_read == 0) {
      console.log('here');

      this.notificationService.readNotify(elm.id).subscribe(res=>{
        console.log(res);
        if (res) {
          this.getNotifiy();
        }
      })
    }

  }

  logout(){
    this.loginService.logOut();
    location.reload();
  }

}
