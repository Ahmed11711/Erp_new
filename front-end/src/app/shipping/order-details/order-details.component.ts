import { Component, EventEmitter, OnInit, Output } from '@angular/core';
import { ActivatedRoute, NavigationEnd, Router } from '@angular/router';
import { OrderService } from '../services/order.service';
import { filter } from 'rxjs';
import Swal from 'sweetalert2';
import { AuthService } from 'src/app/auth/auth.service';
import { environment } from 'src/env/env';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ShippingWayService } from '../services/shipping-way.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { RBAC_ROUTE } from 'src/app/guards/rbac-route-data';

@Component({
  selector: 'app-order-details',
  templateUrl: './order-details.component.html',
  styleUrls: ['./order-details.component.css']
})
export class OrderDetailsComponent implements OnInit{
  // @Output() dataEvent = new EventEmitter<{quantity:number,id:number , shippstatus:any}>();
  @Output() dataEvent = new EventEmitter<{shipProducts:any , shippstatus:any , orderType:string }>();
  user!:string;
  get isFinancialAccountsReadonly(): boolean {
    return this.user === 'Financial Accounts';
  }
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

  /** مراجعة Shopify: نفس صفحة التفاصيل مع تعديل كامل */
  shopifyReviewMode = false;
  reviewSubmitting = false;
  reviewNote = '';
  reviewDraft: any = {};
  reviewProductsDraft: any[] = [];
  shippingWays: any[] = [];
  catalogProducts: any[] = [];
  reviewProductPick: any = null;
  reviewAddQty: number | null = null;
  reviewAddPrice: number | null = null;
  catword = 'category_name';

  constructor(
    private route: ActivatedRoute,
    private orderService: OrderService,
    private router: Router,
    private authService: AuthService,
    private snackBar: MatSnackBar,
    private shippingWay: ShippingWayService,
    public rbac: RbacService,
  ) {
    this.imgUrl = environment.imgUrl;
  }

  get canConfirmShopifyReview(): boolean {
    return this.rbac.canAny([...RBAC_ROUTE.shopifyOrderReview]);
  }

  get isShopifyOrder(): boolean {
    return !!this.order?.shopify_order_id;
  }

  get isShopifyReviewed(): boolean {
    return !!this.order?.shopify_reviewed_at;
  }

  get shopifyFinancialStatusLabel(): string {
    const s = this.order?.shopify_financial_status;
    if (!s) return '—';
    const map: Record<string, string> = {
      paid: 'مدفوع',
      pending: 'معلق (COD)',
      partially_paid: 'مدفوع جزئياً',
      authorized: 'مصرّح',
      refunded: 'مسترد',
      voided: 'ملغي',
    };
    return map[s] || s;
  }

