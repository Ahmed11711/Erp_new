import { Component, EventEmitter, OnInit, Output } from '@angular/core';
import { ActivatedRoute, NavigationEnd, Router } from '@angular/router';
import { OrderService } from '../services/order.service';
import { filter } from 'rxjs';
import Swal from 'sweetalert2';
import { AuthService } from 'src/app/auth/auth.service';
import { environment } from 'src/env/env';

@Component({
  selector: 'app-order-details',
  templateUrl: './order-details.component.html',
  styleUrls: ['./order-details.component.css']
})
export class OrderDetailsComponent implements OnInit{
  // @Output() dataEvent = new EventEmitter<{quantity:number,id:number , shippstatus:any}>();
  @Output() dataEvent = new EventEmitter<{shipProducts:any , shippstatus:any , orderType:string }>();
  user!:string;
  id!:number;
  order!:any
  notes:any[]=[];
  order_products:any[]=[];
  shipProducts:any[]=[];
  tempReviewNotification:any[]=[];
  maintenReasons:any[]=[];
  tracking:any  = [];
  notifications:any  = [];
  tempReview:any  = [];

  oldProduct:boolean = false;
  isDetails!:boolean;
  isShipCompany!:boolean;
  orderType!:string;
  reviewd:boolean =false;
  reviewdNote:string = '';

  userReviewd:boolean =false;
  special_order:boolean =false;
  userReviewdNote:string = '';
  imgUrl!: string;

  constructor(private route:ActivatedRoute , private orderService:OrderService, private router:Router, private authService:AuthService){
    this.imgUrl = environment.imgUrl;
  }

  ngOnInit(): void {
    this.route.params.subscribe((result:any)=>{
      this.id = result?.id;
    });

    this.user = this.authService.getUser();


    this.getOrder();

    const currentUrl = this.router.url;
    const lastSlashIndex = currentUrl.lastIndexOf('/');
    let checkUrl;

    const modifiedUrl = currentUrl.substring(0, lastSlashIndex);

    if (Number(currentUrl.slice(lastSlashIndex+1))) {
      checkUrl = modifiedUrl;


    }
    else{
      checkUrl = currentUrl;
    }
    console.log(checkUrl);


    if (checkUrl == '/dashboard/shipping/orderdetails') {
      this.isDetails = true;

    }

    if (checkUrl == '/dashboard/shipping/shipOrder') {
      this.isShipCompany =true;
    }



  }

  showImg(e){
    Swal.fire({
      html: `<img src="${e.target.src}" alt="Preview" style="max-width: 100%; height: auto;" />`,
      showConfirmButton:false
    });
  }

  showTime(e:any){
    let id=`date${e.target.id}`
    let elm = document.getElementById(id);
    if (e.type == 'mouseenter') {
      elm?.classList.replace('d-none','d-block');
    } else {
      elm?.classList.replace('d-block','d-none');
    }
  }

  getOrder(){
    this.orderService.getOrderById(this.id).subscribe( (result:any)=>{
      this.order = result;
      this.orderType = result.order_type;

      this.tempReviewNotification = result.tempReviewNotification;

      this.order_products = result.order_products;
      this.tracking = result.traking;

      if (result?.mainten_reason) {
        this.maintenReasons = result?.mainten_reason;
      }

      this.notifications = result?.notifications;
      this.notes = result.note;
      this.tempReview = result.temp_review;

      if (this.order?.order_details?.reviewed == 1) {
        this.reviewd =true;
      } else {
        this.reviewd =false;
      }
      if (this.order?.order_details?.reviewed_note) {
        this.reviewdNote = this.order?.order_details?.reviewed_note;
      }

      if (this.order?.order_details?.user_reviewed == 1) {
        this.userReviewd =true;
      } else {
        this.userReviewd =false;
      }
      if (this.order?.order_details?.user_reviewed_note) {
        this.userReviewdNote = this.order?.order_details?.user_reviewed_note;
      }

      this.order_products.forEach(elm=>{
        elm.quantity = Number(elm.quantity);
        elm.requiredQuantity = elm.quantity-elm.shipped_quantity;
        if (elm.quantity-elm.shipped_quantity == 0) {
          elm.hideinput = true;
        }
      });
      this.special_order = this.order_products.find(elm => elm.special_details);
      this.shipProducts = this.order_products.filter(elm=>elm.quantity > elm.shipped_quantity);
      this.dataEvent.emit({shipProducts:this.shipProducts,shippstatus:true , orderType:this.orderType});
    })
  }

