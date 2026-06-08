import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
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
import { SafeService } from 'src/app/accounting/services/safe.service';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service';

@Component({
  selector: 'app-shippingcompany-details',
  templateUrl: './shippingcompany-details.component.html',
  styleUrls: ['./shippingcompany-details.component.css']
})
export class ShippingcompanyDetailsComponent {

  data:any[]=[];
  banks :any = [];
  safes: any[] = [];
  serviceAccounts: any[] = [];
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

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  constructor(private shippingService:ShippingCompanyService,private order: OrderService , private route:ActivatedRoute , private authService:AuthService,
    private bankService:BanksService , public dialog: MatDialog , private userService:UserService,
    private safeService: SafeService, private serviceAccountsService: ServiceAccountsService
    ){
  }

  ngOnInit(){
    this.id = this.route.snapshot.params['id'];
    this.param['id'] = this.route.snapshot.params['id'];
    this.user = this.authService.getUser();
    this.bankService.bankSelect().subscribe(res=>this.banks=res);
    this.safeService.getAll().subscribe((res: any) => {
      this.safes = res?.data ?? res ?? [];
    });
    this.serviceAccountsService.index().subscribe((res: any) => {
      this.serviceAccounts = Array.isArray(res) ? res : (res?.data ?? []);
    });
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

  selectedOrders:any=[];

  selectOrder(e: any, item: any) {
    if (e.target.checked) {
      if (!this.canCollectFromRow(item)) {
        e.target.checked = false;
        return;
      }
      if (!this.selectedOrders.includes(item)) {
        this.selectedOrders.push(item);
      }
    } else {
      this.selectedOrders = this.selectedOrders.filter(elm => elm !== item);
    }
  }

  sendOneOrder:boolean = false;
  orderToSend:any[]=[];
  sendOrder(item:any){
    this.sendOneOrder =true;
    this.orderToSend = [item];
  }

  notificationOrders:any[]=[];
  sendNotification(user:any): void {
    if (this.sendOneOrder) {
      this.notificationOrders = this.orderToSend.map(elm => elm.order);
    } else {
      this.notificationOrders = this.selectedOrders.map(elm => elm.order);
    }
    if (this.notificationOrders.length >0) {
      const dialogRef = this.dialog.open(DialogNotificationNoteComponent, {
        width: '25%',data: {user,orders: this.notificationOrders ,  refreshData: ()=>this.search(arguments)},
      });
      dialogRef.afterClosed().subscribe(result => {
        this.notificationOrders = [];
        this.sendOneOrder = false;
        this.selectedOrders = [];
      });
    }
  }

  reviewFn(){
    const orders = this.selectedOrders
      .map((elm) => ({ id: elm.order_id ?? elm.id }))
      .filter((o) => o.id != null);

    if (orders.length === 0) {
      return;
    }

    this.order.reviewOrder({ orders }).subscribe(res => {
      if (res) {
        Swal.fire({
          icon: 'success',
          timer: 1500,
          showConfirmButton: false,
        }).then(() => {
          this.selectedOrders = [];
          this.search(arguments);
        });
      }
    });
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.selectedOrders = [];
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
    if (!this.menuVisibleForRow(elm)) {
      return false;
    }
    const done = elm.is_done === true || elm.is_done === 1 || elm.is_done === '1';
    if (done) {
      return false;
    }
    const os = this.normStatus(elm?.order?.order_status);
    if (os === 'تم التحصيل') {
      return false;
    }
    return true;
  }

  /** نفس أدوار قائمة «تحصيل الطلب» في الجدول */
  get canBulkCollectRole(): boolean {
    const u = this.user;
    return u === 'Admin' || u === 'Operation Management' || u === 'Finance and operations management'
      || u === 'Operation Specialist' || u === 'Logistics Specialist';
  }

  /**
   * تحصيل جماعي بنفس حقول الدفع والـ API الخلفي للتحصيل الفردي (قيود محاسبية موحدة).
   */
  openBulkCollect(): void {
    const eligible = this.selectedOrders.filter((r: any) => this.canCollectFromRow(r));
    const uniqueIds = [...new Set(eligible.map((r: any) => r.order_id))] as number[];
    if (uniqueIds.length === 0) {
      const msg = this.selectedOrders.length === 0
        ? 'لم يُحدَّد أي صف'
        : 'الصفوف المحددة غير صالحة للتحصيل (مُحصَّلة مسبقاً، أو حالة الطلب لا تطابق حالة السطر)';
      Swal.fire({ icon: 'warning', title: msg });
      return;
    }
    const hasReturnSwap = eligible.some(
      (r: any) => r?.order?.order_type === 'طلب مرتجع' || r?.order?.order_type === 'طلب استبدال'
    );
    if (hasReturnSwap) {
      Swal.fire({
        icon: 'info',
        title: 'طلبات مرتجع / استبدال',
        text: 'احذفها من التحديد وتحصّلها من شاشة تحصيل الطلب (تأكيد استلام المنتج).',
      });
      return;
    }

    const bankOptions = (this.banks || []).map((b: any) => `<option value="${b.id}">${b.name}</option>`).join('');
    const safeOptions = (this.safes || []).map((s: any) => `<option value="${s.id}">${s.name}</option>`).join('');
    const svcOptions = (this.serviceAccounts || []).map((a: any) => `<option value="${a.id}">${a.name}</option>`).join('');

    Swal.fire({
      title: `تحصيل ${uniqueIds.length} طلباً`,
      html: `
      <div class="text-start" dir="rtl">
        <label class="d-block mb-1 small">طريقة التحصيل</label>
        <select id="bulk-pay-type" class="swal2-input mb-2">
          <option value="bank">بنك</option>
          <option value="safe">خزينة</option>
          <option value="service_account">حساب خدمي</option>
        </select>
        <div id="bulk-bank-box">
          <label class="d-block mb-1 small">البنك</label>
          <select id="bulk-bank" class="swal2-input mb-2"><option value="">— اختر —</option>${bankOptions}</select>
        </div>
        <div id="bulk-safe-box" style="display:none">
          <label class="d-block mb-1 small">الخزينة</label>
          <select id="bulk-safe" class="swal2-input mb-2"><option value="">— اختر —</option>${safeOptions}</select>
        </div>
        <div id="bulk-svc-box" style="display:none">
          <label class="d-block mb-1 small">حساب خدمي</label>
          <select id="bulk-svc" class="swal2-input mb-2"><option value="">— اختر —</option>${svcOptions}</select>
        </div>
        <label class="d-block mb-1 small">ملاحظة (اختياري)</label>
        <input id="bulk-note" class="swal2-input" placeholder="ملاحظة" />
      </div>`,
      showCancelButton: true,
      confirmButtonText: 'تحصيل',
      cancelButtonText: 'إلغاء',
      focusConfirm: false,
      didOpen: () => {
        const pt = document.getElementById('bulk-pay-type') as HTMLSelectElement | null;
        const toggle = () => {
          const v = pt?.value ?? 'bank';
          const b = document.getElementById('bulk-bank-box') as HTMLElement | null;
          const s = document.getElementById('bulk-safe-box') as HTMLElement | null;
          const x = document.getElementById('bulk-svc-box') as HTMLElement | null;
          if (b) { b.style.display = v === 'bank' ? 'block' : 'none'; }
          if (s) { s.style.display = v === 'safe' ? 'block' : 'none'; }
          if (x) { x.style.display = v === 'service_account' ? 'block' : 'none'; }
        };
        pt?.addEventListener('change', toggle);
        toggle();
      },
      preConfirm: () => {
        const payment_type = (document.getElementById('bulk-pay-type') as HTMLSelectElement)?.value || 'bank';
        const note = (document.getElementById('bulk-note') as HTMLInputElement)?.value ?? '';
        if (payment_type === 'bank') {
          const bank_id = (document.getElementById('bulk-bank') as HTMLSelectElement)?.value;
          if (!bank_id) {
            Swal.showValidationMessage('اختر البنك');
            return false as any;
          }
          return { payment_type, bank_id, note };
        }
        if (payment_type === 'safe') {
          const safe_id = (document.getElementById('bulk-safe') as HTMLSelectElement)?.value;
          if (!safe_id) {
            Swal.showValidationMessage('اختر الخزينة');
            return false as any;
          }
          return { payment_type, safe_id, note };
        }
        const service_account_id = (document.getElementById('bulk-svc') as HTMLSelectElement)?.value;
        if (!service_account_id) {
          Swal.showValidationMessage('اختر الحساب الخدمي');
          return false as any;
        }
        return { payment_type, service_account_id, note };
      },
    }).then((result) => {
      if (!result.isConfirmed || !result.value) {
        return;
      }
      const v = result.value as { payment_type: string; bank_id?: string; safe_id?: string; service_account_id?: string; note: string };
      const fd = new FormData();
      uniqueIds.forEach((id) => fd.append('order_ids[]', String(id)));
      fd.append('shipping_company_id', String(this.id));
      fd.append('payment_type', v.payment_type);
      if (v.payment_type === 'bank' && v.bank_id) {
        fd.append('bank_id', v.bank_id);
      } else if (v.payment_type === 'safe' && v.safe_id) {
        fd.append('safe_id', v.safe_id);
      } else if (v.payment_type === 'service_account' && v.service_account_id) {
        fd.append('service_account_id', v.service_account_id);
      }
      if (v.note) {
        fd.append('note', v.note);
      }
      this.order.bulkCollectOrders(fd).subscribe({
        next: (res: any) => {
          if (res?.message === 'success') {
            Swal.fire({
              icon: 'success',
              title: `تم تحصيل ${res.processed ?? uniqueIds.length} طلباً`,
              timer: 2000,
              showConfirmButton: false,
            });
            this.selectedOrders = [];
            this.search({ target: {} } as any);
          }
        },
        error: (err) => {
          const msg = err?.error?.message ?? err?.message ?? 'فشل التحصيل';
          Swal.fire({ icon: 'error', title: String(msg) });
        },
      });
    });
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