  ngOnInit(): void {
    this.route.params.subscribe((result:any)=>{
      this.id = result?.id;
    });

    this.route.queryParams.subscribe((qp: any) => {
      this.shopifyReviewMode = qp?.shopifyReview === '1' || qp?.shopifyReview === 1 || qp?.shopifyReview === true;
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

    if (this.shopifyReviewMode) {
      this.shippingWay.data().subscribe((res: any) => this.shippingWays = res || []);
      this.orderService.getProducts().subscribe((res: any) => this.catalogProducts = res || []);
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

      if (this.shopifyReviewMode && this.isShopifyOrder) {
        this.initShopifyReviewDraft();
      }
    })
  }

  initShopifyReviewDraft(): void {
    this.reviewNote = this.order?.shopify_review_note || '';
    this.reviewDraft = {
      customer_name: this.order?.customer_name || '',
      customer_phone_1: this.order?.customer_phone_1 || '',
      customer_phone_2: (this.order?.customer_phone_2 && this.order.customer_phone_2 !== 'null')
        ? this.order.customer_phone_2 : '',
      tel: (this.order?.tel && this.order.tel !== 'null') ? this.order.tel : '',
      governorate: this.order?.governorate || '',
      city: this.order?.city || '',
      address: this.order?.address || '',
      shipping_method_id: this.order?.shipping_method_id ?? null,
      shipping_cost: Number(this.order?.shipping_cost) || 0,
      prepaid_amount: Number(this.order?.prepaid_amount) || 0,
      discount: Number(this.order?.discount) || 0,
      total_invoice: Number(this.order?.total_invoice) || 0,
      net_total: Number(this.order?.net_total) || 0,
      vat: Number(this.order?.vat) || 0,
      collect_note: this.order?.collect_note || '',
    };
    this.reviewProductsDraft = (this.order_products || []).map((elm: any) => ({
      category_id: elm.category?.id ?? elm.category_id,
      category_name: elm.category?.category_name || elm.special_details || '—',
      quantity: Number(elm.quantity) || 0,
      price: Number(elm.price) || 0,
      total: Number(elm.total_price) || 0,
      special_details: elm.special_details || '',
      imgsrc: elm.category?.category_image,
      is_unmatched: !!elm.is_shopify_unmatched,
      shopify_name: elm.special_details || (elm.category?.category_name || ''),
    }));
  }

  /** هل ما زال هناك بند غير مربوط بصنف ERP صحيح في المسودة الحالية؟ */
  get hasUnmatchedReviewProducts(): boolean {
    return (this.reviewProductsDraft || []).some((r) => r.is_unmatched);
  }

  /** ربط بند Shopify غير مطابق بصنف ERP صحيح من القائمة دون فقد اسم Shopify الأصلي. */
  onReviewRowCategorySelected(index: number, item: any): void {
    if (!item || !this.reviewProductsDraft[index]) return;
    const row = this.reviewProductsDraft[index];
    row.category_id = item.id;
    row.category_name = item.category_name;
    row.imgsrc = item.category_image;
    row.is_unmatched = false;
    if (!row.price || row.price <= 0) {
      row.price = Number(item.category_price ?? item.sell_total_price ?? 0) || 0;
    }
    // احتفظ باسم Shopify الأصلي في special_details للرجوع إليه.
    if (!row.special_details) {
      row.special_details = row.shopify_name || '';
    }
    this.calcReviewTotals();
  }

  calcReviewTotals(): void {
    let productsPrice = 0;
    this.reviewProductsDraft.forEach((row) => {
      row.total = Math.round((Number(row.quantity) || 0) * (Number(row.price) || 0) * 100) / 100;
      productsPrice += row.total;
    });
    const shipping = Number(this.reviewDraft.shipping_cost) || 0;
    const discount = Number(this.reviewDraft.discount) || 0;
    const prepaid = Number(this.reviewDraft.prepaid_amount) || 0;
    let total = productsPrice + shipping;
    if (this.order?.customer_type === 'شركة') {
      const vat = Number(this.reviewDraft.vat) || 0;
      total += vat;
    }
    this.reviewDraft.total_invoice = Math.round(total * 100) / 100;
    this.reviewDraft.net_total = Math.round((total - prepaid - discount) * 100) / 100;
  }

  onReviewProductQtyChange(index: number, value: string | number): void {
    const qty = Math.max(1, Number(value) || 1);
    this.reviewProductsDraft[index].quantity = qty;
    this.calcReviewTotals();
  }

  onReviewProductPriceChange(index: number, value: string | number): void {
    this.reviewProductsDraft[index].price = Number(value) || 0;
    this.calcReviewTotals();
  }

  removeReviewProduct(index: number): void {
    this.reviewProductsDraft.splice(index, 1);
    this.calcReviewTotals();
  }

  onReviewProductSelected(item: any): void {
    if (!item) return;
    this.reviewProductPick = item;
    this.reviewAddPrice = Number(item.category_price ?? item.sell_total_price ?? 0) || 0;
    this.reviewAddQty = 1;
  }

  addReviewProduct(): void {
    if (!this.reviewProductPick || this.reviewAddQty == null || this.reviewAddQty < 1) return;
    const exists = this.reviewProductsDraft.find((r) => r.category_id === this.reviewProductPick.id);
    if (exists) {
      this.snackBar.open('الصنف موجود في الطلب — عدّل الكمية من الجدول', 'إغلاق', { duration: 4000 });
      return;
    }
    const price = Number(this.reviewAddPrice) || 0;
    const qty = Number(this.reviewAddQty) || 1;
    this.reviewProductsDraft.push({
      category_id: this.reviewProductPick.id,
      category_name: this.reviewProductPick.category_name,
      quantity: qty,
      price,
      total: price * qty,
      special_details: '',
      imgsrc: this.reviewProductPick.category_image,
    });
    this.reviewProductPick = null;
    this.reviewAddQty = null;
    this.reviewAddPrice = null;
    this.calcReviewTotals();
  }

  exitShopifyReview(): void {
    this.router.navigate(['/dashboard/shipping/listorders']);
  }

  confirmShopifyReview(): void {
    if (!this.canConfirmShopifyReview) {
      this.snackBar.open('ليس لديك صلاحية مراجعة طلبات Shopify', 'إغلاق', { duration: 5000 });
      return;
    }
    if (this.reviewSubmitting || !this.reviewProductsDraft.length) {
      if (!this.reviewProductsDraft.length) {
        this.snackBar.open('يجب وجود صنف واحد على الأقل', 'إغلاق', { duration: 4000 });
      }
      return;
    }
    if (this.hasUnmatchedReviewProducts) {
      Swal.fire({
        icon: 'warning',
        title: 'منتجات غير مربوطة',
        text: 'يوجد منتج Shopify غير مربوط بصنف. اربطه بصنف ERP صحيح (أو احذفه) قبل تأكيد المراجعة.',
        confirmButtonText: 'حسناً',
      });
      return;
    }
    this.calcReviewTotals();
    this.reviewSubmitting = true;
    const payload = {
      note: this.reviewNote?.trim() || null,
      order: { ...this.reviewDraft },
      order_products: this.reviewProductsDraft.map((r) => ({
        category_id: r.category_id,
        quantity: r.quantity,
        price: r.price,
        special_details: r.special_details || null,
      })),
    };
    this.orderService.shopifyReviewOrder(this.id, payload).subscribe({
      next: (res: any) => {
        this.reviewSubmitting = false;
        const msg = res?.message || 'تمت المراجعة';
        this.snackBar.open(msg, 'إغلاق', { duration: 5000 });
        this.getOrder();
        if (res?.changes?.length) {
          Swal.fire({
            icon: 'info',
            title: 'التعديلات المسجّلة',
            html: '<pre dir="rtl" style="text-align:right;white-space:pre-wrap">' +
              res.changes.join('\n') + '</pre>',
            confirmButtonText: 'حسناً',
          });
        }
      },
      error: (err) => {
        this.reviewSubmitting = false;
        this.snackBar.open(err?.error?.message || 'فشل حفظ المراجعة', 'إغلاق', { duration: 6000 });
      },
    });
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

  get visibleNotes(): any[] {
    return (this.notes || [])
      .filter((item) => item?.note && item.note !== 'null' && String(item.note).trim() !== '')
      .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
  }

  get canManageOrderNotes(): boolean {
    return this.user !== 'Review Management' && this.user !== 'Financial Accounts';
  }

  isNoteEdited(note: any): boolean {
    if (!note?.updated_at || !note?.created_at) {
      return false;
    }
    return new Date(note.updated_at).getTime() - new Date(note.created_at).getTime() > 1000;
  }

  noteEditorName(note: any): string {
    return note?.edited_by?.name || note?.user?.name || '—';
  }

  private openNoteDialog(title: string, initialValue = ''): Promise<string | null> {
    return Swal.fire({
      titleText: title,
      input: 'textarea',
      inputValue: initialValue,
      inputPlaceholder: 'اكتب الملاحظة هنا...',
      showCancelButton: true,
      confirmButtonText: 'حفظ',
      cancelButtonText: 'إلغاء',
      inputAttributes: {
        rows: '4',
        dir: 'rtl',
      },
      inputValidator: (value) => {
        if (!value || !String(value).trim()) {
          return 'يجب ادخال ملاحظة';
        }
        return undefined;
      },
    }).then((result) => (result.isConfirmed ? String(result.value || '').trim() : null));
  }

  addNote(orderId: number): void {
    if (!orderId) {
      return;
    }

    this.openNoteDialog('إضافة ملاحظة').then((value) => {
      if (!value) {
        return;
      }

      this.orderService.addNote(orderId, value).subscribe({
        next: () => {
          Swal.fire({
            icon: 'success',
            timer: 1500,
            showConfirmButton: false,
          });
          this.getOrder();
        },
        error: (err) => {
          Swal.fire({
            icon: 'error',
            title: 'تعذر حفظ الملاحظة',
            text: err?.error?.message || 'حدث خطأ',
          });
        },
      });
    });
  }

  editNote(note: any): void {
    if (!note?.id || !note?.can_edit) {
      return;
    }

    this.openNoteDialog('تعديل الملاحظة', note.note).then((value) => {
      if (!value || value === note.note) {
        return;
      }

      this.orderService.updateNote(note.id, value).subscribe({
        next: () => {
          Swal.fire({
            icon: 'success',
            timer: 1500,
            showConfirmButton: false,
          });
          this.getOrder();
        },
        error: (err) => {
          Swal.fire({
            icon: 'error',
            title: 'تعذر تعديل الملاحظة',
            text: err?.error?.message || 'حدث خطأ',
          });
        },
      });
    });
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
