import { Component, ElementRef, HostListener, Inject, OnDestroy, Renderer2 } from '@angular/core';
import { BreakpointObserver } from '@angular/cdk/layout';
import { OrderService } from '../services/order.service';
import { OrderInvoicePrintService } from '../services/order-invoice-print.service';
import {MatDialog, MAT_DIALOG_DATA, MatDialogRef} from '@angular/material/dialog';
import { NavigationEnd, Router } from '@angular/router';
import { ShippingCompanyService } from '../services/shipping-company.service';
import { HttpClient } from '@angular/common/http';
import { OrderSourceService } from '../services/order-source.service';
import { ShippingWayService } from '../services/shipping-way.service';
import { FilterOrderService } from '../services/filter-order.service';
import { DatePipe } from '@angular/common';
import { ShippingLinesService } from '../services/shipping-lines.service';
import Swal from 'sweetalert2';
import { UserService } from 'src/app/manage-system/services/user.service';
import { DialogNotificationNoteComponent } from '../dialog-notification-note/dialog-notification-note.component';
import { DialogOrderNotificationComponent } from '../dialog-order-notification/dialog-order-notification.component';
import { DialogCancelRefuseOrderComponent } from '../dialog-cancel-refuse-order/dialog-cancel-refuse-order.component';
import { DialogOrderRollbackComponent } from '../dialog-order-rollback/dialog-order-rollback.component';
import { DialogWhatsAppMessageComponent } from 'src/app/whatsapp/components/dialog-whatsapp-message/dialog-whatsapp-message.component';
import { BanksService } from 'src/app/financial/services/banks.service';
import { SafeService } from 'src/app/accounting/services/safe.service';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service';
import { CollectionCompanyService } from '../services/collection-company.service';
import { AuthService } from 'src/app/auth/auth.service';
import { WhatsAppService } from 'src/app/whatsapp/services/whatsapp.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { RBAC_ROUTE } from 'src/app/guards/rbac-route-data';
import { Subject, firstValueFrom, takeUntil } from 'rxjs';
import { environment } from 'src/env/env';
import {
  collectRenewPrepaidParams,
  promptReturnPrepaidAmount,
  requiresManualPrepaidRefundSelection,
} from '../utils/order-renew-prepaid.flow';
import {
  isCompanyCustomerType,
  isIndividualCustomerType,
  isRefuseEligibleOrderStatus,
} from '../utils/order-refuse.utils';
import { canShowCollectOrderMenu as isCollectOrderMenuVisible } from '../utils/order-collect-eligibility.utils';
import { orderStatusFilterOptions } from '../utils/order-status-visibility.utils';

@Component({
  selector: 'app-list-orders',
  templateUrl: './list-orders.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './list-orders.component.css']
})
export class ListOrdersComponent implements OnDestroy {
  user!:string;
  /** قسم الحسابات المالية: عرض فقط (طلبات مشحونة/محصّلة) دون إجراءات. */
  get isFinancialAccountsReadonly(): boolean {
    return this.user === 'Financial Accounts';
  }
  /** True if the current user is assigned to at least one WhatsApp number */
  hasWhatsAppAccess = false;

  trackById(index: number, item: any): number {
    return item?.id;
  }

  isShopifyOrder(item: any): boolean {
    return item?.shopify_order_id != null && item?.shopify_order_id !== '';
  }

  isShopifyReviewed(item: any): boolean {
    return !!item?.shopify_reviewed_at;
  }

  canShopifyReview(): boolean {
    return this.rbac.canAny([...RBAC_ROUTE.shopifyOrderReview]);
  }

  /** خيارات فلتر حالة الطلب حسب صلاحيات RBAC والقسم. */
  get statusFilterOptions() {
    return orderStatusFilterOptions(this.user, (slug) => this.rbac.can(slug));
  }

  canPostponeOrder(): boolean {
    const allowed = new Set([
      'admin',
      'shipping management',
      'operation management',
      'operation specialist',
      'logistics specialist',
    ]);
    const dept = String(this.user || '').trim().toLowerCase();
    if (allowed.has(dept)) {
      return true;
    }
    return this.rbac.can('orders.change_status');
  }

  /** تعديل الطلب: صلاحية orders.edit + حالة/نوع الطلب المسموح */
  canEditOrder(item: any): boolean {
    if (!this.rbac.can('orders.edit')) {
      return false;
    }
    if (item?.order_status === 'تم شحن') {
      return false;
    }
    const editableTypes = ['جديد', 'طلب استبدال', 'طلب مرتجع', 'طلب صيانة'];
    const editableStatuses = ['طلب جديد', 'طلب مؤكد', 'شحن جزئي'];
    return editableTypes.includes(item?.order_type) && editableStatuses.includes(item?.order_status);
  }

  /** تحصيل متغير للطلبات المشحونة أو المسلَّمة */
  canEditShippedOrder(item: any): boolean {
    return this.rbac.can('orders.edit')
      && (item?.order_status === 'تم شحن' || item?.order_status === 'تم التسليم');
  }

  shopifyReviewTooltip(item: any): string {
    if (!this.isShopifyOrder(item)) return '';
    if (this.isShopifyReviewed(item)) {
      const name = item?.shopify_reviewer?.name || '—';
      return `تمت المراجعة بواسطة ${name}`;
    }
    return 'طلب Shopify — بانتظار المراجعة';
  }

  openShopifyReview(item: any): void {
    if (!item?.id) return;
    this.router.navigate(['/dashboard/shipping/orderdetails', item.id], {
      queryParams: { shopifyReview: '1' },
    });
  }

  /** Shopify وغيره: «فرد» / «افراد» / individual — وأي نوع غير «شركة». */
  isIndividualCustomer(item: any): boolean {
    return isIndividualCustomerType(item?.customer_type);
  }

  isCompanyCustomer(item: any): boolean {
    return isCompanyCustomerType(item?.customer_type);
  }

  canRefuseOrderMenu(): boolean {
    return this.canChangeOrderStatusMenu();
  }

  /** إلغاء الطلب: صلاحية orders.change_status أو أقسام التشغيل/الإدخال/خدمة العملاء. */
  canCancelOrderMenu(): boolean {
    return this.canChangeOrderStatusMenu();
  }

  canCancelOrder(item: any): boolean {
    const status = String(item?.order_status ?? '').trim();
    return ['طلب جديد', 'طلب مؤكد', 'مؤجل'].includes(status);
  }

  /** تجديد الطلب: صلاحية orders.change_status أو أقسام مسموحة (مع تقييد إدارة الشحن). */
  canRenewOrder(item: any): boolean {
    const status = String(item?.order_status ?? '').trim();
    if (!['ملغي', 'أرشيف', 'ارشيف', 'مؤجل', 'رفض استلام', 'طلب مؤكد'].includes(status)) {
      return false;
    }
    if (this.rbac.can('orders.change_status')) {
      return true;
    }
    const dept = String(this.user || '').trim().toLowerCase();
    if (dept === 'admin' || dept === 'data entry' || dept === 'customer service') {
      return true;
    }
    if (dept === 'shipping management') {
      return status === 'رفض استلام' || status === 'مؤجل';
    }
    return false;
  }

