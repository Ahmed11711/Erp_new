import { ChangeDetectorRef, Component, OnDestroy, Renderer2 } from '@angular/core';
import { NavigationEnd, Route, Router } from '@angular/router';
import { AuthService } from '../auth/auth.service';
import { filter, forkJoin, interval, startWith, Subscription, switchMap } from 'rxjs';
import { NotificationService } from '../notification/service/notification.service';
import Swal from 'sweetalert2';
import { FilterOrderService } from '../shipping/services/filter-order.service';
import { WhatsAppService } from '../whatsapp/services/whatsapp.service';
import { RbacService } from '../core/rbac/rbac.service';
import { RBAC_ROUTE } from '../guards/rbac-route-data';


@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnDestroy {

  permission:any[]=[];

  notifications:any[]=[];
  confirmedOrder:any[]=[];
  confirmedOrderCount:number = 0;
  counter:number = 0;
  oldNotifiNumber:number = 0;

  user!:string;
  userName!:string;

  readonly RBAC = RBAC_ROUTE;

  /** Combined RBAC for main «الحسابات» sidebar block */
  get showAccountingSection(): boolean {
    return this.rbac.can('finance.view');
  }

  canReportsSection(): boolean {
    return this.rbac.canAny([...RBAC_ROUTE.reportsSection]);
  }

  /** قسم «الإيصالات والأذونات»: يظهر إن وُجدت صلاحية القسم أو عروض الأسعار فقط */
  get showReceiptsAndPermissionsMenu(): boolean {
    return this.rbac.canAny(['nav.receipts', 'nav.receipts.quotes']);
  }

  /** لوحة Shopify في التبويب السريع (أعلى المحتوى) */
  get showShopifyQuickTabs(): boolean {
    return this.rbac.canAny(['nav.shopify.dashboard', 'nav.shopify', 'system.rbac']);
  }

  /** Shopify: طلبات/منتجات في لوحة الشحن */
  canAccessShopifyDashboard(): boolean {
    return this.rbac.canAny(['nav.shopify.dashboard', 'nav.shopify', 'system.rbac']);
  }

  /** Shopify: مزامنة يدوية واختبار اتصال */
  canAccessShopifySettings(): boolean {
    return this.rbac.canAny(['nav.shopify.settings', 'nav.shopify', 'system.rbac']);
  }

  showShopifySubmenu(): boolean {
    return this.canAccessShopifyDashboard() || this.canAccessShopifySettings();
  }

  showShippingMenuSection(): boolean {
    return this.rbac.can('orders.view') || this.showShopifySubmenu();
  }

  /** True if the current user is assigned to at least one WhatsApp number */
  hasWhatsAppAccess = false;
  /** صلاحية إدارة تعيين المستخدمين لأرقام الواتساب (صفحة admin/whatsapp-management) */
  canAssignWhatsAppNumbers = false;

  openASide:boolean = true;
  asideMode:string = 'side';

  /** مسار المحتوى تحت /dashboard لتمييز التبويب النشط */
  dashboardNavUrl = '';

  private navUrlSub?: Subscription;
  private permissionsCookieSub?: Subscription;

  constructor(private loginService:AuthService , private notificationService:NotificationService, private route:Router,
    private orderFilter :FilterOrderService, private renderer: Renderer2,
    private whatsappService: WhatsAppService,
    public rbac: RbacService,
    private cdr: ChangeDetectorRef,
    ) {

    }


  ngOnInit() {
    const width = this.renderer.selectRootElement(window).innerWidth;
    if (width < 600) {
      this.openASide = false;
    } else {
      this.openASide = true;
    }


    this.applyUserDepartmentFromCookie();
    forkJoin({
      dept: this.loginService.syncSessionDepartmentFromServer(),
      permissions: this.loginService.syncSessionPermissionsFromServer(),
    }).subscribe(() => {
      this.applyUserDepartmentFromCookie();
      const perms = this.loginService.getPermission();
      this.canAssignWhatsAppNumbers =
        (Array.isArray(perms) && perms.includes('assign to whatsapp number'))
        || this.rbac.can('whatsapp.assign_numbers');
      this.cdr.detectChanges();
    });
    this.permissionsCookieSub = this.loginService.permissionsCookieUpdated.subscribe(() => {
      const perms = this.loginService.getPermission();
      this.canAssignWhatsAppNumbers =
        (Array.isArray(perms) && perms.includes('assign to whatsapp number'))
        || this.rbac.can('whatsapp.assign_numbers');
      this.cdr.detectChanges();
    });
    this.userName = this.loginService.userName();
    this.dashboardNavUrl = this.route.url;
    this.navUrlSub = this.route.events
      .pipe(filter((e): e is NavigationEnd => e instanceof NavigationEnd))
      .subscribe((e) => {
        this.dashboardNavUrl = e.urlAfterRedirects;
      });

    const perms = this.loginService.getPermission();
    this.canAssignWhatsAppNumbers =
      (Array.isArray(perms) && perms.includes('assign to whatsapp number'))
      || this.rbac.can('whatsapp.assign_numbers');

    this.whatsappService.getUserPhoneNumbers().subscribe({
      next: (res) => {
        const list = res?.data ?? res ?? [];
        this.hasWhatsAppAccess = Array.isArray(list) && list.length > 0;
      },
      error: () => { this.hasWhatsAppAccess = false; }
    });

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

  private applyUserDepartmentFromCookie(): void {
    const raw = this.loginService.getUser();
    this.user = typeof raw === 'string' ? raw.trim() : '';
  }

  ngOnDestroy(): void {
    this.navUrlSub?.unsubscribe();
    this.permissionsCookieSub?.unsubscribe();
  }

  isHomeDashTabActive(): boolean {
    const path = (this.dashboardNavUrl || '').split('?')[0].replace(/\/$/, '') || '';
    return path === '/dashboard';
  }

  isShopifyDashTabActive(): boolean {
    const path = (this.dashboardNavUrl || '').split('?')[0];
    return path.startsWith('/dashboard/shopify');
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
    this.orderFilter.category_id = null;
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
      this.notificationService.readNotify(elm.id).subscribe(res=>{
        if (res) {
          this.getNotifiy();
        }
      })
    }
  }

  openNotification(elm: any): void {
    this.clickNotification(elm);
    if (elm?.type === 'كشف حضور' && elm?.ref) {
      this.route.navigate(['/dashboard/hr/workinghoursdetails', elm.ref]);
      return;
    }
    this.openNotifiy(elm?.ref);
  }

  logout(){
    this.loginService.logOut();
    location.reload();
  }

}
