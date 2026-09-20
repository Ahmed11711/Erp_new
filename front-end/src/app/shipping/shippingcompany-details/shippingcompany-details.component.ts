import { Component } from '@angular/core';
import { ShippingCompanyService } from '../services/shipping-company.service';
import { ActivatedRoute } from '@angular/router';
import Swal from 'sweetalert2';
import { OrderService } from '../services/order.service';
import { AuthService } from 'src/app/auth/auth.service';
import { BanksService } from 'src/app/financial/services/banks.service';
import { DialogCancelRefuseOrderComponent } from '../dialog-cancel-refuse-order/dialog-cancel-refuse-order.component';
import { MatDialog } from '@angular/material/dialog';
import { DialogNotificationNoteComponent } from '../dialog-notification-note/dialog-notification-note.component';
import { UserService } from 'src/app/manage-system/services/user.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import {
  canRefuseFromShippingRow,
  isCompanyCustomerType,
  isIndividualCustomerType,
} from '../utils/order-refuse.utils';
import { allowsManualShippingCollection } from '../utils/order-collect-eligibility.utils';

@Component({
  selector: 'app-shippingcompany-details',
  templateUrl: './shippingcompany-details.component.html',
  styleUrls: ['./shippingcompany-details.component.css']
})
export class ShippingcompanyDetailsComponent {

  data:any[]=[];
  banks :any = [];
  tableData:any[]=[];
  collectDate!:string;
  shippingDate!:string;
  status!:string;
  reviewed!:string;
  name!:string;
  user!:string;
  id!:number;

  totalOrders!:number;
  totalPrice:number=0;

  totalcheck:number=0;

  length = 0;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  constructor(private shippingService:ShippingCompanyService,private order: OrderService , private route:ActivatedRoute , private authService:AuthService,
    private bankService:BanksService , public dialog: MatDialog , private userService:UserService,
    private rbac: RbacService,
    ){
  }

  ngOnInit(){
    this.id = this.route.snapshot.params['id'];
    this.param['id'] = this.route.snapshot.params['id'];
    this.user = this.authService.getUser();
    this.bankService.bankSelect().subscribe(res=>this.banks=res);
    if (this.user == 'Admin') {
      this.reviewed = 'all';
    }
    this.status = 'تم شحن';
    this.getUsers();
    this.search(arguments);
  }

  userdata:any[]=[];
  getUsers(){
    this.userService.usersForNotifi().subscribe((res:any)=>this.userdata = res);
  }

  sendOneOrder:boolean = false;
  orderToSend:any[]=[];
  sendOrder(item:any){
    this.sendOneOrder =true;
    this.orderToSend = [item];
  }

  notificationOrders:any[]=[];
  sendNotification(user:any): void {
    this.notificationOrders = this.orderToSend.map(elm => elm.order);
    if (this.notificationOrders.length >0) {
      const dialogRef = this.dialog.open(DialogNotificationNoteComponent, {
        width: '25%',data: {user,orders: this.notificationOrders ,  refreshData: ()=>this.search(arguments)},
      });
      dialogRef.afterClosed().subscribe(result => {
        this.notificationOrders = [];
        this.sendOneOrder = false;
        this.orderToSend = [];
      });
    }
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
  }

  trackByDetailId(_index: number, row: { id?: number; order_id?: number }): number {
    return row?.id ?? row?.order_id ?? _index;
  }

  private normStatus(v: unknown): string {
    return v == null ? '' : String(v).trim();
  }

  /** ملخّص يظهر في عمود التعديلات: عداد التعديلات + تأكيد التسليم + آخر أحداث التتبع */
  modificationsSummary(elm: any): {
    hasAny: boolean;
    editsCount: number;
    deliveryDate: string | null;
    deliveryBatch: string | null;
    recentTracking: { line: string; title: string }[];
  } {
    const od = elm?.order?.order_details;
    const editsCount = Math.max(0, Number(od?.edits ?? 0));
    const deliveryDate = od?.delivery_date != null && od.delivery_date !== '' ? String(od.delivery_date) : null;
    const deliveryBatch =
      od?.delivery_batch_code != null && String(od.delivery_batch_code).trim() !== ''
        ? String(od.delivery_batch_code).trim()
        : null;
    const raw = elm?.order?.traking;
    const list = Array.isArray(raw) ? raw : [];
    const recentTracking = list.slice(0, 5).map((tr: any) => {
      const action = tr?.action != null ? String(tr.action) : '';
      const date = tr?.date != null ? String(tr.date) : tr?.created_at != null ? String(tr.created_at).slice(0, 16) : '';
      const user = tr?.user?.name != null ? String(tr.user.name) : '';
      const line = user ? `${action} — ${date} — ${user}` : `${action} — ${date}`;
      return { line: line.replace(/ — $/, '').trim(), title: line };
    });
    const hasAny = editsCount > 0 || !!deliveryDate || recentTracking.length > 0;
    return { hasAny, editsCount, deliveryDate, deliveryBatch, recentTracking };
  }

