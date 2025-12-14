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

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  constructor(private shippingService:ShippingCompanyService,private order: OrderService , private route:ActivatedRoute , private authService:AuthService,
    private bankService:BanksService , public dialog: MatDialog , private userService:UserService
    ){
  }

  ngOnInit(){
    this.id = this.route.snapshot.params['id'];
    this.param['id'] = this.route.snapshot.params['id'];
    this.user = this.authService.getUser();
    this.bankService.bankSelect().subscribe(res=>this.banks=res);
    if (this.user == 'Admin') {
      this.reviewed = '0';
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
      if (!this.selectedOrders.includes(item)) {
        this.selectedOrders.push(item);
      }
    } else{
      this.selectedOrders = this.selectedOrders.filter(elm=> elm !== item);
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
    this.selectedOrders = this.selectedOrders.map(elm=> {
      return {'id': elm.order_id}
    });

    this.order.reviewOrder({orders:this.selectedOrders}).subscribe(res=>{
      if (res) {
      Swal.fire({
        icon : 'success',
        timer:1500,
        showConfirmButton:false,
      }).then(res=> this.search(arguments));
      }
    });

  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
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
      console.log(res.orderDetails.data);

      this.data = res.orderDetails.data;
      this.length=res.orderDetails.total;
      this.pageSize=res.orderDetails.per_page;
      this.totalOrders=res.orderDetails.total;
      this.totalPrice=res.totalNet;
      this.name=res.name.name;
      this.tableData = this.data;
    })
  }




}
