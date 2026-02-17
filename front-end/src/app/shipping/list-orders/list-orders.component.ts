import { Component, ElementRef, HostListener, Inject, Renderer2 } from '@angular/core';
import { OrderService } from '../services/order.service';
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
import { DialogWhatsAppMessageComponent } from 'src/app/whatsapp/components/dialog-whatsapp-message/dialog-whatsapp-message.component';
import { BanksService } from 'src/app/financial/services/banks.service';
import { AuthService } from 'src/app/auth/auth.service';

@Component({
  selector: 'app-list-orders',
  templateUrl: './list-orders.component.html',
  styleUrls: ['./list-orders.component.css']
})
export class ListOrdersComponent {
  user!:string;
  orders :any = [];
  banks :any = [];
  currentPageData :any = [];
  companies :any = [];
  location:any[]=[];
  cities:any[]=[];
  governName:boolean=false;
  orderSources:any[]=[];
  shippingWays:any[]=[];
  shippingLines:any[]=[];
  products:any[]=[];

  length = 50;
  pageSize = 100;
  page = 0;
  pageSizeOptions = [100,15,50];

  constructor(private orderSource:OrderSourceService ,private shippingWay: ShippingWayService , private datePipe:DatePipe,
    private http:HttpClient ,private order: OrderService,public dialog: MatDialog, private company:ShippingCompanyService,
    private filterService:FilterOrderService , private shippingLine:ShippingLinesService,
    private userService:UserService ,private renderer: Renderer2 ,private el: ElementRef, private bankService:BanksService,
    private authService:AuthService, private router: Router
    ) {
      document.addEventListener('scroll', (event) => {
        this.onListenerTriggered(event);
      }, true);
  }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    this.order.getProducts().subscribe((result:any)=>this.products = result);

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
    this.orderSource.data().subscribe(reuslt=>this.orderSources = reuslt);
    this.shippingWay.data().subscribe(result=>this.shippingWays = result);
    this.shippingLine.dataLines().subscribe(result=>this.shippingLines = result);
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
  productChange(event) {
    this.filterService.category_id = event.id;
    this.filter(arguments);
  }