  /** قائمة الإجراءات (تحصيل / إشعار) مفعّلة لطلبات «تم شحن» أو «تم التسليم» المتطابقة مع حالة الطلب */
  menuVisibleForRow(elm: any): boolean {
    const st = this.normStatus(elm?.status);
    const os = this.normStatus(elm?.order?.order_status);
    return (
      (st === 'تم شحن' && os === 'تم شحن') ||
      (st === 'تم التسليم' && os === 'تم التسليم')
    );
  }

  canCollectFromRow(elm: any): boolean {
    const st = this.normStatus(elm?.status);
    const os = this.normStatus(elm?.order?.order_status);
    if (st !== 'تم التسليم' || os !== 'تم التسليم') {
      return false;
    }
    const done = elm.is_done === true || elm.is_done === 1 || elm.is_done === '1';
    if (done) {
      return false;
    }
    const order = elm?.order;
    if (order && !allowsManualShippingCollection(order)) {
      return false;
    }
    return true;
  }

  isIndividualCustomer(order: any): boolean {
    return isIndividualCustomerType(order?.customer_type);
  }

  isCompanyCustomer(order: any): boolean {
    return isCompanyCustomerType(order?.customer_type);
  }

  canRefuseOrderMenu(): boolean {
    const allowed = new Set([
      'Admin',
      'Shipping Management',
      'Operation Management',
      'Finance and operations management',
      'Operation Specialist',
      'Logistics Specialist',
      'Data Entry',
      'Review Management',
    ]);
    return allowed.has(this.user) || this.rbac.can('orders.change_status');
  }

  canShowShippingActionsMenu(): boolean {
    return this.canRefuseOrderMenu() || this.canCollectRole;
  }

  canRefuseFromRow(elm: any): boolean {
    return canRefuseFromShippingRow(elm?.order?.order_status, elm?.status, elm?.is_done);
  }

  get canCollectRole(): boolean {
    const u = this.user;
    return u === 'Admin' || u === 'Operation Management' || u === 'Finance and operations management'
      || u === 'Operation Specialist' || u === 'Logistics Specialist';
  }

  shippingAccountsQuery(orderId?: number): Record<string, string | number> {
    const q: Record<string, string | number> = { company_id: this.id };
    if (this.name) {
      q['company_name'] = this.name;
    }
    if (orderId) {
      q['order_id'] = orderId;
    }
    return q;
  }

  oncollectDateChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.collectDate = target.value;
    this.search(event);

  }
  onshippingDateChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.shippingDate = target.value;
    this.search(event);

  }

  refuseOrder(type:string,id:number){
    const dialogRef = this.dialog.open(DialogCancelRefuseOrderComponent, {
      width: '25%',data: {data: {id,action:'refused',type} ,  refreshData: ()=>this.search(arguments)},
    });
    dialogRef.afterClosed().subscribe(result => {

    });
  }

  changeOrderStatus(type:string,id:number,title:string,action:string){
    if (type =='شركة' && action=='refused') {
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
                        console.log('yes');
                        this.order.chngeStatus(id, action, value , amount,bank,0).subscribe(res => {
                          console.log(res);
                          if (res) {
                            Swal.fire({
                              icon : 'success',
                              timer:3000,
                              showConfirmButton:false,
                              titleText: 'تم ارسال اشعار للادمن',
                              position: 'bottom-end',
                              toast: true,
                              timerProgressBar: true,
                            }).then(res=>{
                              this.search(arguments);
                            })
                          };
                        });

                      } else{
                        Swal.fire({
                          icon:'error',
                          title: 'اختر الخزينة',
                        })
                      }
                    }
                  });

                } else if (result.dismiss == "cancel") {
                    this.order.chngeStatus(id, action, value , amount,0,0).subscribe(res => {
                      console.log(res);
                      if (res) {
                        Swal.fire({
                          icon : 'success',
                          timer:3000,
                          showConfirmButton:false,
                          titleText: 'تم ارسال اشعار للادمن',
                          position: 'bottom-end',
                          toast: true,
                          timerProgressBar: true,
                        }).then(res=>{
                          this.search(arguments);
                        })
                      };
                    });
                }

                return undefined;
              });

              return undefined;
            }
          });

          return undefined;
        }
      });
    }
  }



  param = {};
  search(event:any){
    if(this.collectDate){
      this.param['collectDate']=this.collectDate;
    }
    if(this.shippingDate){
      this.param['shippingDate']=this.shippingDate;
    }

    if(event.target?.id=='status'){
      this.status = event.target?.value;
    }

    if(event.target?.id=='review'){
      this.reviewed = event.target?.value;
    }

    if(this.status){
      this.param['order_status']=this.status;
    }

    if(this.reviewed){
      this.param['reviewed']=this.reviewed;
    }

    if (this.reviewed == 'all') {
      delete this.param['reviewed'];
    }

    this.shippingService.search(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      const od = res?.orderDetails;
      const rows = Array.isArray(od?.data) ? od.data : [];
      const total = Number(od?.total ?? 0);
      const perPage = Number(od?.per_page ?? this.pageSize) || this.pageSize;

      this.data = rows;
      this.tableData = rows;
      this.length = total;
      this.totalOrders = total;
      this.pageSize = perPage;
      this.totalPrice = res?.totalNet ?? 0;
      this.name = res?.name?.name ?? '';
    })
  }




}
