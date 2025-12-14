import { DatePipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ActivatedRoute, Router } from '@angular/router';
import { BanksService } from 'src/app/financial/services/banks.service';
import { SnackBarComponent } from 'src/app/shared/snack-bar/snack-bar.component';
import { OrderSourceService } from '../services/order-source.service';
import { OrderService } from '../services/order.service';
import { ShippingWayService } from '../services/shipping-way.service';
import { AuthService } from 'src/app/auth/auth.service';
import Swal from 'sweetalert2';
import { environment } from 'src/env/env';

@Component({
  selector: 'app-edit-order',
  templateUrl: './edit-order.component.html',
  styleUrls: ['./edit-order.component.css']
})
export class EditOrderComponent {
  user!:string;

  id!:number;
  customer_companyID!:number

  order!:any

  shippingWays:any[]=[];
  orderSources:any[]=[];
  errormessage:boolean=false;
  products:any[]=[];
  banksData:any[]=[];

  openbtn:boolean=true;
  formdiv:boolean=false;
  addbtn:boolean =false;
  editbtn:boolean =false;
  imgUrl!: string;


  constructor(private orderSource:OrderSourceService ,
    private shippingWay:ShippingWayService ,
    private orderService:OrderService ,
    private datePipe: DatePipe,
    private bankService:BanksService,
    private http:HttpClient,
    private _snackBar:MatSnackBar,
    private router:Router,
    private route:ActivatedRoute,
    private authService:AuthService
    ){
      this.imgUrl = environment.imgUrl;

    }

  fixedOldOrders:any[]=[];
  ngOnInit(){
    this.user = this.authService.getUser();
    this.route.params.subscribe((result:any)=>{
      this.id = result?.id;
    });
    this.bankService.bankSelect().subscribe((result:any)=>this.banksData=result);
    this.getOrder();

    this.orderService.getProducts().subscribe((result:any)=>this.products = result);

  }

  getOrder(){
    this.orderService.getOrderById(this.id).subscribe((result:any)=>{
      this.order = result;

      this.customer_companyID = result?.company_id;

      this.vat = result?.vat;
      this.discount = result?.discount;
      this.shipping_cost = result?.shipping_cost;
      this.prepaid_amount = result?.prepaid_amount;

      this.customerTypeVal = result?.customer_type;

      this.order_details = result?.order_products.map(elm=>{
        return {
          category_name:elm.category.category_name,
          category_id:elm.category.id,
          quantity:elm.quantity,
          minQuantity:elm.quantity,
          shipped_quantity:elm.shipped_quantity,
          special_details:elm.special_details,
          price:elm.price,
          total:elm.total_price,
          imgsrc:elm.category.category_image
        }
      });

      this.totalInvoice = result?.total_invoice;
      this.net_total = result?.net_total;

      this.form.patchValue({
        'shipping_cost' :result.shipping_cost,
        'prepaid_amount' :result.prepaid_amount,
        'discount' :result.discount,
        'order_notes' :result.order_notes,
        'bank':result?.bank_id
      })


    })
  }

  openForm(){
    this.openbtn = false;
    this.formdiv = true;
    this.addbtn = true;
    this.editbtn = false;
  }

  productID!:number;
  quantity!:number;


  editQuantity(e,index:number){
    if ((this.user == 'Shipping Management' || this.user == 'Operation Management' || this.user == 'Finance and operations management') && e.target.value < this.order_details[index].minQuantity) {
      Swal.fire({
        icon:'error',
        title:'لا يمكنك تقليل الكمية'
      })
      e.target.value = this.order_details[index].quantity;
    } else if(e.target.value == 0){
      e.target.value = this.order_details[index].quantity;
    } else {
      this.order_details[index].quantity = Number(e.target.value);
      this.order_details[index].total = this.order_details[index].quantity * this.order_details[index].price;
      this.calc(arguments);
    }
  }


  imgtext:string="صورة الايصال"
  fileopend:boolean=false;

  openFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
      this.fileopend=true;
    }
  }

  selectedFile: any;
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.imgtext = this.selectedFile?.name || 'No image selected';
  }


  form:FormGroup = new FormGroup({
    'bank' :new FormControl(null),
    'productprice' :new FormControl(null ),
    'productquantity' :new FormControl(null ),
    'order_notes' :new FormControl(null ),
    'order_image' :new FormControl(null),
    'total_invoice' :new FormControl(null ),
    'shipping_cost' :new FormControl(null ),
    'prepaid_amount' :new FormControl(null ),
    'discount' :new FormControl(null),
    'net_total' :new FormControl(null),
    'vat' :new FormControl(null),
  })

  changeProductPrice(e:any){
    this.category_price = e.target.value;
  }

  resetInp(){
    this.form.get('productprice')?.reset();
    this.form.get('productquantity')?.reset();
  }
  changedCollectNote!:string;
  submitform(){
    if (this.order.order_status == 'تم شحن') {
      Swal.fire({
        title: 'ملحوظة التحصيل المتغير',
        input: 'text',
        showCancelButton: true,
        inputValidator: (value) => {
          if (!value) {
            return 'يجب ادخال قيمة'
          }
          if (value !== '') {
            this.changedCollectNote = value;
            this.formValueFn();
          }
          return undefined
        }
      })

    } else {
      this.formValueFn();
    }
  }

  disableScroll(event: WheelEvent) {
    event.preventDefault();
  }


  formValueFn(){
    let data = this.form.value
    data.shipping_cost = this.shipping_cost || 0
    data.total_invoice = this.totalInvoice
    data.prepaid_amount = this.prepaid_amount
    data.discount = this.discount || 0
    data.net_total = this.net_total
    data.order_id = this.id
    data.customer_company = this.customer_companyID
    data.orders = this.order_details
    data.vat = this.vat

    const formData = new FormData();
    formData.append('shipping_cost', data.shipping_cost);
    formData.append('total_invoice', data.total_invoice);
    formData.append('prepaid_amount', data.prepaid_amount);
    formData.append('discount', data.discount);
    formData.append('net_total', data.net_total);
    formData.append('order_id', data.order_id);
    formData.append('bank_id', data.bank);
    formData.append('vat', data.vat);
    if(this.customerTypeVal == 'شركة'){
      formData.append('company_id', data.customer_company);
    }
    if(this.changedCollectNote){
      formData.append('changed_collect_note', this.changedCollectNote);
    }
    formData.append('order_details', JSON.stringify(data.orders));

    if (this.selectedFile) {
      formData.append('order_image', this.selectedFile, this.selectedFile.name);
    }

    this.orderService.editOrder(this.id,formData).subscribe(result=>{
      this.errormessage=false;
      this.getOrder();
      Swal.fire({
        icon:'success',
        title: 'تم تعديل الطلب',
        showConfirmButton:false,
        timerProgressBar:true,
        timer:1000
      })
      if (this.order.order_status == 'تم شحن') {
        this.router.navigateByUrl(`/dashboard/shipping/collectorder/${this.id}`)
      }
    },
    (error)=>{
      console.log(error);

      this.errormessage=true
    });

  }

  orderStatus!:string ;
  status(event:any){
    this.orderStatus = event.target.value;
  }

  // -----------------------------fill table
  category_name!:string;
  category_quantity!:number;
  category_price!:number;
  category_image!:string;
  category_id!:string;

  order_details:any[]=[];

  catword = 'category_name';
  productChange(event) {
    this.category_price = event.category_price
    this.category_image = event.category_image
    this.category_name = event.category_name
    this.category_id = event.id
  }

  addproduct(){
    if (this.category_name &&typeof(this.category_quantity) =='number'   && this.category_price) {
      let oldPrice = this.products.find(elm => elm.id === this.category_id).category_price;
      const product = {
        category_id: this.category_id,
        category_name: this.category_name,
        shipped_quantity: 0,
        quantity:this.category_quantity,
        price : Number(this.category_price) ,
        oldPrice : oldPrice ,
        imgsrc:this.category_image,
        total : this.category_price*this.category_quantity
      }

      this.order_details.push(product);
      this.calc(arguments);
      this.form.get('productquantity')?.reset();
      this.formdiv = false;
      this.openbtn = true;
      this.addbtn = false;
    }
  }

  removeProduct(i:number){
    this.productID = -1;
    this.openbtn = true;
    this.formdiv = false;
    this.editbtn = false;
    this.addbtn = false;
    const data = this.order_details.splice(i,1);
    this.calc(arguments);
  }
  //-------------------------------------------------end table

  totalInvoice:number=0;
  net_total:number=0;
  vat:number=0;
  changedVat:boolean=false;
  customerTypeVal :string = 'افراد';

  shipping_cost:number=0;
  productsPrice:number=0;
  prepaid_amount:number=0;
  discount:number=0;
  vatPercent:number=14;
  calc(e:any){
    this.productsPrice = 0;
    this.order_details.forEach(elm=>{
      this.productsPrice += elm.total
    });
    if (e?.target?.id == 'vat') {
      this.changedVat = true;
    }
    if (!this.changedVat) {
      this.vat = this.productsPrice * this.vatPercent/100;
    }
    this.totalInvoice = (this.productsPrice + this.shipping_cost);
    if(this.customerTypeVal == 'شركة'){
      this.totalInvoice = this.totalInvoice + this.vat;

    }
    this.net_total = this.totalInvoice - this.prepaid_amount - this.discount;
  }




}
