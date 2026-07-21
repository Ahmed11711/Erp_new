import { DatePipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import Swal from 'sweetalert2';
import { DialogOverviewExampleDialog } from '../list-orders/list-orders.component';
import { FilterOrderService } from '../services/filter-order.service';
import { OrderSourceService } from '../services/order-source.service';
import { OrderService } from '../services/order.service';
import { ShippingCompanyService } from '../services/shipping-company.service';
import { ShippingLinesService } from '../services/shipping-lines.service';
import { ShippingWayService } from '../services/shipping-way.service';
import { ActivatedRoute } from '@angular/router';
import { BanksService } from 'src/app/financial/services/banks.service';
import { SafeService } from 'src/app/accounting/services/safe.service';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service';
import { CollectionCompanyService } from '../services/collection-company.service';
import { collectRenewPrepaidParams } from '../utils/order-renew-prepaid.flow';
import { DialogCancelRefuseOrderComponent } from '../dialog-cancel-refuse-order/dialog-cancel-refuse-order.component';
import { AuthService } from 'src/app/auth/auth.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import {
  isCompanyCustomerType,
  isIndividualCustomerType,
  isRefuseEligibleOrderStatus,
} from '../utils/order-refuse.utils';

@Component({
  selector: 'app-customer-company-details',
  templateUrl: './customer-company-details.component.html',
  styleUrls: ['./customer-company-details.component.css']
})
export class CustomerCompanyDetailsComponent {

  orders :any = [];
  currentPageData :any = [];
  companies :any = [];
  location:any[]=[];
  cities:any[]=[];
  governName:boolean=false;
  orderSources:any[]=[];
  shippingWays:any[]=[];
  shippingLines:any[]=[];

  comapnyName:string='';

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  company_id!:number;
  banks: { id: number; name: string }[] = [];
  safes: { id: number; name: string }[] = [];
  serviceAccounts: { id: number; name: string }[] = [];
  collectionCompanies: { id: number; name: string }[] = [];
  user!: string;

    constructor(private orderSource:OrderSourceService ,private shippingWay: ShippingWayService , private datePipe:DatePipe,
      private http:HttpClient ,private order: OrderService,public dialog: MatDialog, private company:ShippingCompanyService,
      private filterService:FilterOrderService , private shippingLine:ShippingLinesService, private route:ActivatedRoute,
      private bankService: BanksService, private safeService: SafeService,
      private serviceAccountsService: ServiceAccountsService,
      private collectionCompanyService: CollectionCompanyService,
      private authService: AuthService, private rbac: RbacService,
      ) {

    }

    canCreateOffer(): boolean {
      return this.rbac.canAny(['nav.receipts.quotes', 'orders.view', 'orders.convert_from_offer', 'system.rbac']);
    }

    ngOnInit(): void {
      this.user = this.authService.getUser();
      this.company_id = this.route.snapshot.params['id'];
      this.bankService.bankSelect().subscribe((res: any) => (this.banks = res || []));
      this.safeService.getAll().subscribe((res: any) => {
        this.safes = res?.data ?? res ?? [];
      });
      this.serviceAccountsService.index().subscribe((res: any) => {
        this.serviceAccounts = res ?? [];
      });
      this.collectionCompanyService.select().subscribe((res: any) => {
        this.collectionCompanies = res ?? [];
      });
      console.log(this.company_id);

      this.filter(arguments);

      this.company.shippingCompanySelect().subscribe((res:any)=>{
        this.companies = res
    });

    this.http.get('assets/egypt/governorates.json').subscribe((data:any)=>this.location=data);
    this.http.get('assets/egypt/cities.json').subscribe((data:any)=>{
      this.cities = data.filter((elem:any)=>elem.governorate_id == 1);
    });


    this.orderSource.data().subscribe(reuslt=>this.orderSources = reuslt);
    this.shippingWay.data().subscribe(result=>this.shippingWays = result);
    this.shippingLine.dataLines().subscribe(result=>this.shippingLines = result);

    }

    govern(event){
      if (event.target.value == "القاهرة") {
        this.governName = true;
      } else{
        this.governName = false;
      }
      this.filter(event);
    }