  addShippmentNumber(id){
    Swal.fire({
      input: 'text',
      inputPlaceholder: 'رقم البوليصة',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال رقم البوليصة'
        }
        if (value !== '') {
          this.orderService.addShippmentNumber(id,value).subscribe((res:any)=>{
            console.log(res);
            if (res) {
              Swal.fire({
                icon : 'success',
                timer:1500,
                showConfirmButton:false,
              })
              this.getOrder();
            }
          })
        }
        return undefined
      }
    })
  }

  toggleUpdatedProduct(){
    this.oldProduct = !this.oldProduct;
  }

  addTempReview(id){
    Swal.fire({
      title:'المراجعة',
      input: 'text',
      inputPlaceholder: 'ادخل المراجعة',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال مراجعة'
        }
        if (value !== '') {
          this.orderService.addTempReview(id,value).subscribe((res:any)=>{
            console.log(res);
            if (res) {
              Swal.fire({
                icon : 'success',
                timer:1500,
                showConfirmButton:false,
              })
              this.getOrder();
            }
          })
        }
        return undefined
      }
    })
  }

  adminReadTempReview(id){
    this.orderService.readTempReview(id).subscribe((res:any)=>{
      if (res) {
        Swal.fire({
          icon : 'success',
          timer:1500,
          showConfirmButton:false,
        })
        this.getOrder();
      }
    })
  }

  addNote(id){
    Swal.fire({
      titleText:'ادخل الملاحظة',
      input: 'text',
      inputPlaceholder: 'ملاحظة',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال ملاحظة'
        }
        if (value !== '') {
          this.orderService.addNote(id,value).subscribe((res:any)=>{
            console.log(res);
            if (res) {
              Swal.fire({
                icon : 'success',
                timer:1500,
                showConfirmButton:false,
              })
              this.getOrder();
            }
          })
        }
        return undefined
      }
    })
  }

  reviewFn(){
    this.orderService.reviewOrder({id:this.id,reviewd:this.reviewd,reviewd_note:this.reviewdNote}).subscribe(res=>{
      console.log(res);

      if (res) {
        Swal.fire({
          icon : 'success',
          timer:1500,
          showConfirmButton:false,
        }).then(res=>this.getOrder());
      }
    });

  }

  UserReviewFn(){
    this.orderService.userReviewOrder({id:this.id,user_reviewed:this.userReviewd,user_reviewed_note:this.userReviewdNote}).subscribe(res=>{
      console.log(res);

      if (res) {
        Swal.fire({
          icon : 'success',
          timer:1500,
          showConfirmButton:false,
        }).then(res=>this.getOrder());
      }
    });

  }

  shippedQuantity(event:any,id:number){
    let shippstatus = true;
    this.order_products.forEach(elm=>{
      if(elm.id==id){
        elm.requiredQuantity = Number(event.target.value) ;
      }
    })
    const shipProducts = this.order_products.filter(elm=>elm.quantity > elm.shipped_quantity);
    let status = this.order_products.every(elm => elm.requiredQuantity <= elm.quantity - elm.shipped_quantity);


    this.dataEvent.emit({shipProducts,shippstatus:status,orderType:this.orderType})
  }

  data:any = {};
  printInvoice(size:any) {
    this.data = this.order;
    this.data.size = size;
  }

  sticker:any = {};
  printSticker() {
    Swal.fire({
      input: 'number',
      inputPlaceholder: 'عدد الملصقات',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال ملاحظة'
        }
        if (value !== '') {
          this.sticker = this.order;
          this.sticker.size = value;
        }
        return undefined
      }
    })

  }


}