  resetInp(){
    this.filterService.category_id = null;
    this.filter(arguments);

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
  OnDateChange(event){
    const inputDate = new Date(event);
    this.need_by_date = this.datePipe.transform(inputDate, 'yyyy-MM-dd');
    this.need_by_datekey = true;
    this.filter('');
  }

  status_datekey = false;
  status_date:any;
  OnStatusDateChange(event){
    const inputDate = new Date(event);
    this.status_date = this.datePipe.transform(inputDate, 'yyyy-MM-dd');
    this.status_datekey = true;
    console.log(this.status_date);

    this.filter('');
  }

  order_datekey = false;
  order_date:any;
  OnOrderDateChange(event){
    const inputDate = new Date(event);
    this.order_date = this.datePipe.transform(inputDate, 'yyyy-MM-dd');
    this.order_datekey = true;
    this.filter('');
  }

  deliver_datekey = false;
  delivery_date:any;
  OnDeliverDateChange(event){
    const inputDate = new Date(event);
    this.delivery_date = this.datePipe.transform(inputDate, 'yyyy-MM-dd');
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
          case 'تم الصيانة':
          return 'fixed';
          case 'أرشيف' :
          return 'archived';
      default:
        return '';
    }
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

  openChatWithCustomer(item: any): void {
    if (item && item.customer_phone_1) {
      // Format phone number
      let phone = item.customer_phone_1;
      if (!phone.startsWith('+')) {
        if (phone.startsWith('0')) {
          phone = '+2' + phone.substring(1);
        } else {
          phone = '+2' + phone;
        }
      }
      
      // Navigate to chat page - the component will find or create customer by phone
      this.router.navigate(['/dashboard/whatsapp/chat'], {
        queryParams: { phone: phone }
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
                this.order.chngeStatus(id, action, value , amount,0,0).subscribe(res => {
                  if (res) {
                    this.filter(arguments);
                    Swal.fire({
                      icon : 'success',
                      timer:3000,
                      showConfirmButton:false,
                      titleText: 'تم ارسال اشعار للادمن',
                      position: 'bottom-end',
                      toast: true,
                      timerProgressBar: true,
                    });
                  };
                });
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
                        this.order.chngeStatus(id, action, value , amount,bank,0).subscribe(res => {
                          console.log(res);
                          if (res) {
                            this.filter(arguments);
                            Swal.fire({
                              icon : 'success',
                              timer:3000,
                              showConfirmButton:false,
                              titleText: 'تم ارسال اشعار للادمن',
                              position: 'bottom-end',
                              toast: true,
                              timerProgressBar: true,
                            });
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
                        this.filter(arguments);
                        Swal.fire({
                          icon : 'success',
                          timer:3000,
                          showConfirmButton:false,
                          titleText: 'تم ارسال اشعار للادمن',
                          position: 'bottom-end',
                          toast: true,
                          timerProgressBar: true,
                        });
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
        inputValidator: async (value) => {
          if (!value) {
            return 'يجب ادخال ملاحظة'
          }
          if (value !== '') {
            let param = {}
            if (action=='cancel' && order.prepaid_amount > 0) {
              let returnPaidMoney = await this.returnPrepaidAmount(order)
              if (Object.keys(returnPaidMoney).length === 0) {
                return undefined;
              }
              param['moneyReturnedStatus'] = returnPaidMoney['returnedStatus'];
              if (returnPaidMoney['returnedStatus'] === 'approved') {
                param['moneyReturnedBank'] = returnPaidMoney['returnedBank'];
              }
            }

            if (action=='renew') {
              const result = await Swal.fire({
                title: 'هل يوجد مبلغ تحت الحساب؟',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم',
                cancelButtonText: 'لا',
              });

              if (result.dismiss === Swal.DismissReason.backdrop) {
                return;
              }

              if (result.isConfirmed) {
                let prepaidAmountData = await this.renewPrepaidAmount();
                if (Object.keys(prepaidAmountData).length === 0) {
                  return undefined;
                }
                param['renewAmount'] = prepaidAmountData['renewAmount'];
                param['renewBankId'] = prepaidAmountData['renewBankId'];
              }
            }

            this.order.chngeStatus(id,action,value,0,0,0,param).subscribe(res=>{
              if (res) {
                this.filter(arguments);
                Swal.fire({
                  icon : 'success',
                  timer:3000,
                  showConfirmButton:false,
                  titleText: 'تم ارسال اشعار للادمن',
                  position: 'bottom-end',
                  toast: true,
                  timerProgressBar: true,
                });
              };
            }
            )
          }
          return undefined
        }
      })
    }
  }

  async returnPrepaidAmount(order){
    const banks = this.banks;
    const bankSelectOptions = banks.reduce((options, bank) => {
      options[bank.id] = bank.name;
      return options;
    }, {});

    let data={}
    await Swal.fire({
      title: ' إرجاع مبلغ تحت الحساب '+ order.prepaid_amount,
      input: 'select',
      inputOptions: bankSelectOptions,
      inputPlaceholder: 'اختر الخزينة',
      inputValue:order.bank_id,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'قيد الانتظار',
      customClass: {
        input: 'text-center',
      },
    }).then((result:any) => {
      const selectedBankId = result.value;
      if (result.isConfirmed) {
        data['returnedStatus'] = 'approved';
        data['returnedBank'] = selectedBankId;
      } else if (result.dismiss == "cancel"){
        data['returnedStatus'] = 'pending';
      }
    });
    return data
  }

  async renewPrepaidAmount() {
    let options;
    this.banks.forEach(elm =>{
      let selected = '';
      options += `<option ${selected} value="${elm.id}">${elm.name}</option>`;
    })

    let data = {};
    const { value: formValues } = await Swal.fire({
      title: ' ادخال مبلغ تحت الحساب ' ,
      html: `
      <div class="row w-100 m-auto">
        <div class="col-md-12">
          <div class="form-group">
            <input id="swal-input-renewAmount" class="form-control text-center" placeholder="المبلغ" type="number" min="0">
          </div>
        </div>
        <div class="col-md-12">
          <div class="form-group">
            <select id="swal-input-bank" class="form-control  text-center bg-main">
              <option value="اختر الخزينة" disabled selected>اختر الخزينة</option>
              ${options}
            </select>
          </div>
        </div>
      </div>
    `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'الغاء',
      preConfirm: () => {
        let renewAmount:any = document.getElementById('swal-input-renewAmount');
        let selectedBankId:any = document.getElementById('swal-input-bank');
        return {
          renewAmount: renewAmount.value,
          selectedBankId: selectedBankId.value
        }
      }
    });

    if (formValues) {
      data['renewAmount'] = formValues.renewAmount;
      data['renewBankId'] = formValues.selectedBankId;
    }
    return data;
  }




  // -------------------------------------------------------------------------------------------filter


  filter(event: any):void{
    if (event.target?.id ==="customer_type") {
      this.filterService.customer_type = event.target.value;
    }

    if (event.target?.id ==="order_type") {
      this.filterService.order_type = event.target.value;
    }

    if (event.target?.id ==="private_order") {
      if (event.target.value == 1) {
        this.filterService.private_order = '1';
      } else {
        this.filterService.private_order = '';
      }
    }

    if (event.target?.id ==="order_status") {
      this.filterService.order_status = event.target.value;
    }

    if (event.target?.id ==="collectType") {
      this.filterService.collectType = event.target.value;
    }

    if (event.target?.id ==="shipping_company_id") {
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

    if (event.target?.id ==="vip") {
      if (event.target.checked) {
        this.filterService.vip = '1';
      } else {
        this.filterService.vip ='0';
      }
      // this.filterService.vip = event.target.checked;
    }

    if (event.target?.id ==="shortage") {
      if (event.target.checked) {
        this.filterService.shortage = '1';
      } else {
        this.filterService.shortage ='0';
      }
    }

    if (event.target?.id ==="paid") {
      if (event.target.checked) {
        this.filterService.paid = '1';
      } else {
        this.filterService.paid ='0';
      }
    }

    if (event.target?.id ==="prepaidAmount") {
      if (event.target.checked) {
        this.filterService.prepaidAmount = '1';
      } else {
        this.filterService.prepaidAmount ='0';
      }
    }

    if (event.target?.id ==="governorate") {
      this.filterService.governorate = event.target.value;
    }

    if (event.target?.id ==="city") {
      this.filterService.city = event.target.value;
    }

    if (event.target?.id ==="customer_name") {
      this.filterService.customer_name = event.target.value;
    }

    if (event.target?.id ==="customer_phone") {
      let number = event.target.value;
      if (number.startsWith('+2') || number.startsWith('2')) {
        number = number.substring(2);
      }
      this.filterService.customer_phone = number;
    }

    if (event.target?.id ==="order_number") {
      this.filterService.order_number = event.target.value;
    }

    if (event.target?.id ==="shippment_number") {
      console.log(event.target.value);
      this.filterService.shippment_number = event.target.value;
    }

    if (event.target?.id ==="order_source_id") {
      this.filterService.order_source_id = event.target.value;
    }

    if (event.target?.id ==="shipping_method_id") {
      this.filterService.shipping_method_id = event.target.value;
    }

    if (event.target?.id ==="shipping_line_id") {
      this.filterService.shipping_line_id = event.target.value;
    }

    if (event.target?.id ==="reviewfilter") {
      this.filterService.reviewed = event.target.value;
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

  selectOrder(e: any, item: any) {
    this.sendOneOrder = false;

    if (e.target.checked) {

      if (!this.googleSheetData.includes(item)) {
        this.googleSheetData.push(item);
      }
    } else{

      this.googleSheetData = this.googleSheetData.filter(elm=> elm !== item);
    }

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