    // Assuming you have a method in your component or a utility service
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
      this.filter(arguments)
    }


    checkboxVip(id:number){
      this.order.vipOrder(id).subscribe((res:any)=>{
        this.filter(arguments);
      })
    }

    checkboxShortage(id:number){
      this.order.shortageOrder(id).subscribe((res:any)=>{
        this.filter(arguments);
      })
    }

    openDialog(id:number, note:string,status:string): void {
      const dialogRef = this.dialog.open(DialogOverviewExampleDialog, {
        data: {name: note,id,status},
      });

      dialogRef.afterClosed().subscribe(result => {
        console.log('The dialog was closed');

      });
    }



    search(event){
      this.orders=this.orders.filter((elem:any)=>{
        return elem.customer_name.includes(event.target.value);
      })
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

    receive(id:number){
      this.order.receivedOrder(id,0,0,'').subscribe((res:any)=>{
        this.filter(arguments);
        console.log(res);
      });
  }

  maintain(id:number){
    console.log(id);
    Swal.fire({
      title: 'ادخل تكلفة الصيانة',
      input: 'text',
      inputLabel: 'التكلفة',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة'
        }
        if (value !== '') {
          this.order.maintainOrder(id,{maintenance_cost:value}).subscribe(res=>{
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

  canRefuseOrderMenu(): boolean {
    return this.canChangeOrderStatusMenu();
  }

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

  canRefuseOrder(item: any): boolean {
    return isRefuseEligibleOrderStatus(item?.order_status);
  }

  isCompanyCustomer(item: any): boolean {
    return isCompanyCustomerType(item?.customer_type);
  }

  isIndividualCustomer(item: any): boolean {
    return isIndividualCustomerType(item?.customer_type);
  }

  refuseOrder(type: string, id: number): void {
    const dialogRef = this.dialog.open(DialogCancelRefuseOrderComponent, {
      width: '25%',
      data: { data: { id, action: 'refused', type }, refreshData: () => this.filter(arguments) },
    });
    dialogRef.afterClosed().subscribe(() => {});
  }


  changeOrderStatus(type:string,id:number,title:string,action:string, orderItem?: any){
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
              this.order.chngeStatus(id, action, value , amount,0,0).subscribe(res => {
                console.log(res);
                this.filter(arguments);
              });

              return undefined;
            }
          });

          return undefined;
        }
      });
    } else{
      Swal.fire({
        title: title,
        input: 'text',
        inputPlaceholder: 'السبب',
        showCancelButton: true,
        inputValidator: (value) => {
          if (!value) {
            return 'يجب ادخال ملاحظة'
          }
          if (value !== '') {
            const runChange = async () => {
              const param: Record<string, string | number> = {};
              if (action === 'renew' && orderItem) {
                const renewParams = await collectRenewPrepaidParams(orderItem, {
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
              this.order.chngeStatus(id, action, value, 0, 0, 0, param).subscribe((res) => {
                console.log(res);
                this.filter(arguments);
              });
            };
            runChange();
          }
          return undefined
        }
      })
    }
  }



    // -------------------------------------------------------------------------------------------filter


    filter(event: any):void{
      if (this.company_id) {
        this.filterService.company_id = this.company_id
      }

      if (event.target?.id ==="customer_type") {
        this.filterService.customer_type = event.target.value;
      }

      if (event.target?.id ==="order_type") {
        this.filterService.order_type = event.target.value;
      }

      if (event.target?.id ==="order_status") {
        this.filterService.order_status = event.target.value;
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
        // this.filterService.shortage = event.target.checked;
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
        this.filterService.customer_phone = event.target.value;
      }

      if (event.target?.id ==="order_number") {
        this.filterService.order_number = event.target.value;
      }

      if (event.target?.id ==="shippment_number") {
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


      this.filterService.filter(this.pageSize,this.page+1).subscribe(result=>{
        this.orders = result.data;
        this.length=result.total;
        this.pageSize=result.per_page;

        this.comapnyName = result.data[0]?.customer_name;

        console.log(result);


        this.orders.map(elm=>{

          const needByDateObj = new Date();
          const orderDateObj = new Date(elm.order_date);

          const differenceInMilliseconds = needByDateObj.getTime() - orderDateObj.getTime();

          const differenceInDays = Math.floor(differenceInMilliseconds / (1000 * 60 * 60 * 24));
          elm.days = differenceInDays

        })
      })

    }

  clearFilter(){
    window.location.reload();

    //reset inputs

    this.filterService.customer_type = '';
    this.filterService.order_type = '';
    this.filterService.order_status = '';
    this.filterService.shipping_company_id = '';
    this.filterService.need_by_date = '';
    this.filterService.status_date = '';
    this.filterService.vip = '';
    this.filterService.shortage = '';
    this.filterService.governorate = '';
    this.filterService.city = '';
    this.filterService.customer_name = '';
    this.filterService.customer_phone = '';
    this.filterService.order_number = '';
    this.filterService.shippment_number = '';
    this.filterService.order_source_id = '';
    this.filterService.shipping_method_id = '';
    this.filterService.shipping_line_id = '';
    this.filterService.filter(this.pageSize,this.page+1).subscribe(result=>{
      this.orders = result.data;
      this.length=result.total;
      this.pageSize=result.per_page;

      this.orders.map(elm=>{

        const needByDateObj = new Date();
        const orderDateObj = new Date(elm.order_date);

        const differenceInMilliseconds = needByDateObj.getTime() - orderDateObj.getTime();

        const differenceInDays = Math.floor(differenceInMilliseconds / (1000 * 60 * 60 * 24));
        elm.days = differenceInDays

      })
    })
  }

  googleSheetData: any[] = [];

  sendToGoogleSheet(e: any, item: any) {

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
          desc += `${i+1}-[ ${elm.category.category_name} (${elm.quantity}) ]  `
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
          "Street" : item.address,
          "COD_Value" : String(item.total_invoice) ,
          "Description" : desc,
        }

        this.googleSheet.push(orderData);

      })

    } else if(sheet === 'Bosta'){
      this.googleSheet = [];
      this.googleSheetData.forEach(item=>{
        let desc = '';

        item.order_products.forEach((elm , i)=>{
          desc += `${i+1}-[ ${elm.category.category_name} (${elm.quantity}) ]  `
        })

        let phone2 = ''
        if (item.customer_phone_2 != 'null') {
          phone2 = item.customer_phone_2
        }

        const orderData = {
          // "Package_Serial" : item.id,
          "Full Name" : item.customer_name,
          "Phone" : item.customer_phone_1,
          "Second Phone" : phone2,
          "Street" : item.address,
          "City" : item.city,
          "Cash Amount" : String(item.total_invoice) ,
          // "Description" : desc,
        }

        this.googleSheet.push(orderData);

      })

    }
    console.log(this.googleSheet);

  }

}
