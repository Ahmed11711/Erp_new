import { ChangeDetectorRef, Component, ElementRef, OnDestroy, QueryList, Renderer2, ViewChild, ViewChildren } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { MatExpansionPanel } from '@angular/material/expansion';
import { NavigationEnd, Route, Router } from '@angular/router';
import { AuthService } from '../auth/auth.service';
import { catchError, filter, interval, of, startWith, Subscription, switchMap } from 'rxjs';
import { NotificationService } from '../notification/service/notification.service';
import Swal from 'sweetalert2';
import { FilterOrderService } from '../shipping/services/filter-order.service';
import { WhatsAppService } from '../whatsapp/services/whatsapp.service';
import { RbacService } from '../core/rbac/rbac.service';
import { RBAC_ROUTE } from '../guards/rbac-route-data';
import { SystemLockDialogComponent } from '../system-lock/system-lock-dialog.component';
import { SystemLockService } from '../system-lock/system-lock.service';


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

  showSalesReportsMenu(): boolean {
    return this.rbac.can('orders.view')
      || this.rbac.canAny([...RBAC_ROUTE.reportsProductSales])
      || this.rbac.canAny([...RBAC_ROUTE.categoriesReportsHome])
      || this.rbac.canAny([...RBAC_ROUTE.reportsCategoryProfit])
      || this.rbac.canAny([...RBAC_ROUTE.reportsFinance]);
  }

  /** قسم «الإيصالات والأذونات»: يظهر إن وُجدت صلاحية القسم أو عروض الأسعار */
  get showReceiptsAndPermissionsMenu(): boolean {
    return this.rbac.canAny(['nav.receipts', 'nav.receipts.admin', 'nav.receipts.quotes'])
      || this.canAccessPriceQuotes()
      || this.canAccessPriceList()
      || this.rbac.can('orders.convert_from_offer');
  }

  /** روابط إنشاء/قائمة عروض الأسعار */
  canAccessPriceQuotes(): boolean {
    return this.rbac.canAccessPriceQuotes();
  }

  /** كتالوج قائمة الأسعار (صور المنتجات) */
  canAccessPriceList(): boolean {
    return this.rbac.canAny([...RBAC_ROUTE.priceList]);
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
    return this.rbac.can('orders.view')
      || this.rbac.can('orders.convert_from_offer')
      || this.showShopifySubmenu();
  }

  /** True if the current user is assigned to at least one WhatsApp number */
  hasWhatsAppAccess = false;
  /** صلاحية إدارة تعيين المستخدمين لأرقام الواتساب (صفحة admin/whatsapp-management) */
  canAssignWhatsAppNumbers = false;

  openASide:boolean = true;
  asideMode:string = 'side';

  @ViewChild('sidenavNav', { read: ElementRef }) sidenavNav?: ElementRef<HTMLElement>;
  @ViewChildren(MatExpansionPanel) menuPanels?: QueryList<MatExpansionPanel>;
  menuSearchTerm = '';

  /** مسار المحتوى تحت /dashboard لتمييز التبويب النشط */
  dashboardNavUrl = '';

  private navUrlSub?: Subscription;
  private permissionsCookieSub?: Subscription;
  private notifyPollSub?: Subscription;
  private counterSub?: Subscription;
  private lockSub?: Subscription;

  constructor(private loginService:AuthService , private notificationService:NotificationService, private route:Router,
    private orderFilter :FilterOrderService, private renderer: Renderer2,
    private whatsappService: WhatsAppService,
    public rbac: RbacService,
    public systemLock: SystemLockService,
    private dialog: MatDialog,
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
    this.loginService.syncSessionFromServer().subscribe(() => {
      this.applyUserDepartmentFromCookie();
      this.refreshWhatsAppAssignFlag();
      this.cdr.detectChanges();
    });
    this.permissionsCookieSub = this.loginService.permissionsCookieUpdated.subscribe(() => {
      this.refreshWhatsAppAssignFlag();
      this.cdr.detectChanges();
    });
    this.userName = this.loginService.userName();
    this.dashboardNavUrl = this.route.url;
    this.navUrlSub = this.route.events
      .pipe(filter((e): e is NavigationEnd => e instanceof NavigationEnd))
      .subscribe((e) => {
        this.dashboardNavUrl = e.urlAfterRedirects;
      });

    this.refreshWhatsAppAssignFlag();

    this.systemLock.startWatching();
    this.lockSub = this.systemLock.status$.subscribe(() => this.cdr.detectChanges());

    if (!this.systemLock.isRestricted) {
      this.whatsappService.getUserPhoneNumbers().subscribe({
        next: (res) => {
          const list = res?.data ?? res ?? [];
          this.hasWhatsAppAccess = Array.isArray(list) && list.length > 0;
        },
        error: () => { this.hasWhatsAppAccess = false; }
      });
    }

    this.counterSub = this.notificationService.counterVal.subscribe((value) => {
      this.counter = value;
    });

    let isFirstNotifyPoll = true;
    this.notifyPollSub = interval(10 * 60 * 1000)
      .pipe(
        startWith(0),
        switchMap(() => {
          if (this.systemLock.isRestricted) {
            return of(null);
          }
          return this.notificationService.getById().pipe(catchError(() => of(null)));
        })
      )
      .subscribe(res => {
        if (!res) {
          return;
        }
        this.applyNotificationPayload(res, !isFirstNotifyPoll);
        isFirstNotifyPoll = false;
      });

  }

  private applyUserDepartmentFromCookie(): void {
    const raw = this.loginService.getUser();
    this.user = typeof raw === 'string' ? raw.trim() : '';
  }

  private refreshWhatsAppAssignFlag(): void {
    const perms = this.loginService.getPermission();
    this.canAssignWhatsAppNumbers =
      (Array.isArray(perms) && perms.includes('assign to whatsapp number'))
      || this.rbac.can('whatsapp.assign_numbers');
  }

  private applyNotificationPayload(res: any, playSound: boolean): void {
    this.notifications = res.notifications ?? [];
    this.confirmedOrder = res.confirmedOrder ?? [];
    this.confirmedOrderCount = res.confirmedOrdersCount ?? 0;
    const number = this.notifications.filter(elm => elm.is_read === 0).length;
    this.notificationService.setCounter(number);
    if (playSound && this.oldNotifiNumber < this.notifications.length && this.oldNotifiNumber !== 0) {
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
    }
    this.oldNotifiNumber = this.notifications.length;
  }

  ngOnDestroy(): void {
    this.navUrlSub?.unsubscribe();
    this.permissionsCookieSub?.unsubscribe();
    this.notifyPollSub?.unsubscribe();
    this.counterSub?.unsubscribe();
    this.lockSub?.unsubscribe();
    this.systemLock.stopWatching();
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
    if (this.systemLock.isRestricted) {
      return;
    }
    this.orderFilter.resetFilters();
    this.orderFilter.order_number = e;
    if (e === 'confirmedOrder') {
      this.orderFilter.order_number = '';
      this.orderFilter.confimedOrderNotifi = true;
    }

    this.route.navigate(['/dashboard/shipping/listorders']);
    this.orderFilter.triggerSearchFn();
  }

  getNotifiy(){
    if (this.systemLock.isRestricted) {
      return;
    }
    this.notificationService.getById().subscribe(res=>{
      this.applyNotificationPayload(res, false);
    });
  }


  backPage(){
    window.history.back();
  }

  /** فلترة عناصر القائمة الجانبية بالبحث النصّي دون حذف أي عنصر من الـ DOM */
  filterMenu(term: string): void {
    const q = (term ?? '').toString().toLowerCase().replace(/\s+/g, ' ').trim();
    this.menuSearchTerm = q;
    const root = this.sidenavNav?.nativeElement;
    if (!root) { return; }

    const norm = (s: string | null | undefined) =>
      (s ?? '').toLowerCase().replace(/\s+/g, ' ').trim();

    const itemSelector = 'a[mat-list-item], mat-list-item';

    // إعادة الضبط عند مسح البحث — بدون إغلاق الأقسام (كان يغلق دروب داون عروض الأسعار بالخطأ)
    if (!q) {
      root.querySelectorAll('.menu-hide').forEach(el => el.classList.remove('menu-hide'));
      return;
    }

    // 1) إظهار/إخفاء الروابط والعناصر حسب المطابقة
    const items = Array.from(root.querySelectorAll<HTMLElement>(itemSelector));
    items.forEach(el => {
      const match = norm(el.textContent).includes(q);
      el.classList.toggle('menu-hide', !match);
    });

    const panels = Array.from(root.querySelectorAll<HTMLElement>('mat-expansion-panel'));

    // 2) الأقسام التي يطابق عنوانها البحث: أظهر كل عناصرها
    panels.forEach(panel => {
      const header = panel.querySelector('mat-expansion-panel-header');
      if (norm(header?.textContent).includes(q)) {
        panel.querySelectorAll<HTMLElement>(itemSelector)
          .forEach(el => el.classList.remove('menu-hide'));
      }
    });

    // 3) إظهار القسم إذا طابق عنوانه أو كان يحتوي أي عنصر ظاهر
    panels.forEach(panel => {
      const header = panel.querySelector('mat-expansion-panel-header');
      const headerMatch = norm(header?.textContent).includes(q);
      const hasVisibleItem = !!panel.querySelector(
        'a[mat-list-item]:not(.menu-hide), mat-list-item:not(.menu-hide)'
      );
      panel.classList.toggle('menu-hide', !(headerMatch || hasVisibleItem));
    });

    // 4) فتح الأقسام الظاهرة — مطابقة بالمضيف DOM وليس بالـ index (يتكسّر مع *ngIf والقوائم المتداخلة)
    const instances = this.menuPanels?.toArray() ?? [];
    instances.forEach((inst) => {
      const host = this.expansionPanelHost(inst);
      if (!host || !root.contains(host)) {
        return;
      }
      if (host.classList.contains('menu-hide')) {
        inst.close();
      } else {
        inst.open();
      }
    });
  }

  /** عنصر المضيف لـ mat-expansion-panel من الـ ViewChild */
  private expansionPanelHost(panel: MatExpansionPanel): HTMLElement | null {
    const anyPanel = panel as unknown as {
      _elementRef?: ElementRef<HTMLElement>;
      _body?: ElementRef<HTMLElement>;
    };
    if (anyPanel._elementRef?.nativeElement) {
      return anyPanel._elementRef.nativeElement;
    }
    const body = anyPanel._body?.nativeElement;
    return body?.closest('mat-expansion-panel') ?? null;
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

  showLockButton(): boolean {
    return this.systemLock.canLock
      || this.systemLock.canUnlock
      || this.systemLock.canControl
      || this.rbac.canLockSystem()
      || this.rbac.canUnlockSystem();
  }

  openSystemLockDialog(): void {
    this.dialog.open(SystemLockDialogComponent, {
      width: '560px',
      maxWidth: '95vw',
      autoFocus: false,
      direction: 'rtl',
    });
  }

  onRestrictedClick(event?: Event): void {
    if (!this.systemLock.isRestricted) {
      return;
    }
    const el = event?.target as HTMLElement | null;
    if (el?.closest('mat-expansion-panel-header, .side-search, .lock-msg-card, .lock-msg-overlay')) {
      return;
    }
    event?.preventDefault();
    event?.stopPropagation();
    this.systemLock.openMessageCard();
  }

  onRestrictedMouseDown(event?: Event): void {
    if (!this.systemLock.isRestricted) {
      return;
    }
    const el = event?.target as HTMLElement | null;
    if (el?.closest('mat-expansion-panel-header, .side-search, .lock-msg-card, .lock-msg-overlay')) {
      return;
    }
    event?.preventDefault();
  }

  closeLockMessage(event?: Event): void {
    event?.preventDefault();
    event?.stopPropagation();
    this.systemLock.closeMessageCard();
  }

}