  private canChangeOrderStatusMenu(): boolean {
    if (this.rbac.can('orders.change_status')) {
      return true;
    }
    const allowed = new Set([
      'admin',
      'shipping management',
      'operation management',
      'finance and operations management',
      'operation specialist',
      'logistics specialist',
      'data entry',
      'review management',
      'customer service',
    ]);
    const dept = String(this.user || '').trim().toLowerCase();
    return allowed.has(dept);
  }

  /** رفض استلام: متاح لكل الحالات باستثناء المحصّل / الملغي / المرفوض / الأرشيف. */
  canRefuseOrder(item: any): boolean {
    return isRefuseEligibleOrderStatus(item?.order_status);
  }

  canShowCollectOrderMenu(item: any): boolean {
    return isCollectOrderMenuVisible(item);
  }

  canShipOrder(item: any): boolean {
    if (!item) return false;
    const status = item.order_status;
    if (this.isCompanyCustomer(item)) {
      return ['طلب جديد', 'طلب مؤكد', 'شحن جزئي', 'مؤجل'].includes(status);
    }
    if (this.isIndividualCustomer(item)) {
      return ['طلب مؤكد', 'مؤجل', 'طلب جديد'].includes(status);
    }
    return false;
  }

  canShipOrderMenu(): boolean {
    const roles = new Set([
      'Admin',
      'Operation Management',
      'Finance and operations management',
      'Operation Specialist',
      'Logistics Specialist',
      'Shipping Management',
    ]);
    return roles.has(this.user);
  }

  /** قائمة الإجراءات بجانب رقم الطلب — Spatie: assign to whatsapp number، أو مستخدم معيّن لرقم واتساب (hasWhatsAppAccess) */
  canAssignWhatsAppNumbers = false;
  orders :any = [];
  banks :any = [];
  safes: any[] = [];
  serviceAccounts: any[] = [];
  collectionCompanies: any[] = [];
  currentPageData :any = [];
  companies :any = [];
  location:any[]=[];
  cities:any[]=[];
  governName:boolean=false;
  orderSources:any[]=[];
  shippingWays:any[]=[];
  shippingLines:any[]=[];
  products:any[]=[];
  private productSearchTimer: ReturnType<typeof setTimeout> | null = null;
  private textFilterTimer: ReturnType<typeof setTimeout> | null = null;

  length = 50;
  pageSize = 100;
  page = 0;
  pageSizeOptions = [100,15,50];

  /** عرض البطاقات بدل الجدول تحت عرض ~768px */
  isMobileView = false;
  /** تفاصيل مفتوحة لبطاقات الموبايل */
  mobileExpandedIds = new Set<number>();
  private readonly destroy$ = new Subject<void>();

  constructor(private orderSource:OrderSourceService ,private shippingWay: ShippingWayService , private datePipe:DatePipe,
    private http:HttpClient ,private order: OrderService, private orderInvoicePrint: OrderInvoicePrintService,
    public dialog: MatDialog, private company:ShippingCompanyService,
    private filterService:FilterOrderService , private shippingLine:ShippingLinesService,
    private userService:UserService ,private renderer: Renderer2 ,private el: ElementRef, private bankService:BanksService,
    private safeService: SafeService,
    private serviceAccountsService: ServiceAccountsService,
    private collectionCompanyService: CollectionCompanyService,
    private authService:AuthService, private router: Router,
    private whatsappService: WhatsAppService,
    private rbac: RbacService,
    private breakpointObserver: BreakpointObserver
    ) {
      document.addEventListener('scroll', (event) => {
        this.onListenerTriggered(event);
      }, true);
  }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    const perms = this.authService.getPermission();
    this.canAssignWhatsAppNumbers =
      Array.isArray(perms) && perms.includes('assign to whatsapp number');
    this.whatsappService.getUserPhoneNumbers().subscribe({
      next: (res) => {
        const list = res?.data ?? res ?? [];
        this.hasWhatsAppAccess = Array.isArray(list) && list.length > 0;
      },
      error: () => { this.hasWhatsAppAccess = false; }
    });
    this.order.getProducts().subscribe((result:any)=>this.allProducts = result || []);

    this.filterService.value.subscribe(res=>{
      this.filter(arguments);
    })

    this.getUsers();



    this.company.shippingCompanySelect().subscribe((res:any)=>{
      this.companies = res
    });

    this.http.get('assets/egypt/governorates.json').subscribe((data:any)=>this.location=data);
    this.http.get('assets/egypt/cities.json').subscribe((data:any)=>{
      this.cities = data.filter((elem:any)=>elem.governorate_id == 1);
    });

    this.bankService.bankSelect().subscribe(res=>this.banks=res);
    this.safeService.getAll().subscribe((res: any) => {
      this.safes = res?.data ?? res ?? [];
    });
    this.serviceAccountsService.index().subscribe((res: any) => {
      this.serviceAccounts = res ?? [];
    });
    this.collectionCompanyService.select().subscribe((res: any) => {
      this.collectionCompanies = res ?? [];
    });
    this.orderSource.data().subscribe(reuslt=>this.orderSources = reuslt);
    this.shippingWay.data().subscribe(result=>this.shippingWays = result);
    this.shippingLine.dataLines().subscribe(result=>this.shippingLines = result);

    this.breakpointObserver
      .observe(['(max-width: 767.98px)'])
      .pipe(takeUntil(this.destroy$))
      .subscribe((state) => {
        this.isMobileView = state.matches;
        if (!state.matches) {
          this.mobileExpandedIds = new Set();
        }
      });

    this.filter(null);
  }

  ngOnDestroy(): void {
    if (this.productSearchTimer) {
      clearTimeout(this.productSearchTimer);
    }
    if (this.textFilterTimer) {
      clearTimeout(this.textFilterTimer);
    }
    this.destroy$.next();
    this.destroy$.complete();
  }

  toggleMobileOrderCard(id: number): void {
    const next = new Set(this.mobileExpandedIds);
    if (next.has(id)) {
      next.delete(id);
    } else {
      next.add(id);
    }
    this.mobileExpandedIds = next;
  }

  isMobileOrderExpanded(id: number): boolean {
    return this.mobileExpandedIds.has(id);
  }

  onListenerTriggered(event: Event): void {
    const element = document.getElementById('menuoption') as HTMLElement;
    const scrollContainerScrollTop = this.el?.nativeElement?.offsetParent?.scrollTop;
    if (element) {
      if (scrollContainerScrollTop > 100) {
        this.renderer?.addClass(element, 'sticky-menu');
      } else {
        this.renderer?.removeClass(element, 'sticky-menu');
      }
    }

  }

  catword = 'category_name';
  allProducts: any[] = [];

  private stripHighlightTags(value: string): string {
    return String(value ?? '').replace(/<\/?b>/gi, '');
  }

  selectedCategoryLabel = (item: any): string => {
    if (!item?.category_name) {
      return '';
    }
    const name = this.stripHighlightTags(String(item.category_name));
    const code = String(item.item_code ?? '').trim();
    return code ? `${name} (${code})` : name;
  };

  filterCategorySearch = (items: any[], query: string) => {
    const q = (query ?? '').trim().toLowerCase();
    if (!q) {
      return [...items];
    }
    return items.filter((item) => {
      const name = this.stripHighlightTags(String(item.category_name ?? '')).toLowerCase();
      const code = String(item.item_code ?? '').toLowerCase();
      return name.includes(q) || code.includes(q);
    });
  };

  searchOrderProducts(query: string): void {
    const q = (query ?? '').trim();
    if (!q) {
      this.products = [];
      return;
    }

    this.http.get<any>(`${environment.Url}/categories/search`, {
      params: {
        itemsPerPage: 50,
        warehouse: 'مخزن منتج تام',
        category_name: q,
      },
    }).subscribe({
      next: (res) => {
        this.products = (res?.data || []).map((item: any) => ({
          ...item,
          category_name: item.category_name || '',
        }));
      },
      error: () => {
        this.products = this.filterCategorySearch(this.allProducts, q);
      },
    });
  }

  onProductInputChanged(value: string): void {
    if (this.productSearchTimer) {
      clearTimeout(this.productSearchTimer);
    }
    const q = (value ?? '').trim();
    if (!q) {
      this.products = [];
      return;
    }
    this.productSearchTimer = setTimeout(() => this.searchOrderProducts(q), 300);
  }

  onTextFilterInput(event: Event): void {
    const target = event.target as HTMLInputElement | null;
    if (!target?.id) {
      return;
    }

    if (this.textFilterTimer) {
      clearTimeout(this.textFilterTimer);
    }

    this.textFilterTimer = setTimeout(() => {
      this.applyTextFilter(target.id, target.value);
      this.page = 0;
      this.filter(null);
    }, 400);
  }

  private applyTextFilter(id: string, rawValue: string): void {
    const value = String(rawValue ?? '').trim();

    switch (id) {
      case 'customer_name':
        this.filterService.customer_name = value;
        break;
      case 'customer_phone': {
        let number = value.replace(/\s+/g, '');
        if (number.startsWith('+2') || number.startsWith('2')) {
          number = number.substring(2);
        }
        this.filterService.customer_phone = number;
        break;
      }
      case 'order_number':
        this.filterService.order_number = value;
        break;
      case 'shippment_number':
        this.filterService.shippment_number = value;
        break;
    }
  }

  productChange(event: { id?: number } | null): void {
    if (!event?.id) {
      return;
    }
    this.filterService.category_id = event.id;
    this.page = 0;
    this.filter(null);
  }

  resetProductFilter(): void {
    this.filterService.category_id = null;
    this.products = [];
    this.page = 0;
    this.filter(null);
  }

  userdata:any[]=[];
  getUsers(){
    this.userService.usersForNotifi().subscribe((res:any)=>this.userdata = res);
  }

  govern(event){
    if (event.target.value == "القاهرة") {
      this.governName = true;
    } else{
      this.governName = false;
    }
    this.filter(event);
  }

  calculateDateDifference(needByDate: string, orderDate: string): number {
    const needByDateObj = new Date(needByDate);
    const orderDateObj = new Date(orderDate);

    // Calculate the difference in milliseconds
    const differenceInMilliseconds = needByDateObj.getTime() - orderDateObj.getTime();

    // Convert milliseconds to days
    const differenceInDays = Math.floor(differenceInMilliseconds / (1000 * 60 * 60 * 24));
    console.log(differenceInDays);

    return differenceInDays;
  }


  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.filter(arguments);
  }


  checkboxVip(id:number){
    if (this.user == 'Admin' || this.user == 'Data Entry' || this.user == 'Shipping Management' || this.user == 'Customer Service') {
      this.order.vipOrder(id).subscribe((res:any)=>{
        this.filter(arguments);
      })
    }
  }

  checkboxShortage(id:number){
    if (this.user == 'Admin' || this.user == 'Data Entry' || this.user == 'Shipping Management' || this.user == 'Customer Service') {
      this.order.shortageOrder(id).subscribe((res:any)=>{
        this.filter(arguments);
      })
    }
  }

  openDialog(id:number, note:string,status:string): void {
    const dialogRef = this.dialog.open(DialogOverviewExampleDialog, {
      data: {name: note,id,status},
    });

    dialogRef.afterClosed().subscribe(result => {
      console.log('The dialog was closed');

    });
  }


  need_by_datekey = false;
  need_by_date:any;
  OnDateChange(iso: string | null){
    if (!iso) {
      this.need_by_datekey = false;
      return;
    }
    this.need_by_date = iso;
    this.need_by_datekey = true;
    this.filter('');
  }

  status_datekey = false;
  status_date:any;
  OnStatusDateChange(iso: string | null){
    if (!iso) {
      this.status_datekey = false;
      return;
    }
    this.status_date = iso;
    this.status_datekey = true;
    this.filter('');
  }

  order_datekey = false;
  order_date:any;
  OnOrderDateChange(iso: string | null){
    if (!iso) {
      this.order_datekey = false;
      return;
    }
    this.order_date = iso;
    this.order_datekey = true;
    this.filter('');
  }

  deliver_datekey = false;
  delivery_date:any;
  OnDeliverDateChange(iso: string | null){
    if (!iso) {
      this.deliver_datekey = false;
      return;
    }
    this.delivery_date = iso;
    this.deliver_datekey = true;
    this.filter('');
  }

  receive(id:number){
    let bank;
    let maintenReason='';
    Swal.fire({
      title: ' سبب الصيانة',
      input: 'text',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة'
        }
        if (value !== '') {
            maintenReason = value;
        }
        return undefined
      }
    }).then((result) => {
      console.log(result);
      if (result.isConfirmed) {
        Swal.fire({
          title: 'هل تم استلام صافي القيمة',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'نعم',
          cancelButtonText: 'لا',
        }).then((result:any) => {
          if (result.isConfirmed) {
            const banks = this.banks;
            const bankSelectOptions = banks.reduce((options, bank) => {
              options[bank.id] = bank.name;
              return options;
            }, {});

            Swal.fire({
              title: 'اختر الخزينة',
              input: 'select',
              inputOptions: bankSelectOptions,
              inputPlaceholder: 'اختر الخزينة',
              showCancelButton: true,
              confirmButtonText: 'تأكيد',
              cancelButtonText: 'إلغاء',
            }).then((bankResult) => {
              if (bankResult.isConfirmed) {
                const selectedBankId = bankResult.value;
                if (selectedBankId) {
                  bank = selectedBankId;
                  this.order.receivedOrder(id,bank,'',maintenReason).subscribe((res:any)=>{
                    console.log(res);

                    if (res) {
                      this.filter(arguments);
                      Swal.fire({
                        icon : 'success',
                        timer:1500,
                        showConfirmButton:false,
                      });
                    }
                  });
                } else{
                  Swal.fire({
                    icon:'error',
                    title: 'اختر الخزينة',
                  })
                }
              }
            });

          } else if (result.dismiss == 'cancel') {
            bank=null;
            Swal.fire({
              icon:'info',
              input: 'text',
              inputPlaceholder: 'السبب',
              showCancelButton: true,
              inputValidator: (value) => {
                if (!value) {
                  return 'يجب ادخال ملاحظة'
                }
                if (value !== '') {
                  this.order.receivedOrder(id,bank,value,maintenReason).subscribe((res:any)=>{
                    console.log(res);
                    if (res) {
                      this.filter(arguments);
                      Swal.fire({
                        icon : 'success',
                        timer:1500,
                        showConfirmButton:false,
                      })
                    }
                  })
                }
                return undefined
              }
            })
          }
        })
      }

    });


  }

  maintain(id:number){
    console.log(id);
    Swal.fire({
      title: 'ادخل تكلفة الصيانة',
      input: 'number',
      inputLabel: 'التكلفة',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة'
        }
        if (value !== '') {
          this.order.maintainOrder(id,{maintenance_cost:value}).subscribe(res=>{
            this.filter(arguments);
            console.log(res);
          }
          )
        }
        return undefined
      }
    })
  }

  getStatusColor(status: string): string {
    switch (status) {
      case 'طلب جديد':
        return 'new';
        case 'جديد':
          return 'new';
      case 'طلب مؤكد':
        return 'confirmed';
      case 'تم شحن':
        return 'shipped';
      case 'شحن جزئي':
        return 'partshipped';
      case 'تم الاستلام':
        return 'received';
      case 'مؤجل':
        return 'postponed';
      case 'رفض استلام':
        return 'refuse';
      case 'ملغي':
        return 'canceled';
        case 'تم التحصيل':
          return 'collected';
          case 'تم التسليم':
          return 'delivered';
          case 'تم الصيانة':
          return 'fixed';
          case 'أرشيف' :
          return 'archived';
      default:
        return '';
    }
  }

  confirmDelivery(orderId: number) {
    this.order.checkDeliveryTransfer(orderId).subscribe(
      (check) => {
        const msg = check.needs_transfer
          ? 'هل تم تسليم الطلب للعميل؟ ستنتقل المديونية من العميل إلى شركة الشحن/المندوب.'
          : 'هل تم تسليم الطلب للعميل؟';

        Swal.fire({
          title: 'تأكيد التسليم',
          text: msg,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'نعم، تم التسليم',
          cancelButtonText: 'إلغاء',
          input: 'text',
          inputPlaceholder: 'ملاحظة (اختياري)',
          inputValidator: () => undefined
        }).then((result) => {
          if (result.isConfirmed) {
            this.order.deliverOrder(orderId, { note: result.value || '' }).subscribe(
              (res: any) => {
                if (res.message === 'success') {
                  Swal.fire('تم', 'تم تأكيد تسليم الطلب بنجاح', 'success');
                  location.reload();
                }
              },
              (err) => {
                Swal.fire('خطأ', err?.error?.message || 'حدث خطأ', 'error');
              }
            );
          }
        });
      },
      () => {
        Swal.fire({
          title: 'تأكيد التسليم',
          text: 'هل تم تسليم الطلب للعميل؟',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'نعم، تم التسليم',
          cancelButtonText: 'إلغاء',
          input: 'text',
          inputPlaceholder: 'ملاحظة (اختياري)',
          inputValidator: () => undefined
        }).then((result) => {
          if (result.isConfirmed) {
            this.order.deliverOrder(orderId, { note: result.value || '' }).subscribe(
              (res: any) => {
                if (res.message === 'success') {
                  Swal.fire('تم', 'تم تأكيد تسليم الطلب بنجاح', 'success');
                  location.reload();
                }
              },
              (err) => {
                Swal.fire('خطأ', err?.error?.message || 'حدث خطأ', 'error');
              }
            );
          }
        });
      }
    );
  }

  reviewFn(){
    this.order.reviewOrder({orders:this.googleSheetData}).subscribe(res=>{
      console.log(res);

      if (res) {
        this.filter(arguments);
        Swal.fire({
          icon : 'success',
          timer:1500,
          showConfirmButton:false,
        })
      }
    });

  }

  /**
   * أدمن: إعادة بناء قيود الفاتورة (ORD-*) من بيانات الطلب الحالية لنطاق «تاريخ الطلب»،
   * ثم إعادة حساب أرصدة شجرة الحسابات. لا تُعدّ قيود التحصيل الفعلية.
   */
  async openOrdersAccountingReconcileWizard(): Promise<void> {
    if (this.user !== 'Admin') {
      return;
    }
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const defaultTo = `${yyyy}-${mm}-${dd}`;
    const firstOfMonth = `${yyyy}-${mm}-01`;

    const step1 = await Swal.fire({
      title: 'تسوية قيود المحاسبة للطلبات',
      html: `
        <p class="text-right small text-muted mb-2" style="max-width:100%;">
          يُعاد إنشاء قيود الفاتورة (ORD-*) من إجمالي الطلب والخصم وإيراد الشحن وربط ذمم شركة الشحن/التحصيل الحالي.
          <strong>الطلبات الملغية والمؤرشفة تُستبعد تلقائياً.</strong>
          قيود التحصيل والدفعات الإضافية المسجّلة لاحقاً لا تُلغى. بعد الانتهاء تُحدَّث أرصدة الشجرة بالكامل.
        </p>
        <label class="d-block text-right fw-bold">من تاريخ الطلب</label>
        <input id="swal-ord-acc-from" type="date" class="swal2-input" style="width:100%;box-sizing:border-box;" value="${firstOfMonth}">
        <label class="d-block text-right fw-bold mt-2">إلى تاريخ الطلب</label>
        <input id="swal-ord-acc-to" type="date" class="swal2-input" style="width:100%;box-sizing:border-box;" value="${defaultTo}">
        <label class="d-flex align-items-start mt-3 text-right gap-2" style="cursor:pointer;">
          <input type="checkbox" id="swal-ord-acc-prepaid" class="mt-1">
          <span class="small">إعادة بناء قيود الدفعة المقدمة عند إنشاء الطلب (ORD-PREPAID) — للاستخدام النادر فقط؛ إن وُجدت تحصيلات جزئية لاحقة قد لا تطابق السجل التاريخي.</span>
        </label>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'معاينة العدد',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const fromEl = document.getElementById('swal-ord-acc-from') as HTMLInputElement | null;
        const toEl = document.getElementById('swal-ord-acc-to') as HTMLInputElement | null;
        const prepaidEl = document.getElementById('swal-ord-acc-prepaid') as HTMLInputElement | null;
        const dateFrom = fromEl?.value ?? '';
        const dateTo = toEl?.value ?? '';
        if (!dateFrom || !dateTo) {
          Swal.showValidationMessage('حدد تاريخ البداية والنهاية');
          return false;
        }
        if (dateFrom > dateTo) {
          Swal.showValidationMessage('"من" يجب أن يكون قبل أو مساوياً لـ "إلى"');
          return false;
        }
        const maxDays = 731;
        const diffMs = new Date(dateTo).getTime() - new Date(dateFrom).getTime();
        const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
        if (diffDays > maxDays) {
          Swal.showValidationMessage(`نطاق التاريخ (${diffDays} يوم) يتجاوز الحد المسموح (${maxDays} يوم)`);
          return false;
        }
        return {
          date_from: dateFrom,
          date_to: dateTo,
          rebuild_prepaid: !!prepaidEl?.checked,
        };
      },
    });

    const payload = step1.value as { date_from: string; date_to: string; rebuild_prepaid: boolean } | undefined;
    if (!step1.isConfirmed || !payload) {
      return;
    }

    try {
      Swal.fire({
        title: 'جاري تحميل المعاينة...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });

      const preview: any = await firstValueFrom(
        this.order.previewOrdersAccountingReconcile({
          date_from: payload.date_from,
          date_to: payload.date_to,
        })
      );

      Swal.close();

      if (preview?.would_exceed_limit) {
        await Swal.fire({
          icon: 'warning',
          title: 'تجاوز الحد',
          html: `عدد الطلبات النشطة (${preview.orders_count}) يتجاوز الحد المسموح لكل تشغيل (${preview.max_orders_per_run}). قلّل نطاق التاريخ.`,
        });
        return;
      }

      if ((preview?.orders_count ?? 0) === 0) {
        await Swal.fire({
          icon: 'info',
          title: 'لا توجد طلبات',
          text: `لا توجد طلبات نشطة في الفترة من ${payload.date_from} إلى ${payload.date_to}.`,
        });
        return;
      }

      let statusBreakdown = '';
      const byStatus = preview?.by_status;
      if (byStatus && Object.keys(byStatus).length > 0) {
        const rows = Object.entries(byStatus)
          .map(([status, count]) => `<tr><td class="text-right px-2">${status}</td><td class="text-center px-2"><b>${count}</b></td></tr>`)
          .join('');
        statusBreakdown = `
          <table class="table table-sm table-bordered mt-2 mb-0" style="font-size:0.85rem;">
            <thead><tr><th class="text-right">الحالة</th><th class="text-center">العدد</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>`;
      }

      const excludedNote = (preview?.excluded_count ?? 0) > 0
        ? `<p class="text-right small text-warning mt-2 mb-0">سيتم تجاوز <b>${preview.excluded_count}</b> طلب ملغي/مؤرشف تلقائياً.</p>`
        : '';

      const runConfirm = await Swal.fire({
        icon: 'question',
        title: 'تأكيد التسوية',
        html: `
          <p class="text-right">سيُعاد ترحيل قيود الفاتورة لـ <b>${preview?.orders_count ?? 0}</b> طلب نشط بين
          <b>${payload.date_from}</b> و <b>${payload.date_to}</b>.
          ${payload.rebuild_prepaid ? '<br><strong class="text-danger">تضمين إعادة بناء الدفعات المقدمة مفعّل.</strong>' : ''}
          </p>
          ${statusBreakdown}
          ${excludedNote}
        `,
        showCancelButton: true,
        confirmButtonText: 'تنفيذ التسوية',
        cancelButtonText: 'رجوع',
        confirmButtonColor: '#d33',
      });

      if (!runConfirm.isConfirmed) {
        return;
      }

      Swal.fire({
        title: 'جاري التسوية...',
        html: '<p class="small text-muted">قد يستغرق ذلك بضع دقائق حسب عدد الطلبات. لا تغلق الصفحة.</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      const result: any = await firstValueFrom(
        this.order.runOrdersAccountingReconcile({
          date_from: payload.date_from,
          date_to: payload.date_to,
          rebuild_prepaid: payload.rebuild_prepaid,
        })
      );

      Swal.close();

      let failHtml = '';
      const fails = Array.isArray(result?.failures) ? result.failures.slice(0, 15) : [];
      if (fails.length) {
        failHtml =
          '<p class="text-right small mt-2">أول الطلبات الفاشلة:</p><ul class="text-right small" style="max-height:140px;overflow:auto;">' +
          fails.map((f: any) => `<li>#${f.order_id}: ${(f.error || '').toString().slice(0, 120)}</li>`).join('') +
          '</ul>';
      }

      const skippedHtml = (result?.orders_skipped_cancelled ?? 0) > 0
        ? `<p class="text-right small text-muted">تم تجاوز: <b>${result.orders_skipped_cancelled}</b> طلب ملغي/مؤرشف</p>`
        : '';

      await Swal.fire({
        icon: result?.success ? 'success' : 'error',
        title: result?.message || 'انتهى',
        html:
          `<p class="text-right">المعالجة بنجاح: <b>${result?.orders_processed ?? 0}</b> — فشل: <b>${result?.orders_failed ?? 0}</b></p>` +
          skippedHtml +
          failHtml,
      });
    } catch (err: any) {
      Swal.close();
      const msg = err?.error?.message || err?.message || 'حدث خطأ';
      Swal.fire({ icon: 'error', title: 'لم يكتمل الطلب', text: msg });
    }
  }

  sendOrder(item:any){
    this.sendOneOrder =true;
    this.orderToSend = [item];
  }
  orderToSend:any[]=[];
  sendOneOrder:boolean = false;

  sendWhatsAppMessage(item: any): void {
    if (item && item.customer_phone_1) {
      const dialogRef = this.dialog.open(DialogWhatsAppMessageComponent, {
        width: '40%',
        data: {
          order: item,
          refreshData: () => this.filter(arguments)
        },
      });
      dialogRef.afterClosed().subscribe(result => {
        // Handle closed dialog if needed
      });
    } else {
      Swal.fire({
        icon: 'warning',
        title: 'تنبيه',
        text: 'لا يوجد رقم هاتف للعميل',
      });
    }
  }

  /** Tooltip text loaded lazily (last WhatsApp lines). */
  waTooltipCache: Record<number, string> = {};

  /** Egypt numbers: 01… → +201… (not +21… which breaks lookup). */
  formatEgyptInternational(raw: string): string {
    let d = String(raw).replace(/\D/g, '');
    if (d.startsWith('0') && d.length === 11) {
      d = '20' + d.substring(1);
    } else if (d.length === 10 && d.startsWith('1')) {
      d = '20' + d;
    }
    return '+' + d;
  }

  onWaInfoHover(item: any): void {
    if (!item?.customer_phone_1 || this.waTooltipCache[item.id]) {
      return;
    }
    const phone = this.formatEgyptInternational(item.customer_phone_1);
    this.waTooltipCache[item.id] = 'جاري تحميل آخر الرسائل…';
    this.whatsappService.getWhatsAppSnippet(phone).subscribe({
      next: (res: any) => {
        if (res.success && res.lines?.length) {
          this.waTooltipCache[item.id] = res.lines.join(' · ');
        } else {
          this.waTooltipCache[item.id] = 'لا توجد رسائل بعد. اضغط لفتح المحادثة.';
        }
      },
      error: () => {
        this.waTooltipCache[item.id] = 'تعذر تحميل المحادثة. اضغط لفتح الدردشة.';
      },
    });
  }

  openChatWithCustomer(item: any): void {
    if (item && item.customer_phone_1) {
      const phone = this.formatEgyptInternational(item.customer_phone_1);
      this.router.navigate(['/dashboard/whatsapp/chat'], {
        queryParams: { phone },
      });
    } else {
      Swal.fire({
        icon: 'warning',
        title: 'تنبيه',
        text: 'لا يوجد رقم هاتف للعميل',
      });
    }
  }

  notificationOrders:any[]=[];
  sendNotification(user:any): void {
    if (this.sendOneOrder) {
      this.notificationOrders = this.orderToSend;
    } else {
      this.notificationOrders = this.googleSheetData
    }
    if (this.notificationOrders.length >0) {
      // Use WhatsApp dialog instead of notification dialog
      const order = this.notificationOrders[0];
      if (order && order.customer_phone_1) {
        const dialogRef = this.dialog.open(DialogWhatsAppMessageComponent, {
          width: '40%',
          data: {
            order: order,
            orders: this.notificationOrders,
            refreshData: () => this.filter(arguments)
          },
        });
        dialogRef.afterClosed().subscribe(result => {
          this.googleSheetData = [];
          this.notificationOrders = [];
          this.orderToSend = [];
        });
      } else {
        // Fallback to notification if no phone number
        const dialogRef = this.dialog.open(DialogNotificationNoteComponent, {
          width: '25%',data: {user,orders: this.notificationOrders ,  refreshData: ()=>this.filter(arguments)},
        });
        dialogRef.afterClosed().subscribe(result => {
          this.googleSheetData = [];
          this.notificationOrders = [];
          this.orderToSend = [];
        });
      }
    }
  }

  refuseOrder(type:string,id:number){
    const dialogRef = this.dialog.open(DialogCancelRefuseOrderComponent, {
      width: '25%',data: {data: {id,action:'refused',type} ,  refreshData: ()=>this.filter(arguments)},
    });
  }

  canRollbackOrder(item: { order_status?: string }): boolean {
    const status = item?.order_status ?? '';
    return status === 'تم التسليم' || status === 'تم التحصيل' || status === 'تم الاستلام';
  }

  canShowRollbackMenu(): boolean {
    const dept = String(this.user || '').trim();
    return dept === 'Admin'
      || dept === 'Operation Management'
      || dept === 'Operation Specialist'
      || dept === 'Logistics Specialist'
      || dept === 'Shipping Management'
      || this.rbac.canAny(['orders.change_status', 'orders.assign_driver']);
  }

  reopenOrder(item: { id: number; order_status?: string }): void {
    this.dialog.open(DialogOrderRollbackComponent, {
      width: '520px',
      maxWidth: '95vw',
      data: {
        orderId: item.id,
        currentStatus: item.order_status ?? '',
        refreshData: () => this.reloadOrdersList(),
      },
    });
  }

  postponeReceipt(type:string,id:number){
    Swal.fire({
      title: `${id} تأجيل استلام طلب رقم `,
      input: 'text',
      inputPlaceholder: 'السبب',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال السبب';
        }


        this.filter(arguments);

        return undefined;
      }
    });
  }


  private reloadOrdersList(): void {
    this.filter(undefined as unknown as Event);
  }

  private statusChangeHandlers(): {
    next: (res: unknown) => void;
    error: (err: { error?: { message?: string } }) => void;
  } {
    return {
      next: (res) => {
        if (res) {
          this.reloadOrdersList();
          Swal.fire({
            icon: 'success',
            timer: 3000,
            showConfirmButton: false,
            titleText: 'تم ارسال اشعار للادمن',
            position: 'bottom-end',
            toast: true,
            timerProgressBar: true,
          });
        }
      },
      error: (err) => {
        Swal.fire({
          icon: 'error',
          title: 'لم يتم تنفيذ الإجراء',
          text: err?.error?.message || 'حدث خطأ أثناء تحديث حالة الطلب',
        });
      },
    };
  }

  async changeOrderStatus(type:string,id:number,title:string,action:string,order:any){
    if (type =='شركة' && (action=='cancel' || action=='refused')) {
      Swal.fire({
        title: title,
        input: 'text',
        inputPlaceholder: 'السبب',
        showCancelButton: true,
        inputValidator: (value) => {
          if (!value) {
            return 'يجب ادخال ملاحظة';
          }

          Swal.fire({
            title: 'المبلغ المخصوم علي العميل',
            input: 'number',
            inputPlaceholder: 'ادخل المبلغ',
            showCancelButton: true,
            inputValidator: (amount) => {
              if (!amount) {
                return 'يجب ادخال المبلغ';
              }

              if (action=='cancel' && order.prepaid_amount >= amount) {
                this.order.chngeStatus(id, action, value , amount,0,0).subscribe(this.statusChangeHandlers());
                return;
              }

              let bank;
              Swal.fire({
                title: 'هل تم استلام المبلغ المخصوم ',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم',
                cancelButtonText: 'لا',
              }).then((result:any) => {
                if (result.isConfirmed) {
                  const banks = this.banks;
                  const bankSelectOptions = banks.reduce((options, bank) => {
                    options[bank.id] = bank.name;
                    return options;
                  }, {});

                  Swal.fire({
                    title: 'اختر الخزينة',
                    input: 'select',
                    inputOptions: bankSelectOptions,
                    inputPlaceholder: 'اختر الخزينة',
                    showCancelButton: true,
                    confirmButtonText: 'تأكيد',
                    cancelButtonText: 'إلغاء',
                  }).then((bankResult) => {
                    if (bankResult.isConfirmed) {
                      const selectedBankId = bankResult.value;
                      console.log('in');
                      if (selectedBankId) {
                        bank = selectedBankId;
                        this.order.chngeStatus(id, action, value , amount,bank,0).subscribe(this.statusChangeHandlers());

                      } else{
                        Swal.fire({
                          icon:'error',
                          title: 'اختر الخزينة',
                        })
                      }
                    }
                  });

                } else if (result.dismiss == "cancel") {
                    this.order.chngeStatus(id, action, value , amount,0,0).subscribe(this.statusChangeHandlers());
                }

                return undefined;
              });

              return undefined;
            }
          });

          return undefined;
        }
      });

    } else if (action=='refused') {
      const dialogRef = this.dialog.open(DialogCancelRefuseOrderComponent, {
        width: '25%',data: {data: {id,action} ,  refreshData: ()=>this.filter(arguments)},
      });
      dialogRef.afterClosed().subscribe(result => {

      });
    }  else{
      Swal.fire({
        title: title,
        input: 'text',
        inputPlaceholder: 'السبب',
        showCancelButton: true,
        inputValidator: (value) => {
          if (!value) {
            return 'يجب ادخال ملاحظة';
          }
          return null;
        },
      }).then(async (result) => {
        if (!result.isConfirmed || !result.value) {
          return;
        }

        const note = result.value;
        const param: Record<string, string | number> = {};

        if (action === 'cancel' && order.prepaid_amount > 0 && requiresManualPrepaidRefundSelection(order)) {
          const returnPaidMoney = await this.returnPrepaidAmount(order);
          if (Object.keys(returnPaidMoney).length === 0) {
            return;
          }
          param['moneyReturnedStatus'] = returnPaidMoney['returnedStatus'] as string;
          if (returnPaidMoney['returnedStatus'] === 'approved' && returnPaidMoney['returnedBank'] != null) {
            param['moneyReturnedBank'] = returnPaidMoney['returnedBank'];
          }
        }

        if (action === 'renew') {
          const renewParams = await collectRenewPrepaidParams(order, {
            banks: this.banks,
            safes: this.safes,
            serviceAccounts: this.serviceAccounts,
            collectionCompanies: this.collectionCompanies,
          });
          if (renewParams === null) {
            return;
          }
          Object.assign(param, renewParams);
        }

        this.order.chngeStatus(id, action, note, 0, 0, 0, param).subscribe(this.statusChangeHandlers());
      });
    }
  }

  async returnPrepaidAmount(order: { prepaid_amount?: number; bank_id?: number }) {
    return promptReturnPrepaidAmount(order, this.banks);
  }




  // -------------------------------------------------------------------------------------------filter


  filter(event: any):void{
    if (event?.target?.id ==="customer_type") {
      this.filterService.customer_type = event.target.value;
    }

    if (event?.target?.id ==="order_type") {
      this.filterService.order_type = event.target.value;
    }

    if (event?.target?.id ==="private_order") {
      if (event.target.value == 1) {
        this.filterService.private_order = '1';
      } else {
        this.filterService.private_order = '';
      }
    }

    if (event?.target?.id ==="order_status") {
      this.filterService.order_status = event.target.value;
    }

    if (event?.target?.id ==="collectType") {
      this.filterService.collectType = event.target.value;
    }

    if (event?.target?.id ==="shipping_company_id") {
      this.filterService.shipping_company_id = event.target.value;
    }

    if (this.need_by_datekey) {
      this.filterService.need_by_date = this.need_by_date;
    }

    if (this.status_datekey) {
      this.filterService.status_date = this.status_date;
    }

    if (this.order_datekey) {
      this.filterService.order_date = this.order_date;
    }

    if (this.deliver_datekey) {
      this.filterService.delivery_date = this.delivery_date;
    }

    if (event?.target?.id ==="vip") {
      if (event.target.checked) {
        this.filterService.vip = '1';
      } else {
        this.filterService.vip ='0';
      }
      // this.filterService.vip = event.target.checked;
    }

    if (event?.target?.id ==="shortage") {
      if (event.target.checked) {
        this.filterService.shortage = '1';
      } else {
        this.filterService.shortage ='0';
      }
    }

    if (event?.target?.id ==="paid") {
      if (event.target.checked) {
        this.filterService.paid = '1';
      } else {
        this.filterService.paid ='0';
      }
    }

    if (event?.target?.id ==="prepaidAmount") {
      if (event.target.checked) {
        this.filterService.prepaidAmount = '1';
      } else {
        this.filterService.prepaidAmount ='0';
      }
    }

    if (event?.target?.id ==="governorate") {
      this.filterService.governorate = event.target.value;
    }

    if (event?.target?.id ==="city") {
      this.filterService.city = event.target.value;
    }

    if (event?.target?.id ==="customer_name") {
      this.filterService.customer_name = event.target.value;
    }

    if (event?.target?.id ==="customer_phone") {
      let number = String(event.target.value ?? '').trim().replace(/\s+/g, '');
      if (number.startsWith('+2') || number.startsWith('2')) {
        number = number.substring(2);
      }
      this.filterService.customer_phone = number;
    }

    if (event?.target?.id ==="order_number") {
      this.filterService.order_number = event.target.value;
    }

    if (event?.target?.id ==="shippment_number") {
      console.log(event.target.value);
      this.filterService.shippment_number = event.target.value;
    }

    if (event?.target?.id ==="order_source_id") {
      this.filterService.order_source_id = event.target.value;
    }

    if (event?.target?.id ==="shipping_method_id") {
      this.filterService.shipping_method_id = event.target.value;
    }

    if (event?.target?.id ==="shipping_line_id") {
      this.filterService.shipping_line_id = event.target.value;
    }

    if (event?.target?.id ==="reviewfilter") {
      this.filterService.reviewed = event.target.value;
    }

    if (event?.target?.id === 'shopifyfilter') {
      const val = event.target.value;
      this.filterService.shopify = val === 'all' ? '' : val;
    }

    this.filterService.filter(this.pageSize,this.page+1).subscribe(result=>{
      this.orders = result.data;
      this.length=result.total;
      this.pageSize=result.per_page;

      this.orders.map(elm=>{
        elm.new_notification = elm.notifications.some(notification => notification.is_read == 0);
        elm.notification_number = elm.notifications.length;

        const needByDateObj = new Date();
        const orderDateObj = new Date(elm.order_date);

        const differenceInMilliseconds = needByDateObj.getTime() - orderDateObj.getTime();

        const differenceInDays = Math.floor(differenceInMilliseconds / (1000 * 60 * 60 * 24));
        elm.days = differenceInDays

      })
      this.syncSelectAllState();
    })

  }

  getOrdersForNumber(number){
    this.filterService.customer_phone = number;
    this.filter(arguments);
  }

  showNotification(notifications: any) {
    const dialogRef = this.dialog.open(DialogOrderNotificationComponent, {
      width: '50%',data: {notifications: notifications ,  refreshData: ()=>this.filter(arguments)},
    });
    dialogRef.afterClosed().subscribe(result => {
      this.filter(arguments);
    });
  }



  clearFilter(){
    window.location.reload();
  }

  googleSheetData: any[] = [];
  allOrdersSelected = false;
  printSelectedLoading = false;

  isOrderSelected(item: any): boolean {
    return this.googleSheetData.includes(item);
  }

  toggleSelectAllPrint(event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;
    this.allOrdersSelected = checked;
    if (checked) {
      this.googleSheetData = [...(this.orders || [])];
      return;
    }
    this.googleSheetData = [];
  }

  private syncSelectAllState(): void {
    const visible = this.orders || [];
    this.allOrdersSelected = visible.length > 0 && visible.every((item) => this.googleSheetData.includes(item));
  }

  printSelectedOrders(): void {
    if (!this.googleSheetData.length) {
      Swal.fire({
        icon: 'info',
        text: 'Please select at least one order to print.',
      });
      return;
    }

    const orderIds = this.googleSheetData.map((item) => Number(item.id)).filter((id) => id > 0);
    if (!orderIds.length) {
      Swal.fire({
        icon: 'info',
        text: 'Please select at least one order to print.',
      });
      return;
    }

    this.printSelectedLoading = true;
    this.order.printOrders(orderIds).subscribe({
      next: (res) => {
        this.printSelectedLoading = false;
        const opened = this.orderInvoicePrint.openPrintWindow(res.orders || [], {
          showInvoiceDate: res.show_invoice_date !== false,
          size: 'A4',
        });
        if (!opened) {
          Swal.fire({
            icon: 'warning',
            text: 'تعذر فتح نافذة الطباعة. تأكد من السماح بالنوافذ المنبثقة.',
          });
        }
      },
      error: (err) => {
        this.printSelectedLoading = false;
        Swal.fire({
          icon: 'error',
          text: err?.error?.message || 'تعذر تحضير الفواتير للطباعة',
        });
      },
    });
  }

  selectOrder(e: any, item: any) {
    this.sendOneOrder = false;

    if (e.target.checked) {

      if (!this.googleSheetData.includes(item)) {
        this.googleSheetData.push(item);
      }
    } else{

      this.googleSheetData = this.googleSheetData.filter(elm=> elm !== item);
    }

    this.syncSelectAllState();
  }

  googleSheet:any[]=[];
  postDataToGoogleSheet(sheet:string) {
    if (sheet === 'Mylerz') {
      this.googleSheet = [];
      this.googleSheetData.forEach(item=>{
        let desc = '';

        item.order_products.forEach((elm , i)=>{
          desc += `[ ${elm.category.category_name} (${elm.quantity}) ] -  `
        })

        let phone2 = ''
        if (item.customer_phone_2 != 'null') {
          phone2 = item.customer_phone_2
        }

        const orderData = {
          "Package_Serial" : item.id,
          "Customer_Name" : item.customer_name,
          "Mobile_No" : item.customer_phone_1,
          "Mobile_No2" : phone2,
          "Address" : item.address,
          "COD_Value" : String(item.net_total) ,
          "Description" : desc,
        }

        this.googleSheet.push(orderData);

      })

    } else if(sheet === 'Bosta'){
      this.googleSheet = [];
      this.googleSheetData.forEach(item=>{
        let desc = '';

        item.order_products.forEach((elm , i)=>{
          desc += `[ ${elm.category.category_name} (${elm.quantity}) ] - `
        })

        let phone2 = ''
        if (item.customer_phone_2 != 'null') {
          phone2 = item.customer_phone_2
        }

        const orderData = {
          "Order Reference" : item.id,
          "Full Name" : item.customer_name,
          "Phone" : item.customer_phone_1,
          "Second Phone" : phone2,
          "Address" : item.address,
          "Work address" : item.governorate,
          "City" : item.city || '',
          "Cash Amount" : String(item.net_total) ,
          "Items" : desc,
          "Package Description" : desc,
        }

        this.googleSheet.push(orderData);

      })

    } else if(sheet === 'Raya'){
      this.googleSheet = [];
      this.googleSheetData.forEach(item=>{
        let desc = '';

        item.order_products.forEach((elm , i)=>{
          desc += `[ ${elm.category.category_name} (${elm.quantity}) ] - `
        })

        let phone2 = ''
        if (item.customer_phone_2 != 'null') {
          phone2 = item.customer_phone_2
        }

        let shippingMethod = item.shipping_method.name;
        if(item.shipping_method.name == 'Small'){
          shippingMethod = 'Small weight  '
        }
        const orderData = {
          "Order Number" : item.id,
          "Consumer Name" : item.customer_name,
          "Consumer Mobile" : item.customer_phone_1,
          "Consumer Mobile2" : phone2,
          "consumer Address" : item.address,
          "Shipper Name" : 'Magalis Egypt',
          "Shipper Mobile" : '01111612681',
          "Shipper Districts" : 'Obour City',
          "Shipper Address" : 'الحي الخامس',
          "SKUs" : '0',
          "COD" : String(item.net_total) ,
          "Description" : desc,
          "Category Size" : shippingMethod,
        }

        this.googleSheet.push(orderData);

      })
    } else if (sheet === 'Lifters') {
      this.googleSheet = [];
      this.googleSheetData.forEach(item=>{
        let desc = '';

        item.order_products.forEach((elm , i)=>{
          desc += `[ ${elm.category.category_name} (${elm.quantity}) ] - `
        })

        let phone2 = ''
        if (item.customer_phone_2 != 'null') {
          phone2 = item.customer_phone_2
        }

        let shippingMethod = item.shipping_method.name;
        if(item.shipping_method.name == 'Small'){
          shippingMethod = 'Small weight  '
        }
        const orderData = {
          "Order Number" : item.id,
          "Customer Name" : item.customer_name,
          "Customer Number" : item.customer_phone_1,
          "Customer Number2" : phone2 ?? '',
          "Full Address" : item.address ?? '',
          "Governorate" : item.governorate ?? '',
          "City" : item.city ?? '',
          "Shipper Name" : 'Magalis Egypt',
          "Shipper Mobile" : '01111612681',
          "Shipper Districts" : 'Obour City',
          "Shipper Address" : 'الحي الخامس',
          "SKU" : '0',
          "COD" : String(item.net_total) ,
          "Item Description" : desc,
          "Creation Date": new Intl.DateTimeFormat('en-GB').format(new Date()),
        }

        this.googleSheet.push(orderData);
      })
    }

    const data = {data:JSON.stringify(this.googleSheet)}
    this.order.postGoogleSheet(sheet , data).subscribe((res:any)=>{
      console.log(res);
      if (res.message == 'Data added successfully') {
        Swal.fire({
          text: 'تم التسجيل بنجاح',
          timer:2000,
          icon: 'success',
          showConfirmButton:false
        })
      }
    },
    error => {
      console.log(error.error);
      if (error.error.message == 'No new data to add') {
        Swal.fire({
          text: 'تم التسجيل من قبل',
          timer:2000,
          icon: 'error',
          showConfirmButton:false
        })
      } else {
        Swal.fire({
          icon: 'warning',
          text:error.error
        })
      }

    })

  }


  private hasScrolled = false;

  orderScroll(id:string){
    this.filterService.scrollOrder = id;
  }

  ngAfterViewChecked(): void {
    const targetElement = document.getElementById(this.filterService?.scrollOrder);
    if (!this.hasScrolled) {
      if (targetElement) {
        targetElement.scrollIntoView();
        this.hasScrolled = true;
        this.filterService.scrollOrder='';
      }
    }
  }

}




@Component({
  selector: 'dialog-overview-example-dialog',
  templateUrl: 'dialog-overview-example-dialog.html',
  styleUrls: ['./list-orders.component.css']

})
export class DialogOverviewExampleDialog  {

  constructor(
    public dialogRef: MatDialogRef<DialogOverviewExampleDialog>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private order:OrderService,
    private router : Router
  ) {

  }

  onNoClick(): void {
    this.dialogRef.close();
  }

  changeStatus(id: number, status: string,note:string) {
    this.order.chngeStatus(id, status,note,0,0,0).subscribe(res=>{
      console.log(res);
      location.reload();
    })
  }
  postpone(form : any,id,status:string){
  this.changeStatus(id,status,form.value.note);
  }


}
