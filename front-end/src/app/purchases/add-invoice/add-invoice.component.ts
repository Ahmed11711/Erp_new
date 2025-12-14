import { DatePipe } from '@angular/common';
import { Component, ViewChild } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { th } from 'date-fns/locale';
import { CategoryService } from 'src/app/categories/services/category.service';
import { BanksService } from 'src/app/financial/services/banks.service';
import { SuppliersService } from 'src/app/suppliers/services/suppliers.service';
import { InvoiceService } from '../service/invoice.service';


@Component({
  selector: 'app-add-invoice',
  templateUrl: './add-invoice.component.html',
  styleUrls: ['./add-invoice.component.css']
})
export class AddInvoiceComponent {
  invoiceId;
  products:any[] = [];
  categories : any[] = [];
  suppliers : any[] = [];
  banks : any[] = [];
  keyword = 'supplier_name';
  catword = 'category_name';
  constructor(private bank:BanksService,private router: Router ,private datePipe: DatePipe, private invoice:InvoiceService, private supplier:SuppliersService, private cat : CategoryService, private activeRoute:ActivatedRoute) { }

  ngOnInit(): void {
    // this.invoiceId = this.activeRoute.snapshot.queryParams['invoiceId'];
    this.invoiceId = sessionStorage.getItem('editInvoiceId');
    this.supplier.suppliersname().subscribe((res:any)=>{
      this.suppliers = res;
    })

    this.cat.getCatBywarehouse('مخزن مواد خام').subscribe((res:any)=>{
      this.categories = res;
    })

    this.bank.bankSelect().subscribe((res:any)=>{
      this.banks = res;
    });

    if (this.invoiceId) {
      this.invoice.getInvoiceById(this.invoiceId,true).subscribe(res=>{
        console.log(res);
        this.products = res['categories'];
        let status:any = document.getElementById('status');
        status.value = res['invoice'].invoice_type;
        this.status = res['invoice'].invoice_type;
        this.date = res['invoice'].receipt_date;
        this.totalInvice = res['invoice'].total_price;
        this.paidamount = res['invoice'].paid_amount;
        this.dueamount = res['invoice'].due_amount;
        this.transportcost = res['invoice'].transport_cost;
        let bank:any = document.getElementById('bank');
        bank.value = res['invoice'].bank_id;
        this.bankId = res['invoice'].bank_id;
        let receipt_date:any = document.getElementById('receipt_date');
        receipt_date.value = res['invoice'].receipt_date;
        this.date = res['invoice'].receipt_date;
        this.supplierId = res['invoice'].supplier_id;
        let supplier: any = document.getElementById('supplier');
        this.suplierSelected = true;
        this.dateSelected = true;
        if (supplier) {
          const input = supplier.querySelector('input');
          if (input) {
            this.supplier = res['invoice'].supplier_id;
            input.value = res['invoice'].supplier.supplier_name;
          }
        }
      })
    }

  }

  changeQuantity(e,i){
    if (e.target.value > 1) {
      this.products[i].product_quantity = e.target.value;
      this.products[i].total = e.target.value * this.products[i].product_price;
      this.calc();
    } else {
      e.target.value = this.products[i].product_quantity;
      this.products[i].product_quantity = e.target.value;
      this.products[i].total = e.target.value * this.products[i].product_price;
      this.calc();
    }
  }

  myFilter = (d: Date | null): boolean => {
    const today = new Date();
    const selectedDate = d || today;
    const timeDifference = Math.floor((today.getTime() - selectedDate.getTime()) / (1000 * 60 * 60 * 24));
    return timeDifference >= 0 && timeDifference <= 3;
  };

  calc(){
    let paidamount = this.paidamount;
    if (this.status === 'مرتجع') {
      if (this.paidamount > 0) {
        paidamount = paidamount*-1;
      }
      this.products.forEach(elm=>{
        if (elm.product_quantity > 0) {
          elm.product_quantity = elm.product_quantity*-1;
        }
        elm.total = elm.product_quantity*elm.product_price;
      })
    } else {
      Math.abs(paidamount);
      this.products.forEach(elm=>{
        elm.product_quantity = Math.abs(elm.product_quantity);
        elm.total = elm.product_quantity*elm.product_price;
      })
    }
    this.producttotal = this.products.reduce((sum, product) => sum + product.total, 0);
    this.totalInvice = Number(this.producttotal)  + Number(this.transportcost);
    this.dueamount= this.totalInvice-paidamount;
  }

  reomveProduct(i:number){
    this.products.splice(i, 1);
    this.calc();
  }

  changeStatus(e){
    this.status = e.target.value;
    this.calc();
  }
  bankChange(e){
    this.bankId = e.target.value;
  }

  productname = '';
  productprice  = 0;
  productUnit='';
  productQuantity = 0;
  supplierId:any;
  status:any;
  bankId:any;
  priceEdited = false;
  originalPrice = 0;
  date : any;
  productSelected = false;
  selectEvent(item) {
    this.originalPrice = item.category_price;
    this.productname = item.category_name;
    this.productprice = item.category_price;
    this.productUnit = item.measurement.unit
    this.productSelected = true;
  }
  dateSelected = false;
  OnDateChange(event){

    const inputDate = new Date(event);
    this.date = this.datePipe.transform(inputDate, 'yyyy-M-d');
    this.dateSelected = true;
  }
  suplierSelected = false;
  supEvent(item){
    this.supplierId = item.id;
    this.suplierSelected = true;
  }
  resetSup(){
    this.supplierId = null;
    this.suplierSelected = false;
  }
  selectedImg: any = null;
  chooseImg(event){
    this.selectedImg = event.target.files[0];
  }
  invoicePriceEdited = 0;
  addToTabel(){
    if(this.originalPrice != this.productprice){
      this.priceEdited = true;
      this.invoicePriceEdited = 1;
    }
    this.products.push({product_quantity:this.productQuantity,price_edited:this.priceEdited,
      product_price:this.productprice,product_name:this.productname,total:this.productprice*this.productQuantity,product_unit:this.productUnit});
    this.calc();
    this.priceEdited = false;
  }
  dueamount=0;
  producttotal = 0;
  paidamount=0;
  transportcost=0;
  totalInvice = 0;

  addInvoice(form:any){
    let paidamount = this.paidamount;
    if (this.status === 'مرتجع') {
      paidamount = paidamount * -1;
    }

    let invoice = new FormData();
    if (this.invoiceId) {
      invoice.append('invoiceId', this.invoiceId);
    }
    invoice.append('supplier_id', this.supplierId);
    invoice.append('invoice_type', this.status);
    invoice.append('receipt_date', this.date);
    invoice.append('total_price', this.totalInvice.toString());
    invoice.append('paid_amount', paidamount.toString());
    invoice.append('due_amount', this.dueamount.toString());
    invoice.append('transport_cost', this.transportcost.toString());
    invoice.append('price_edited', this.invoicePriceEdited.toString());
    invoice.append('bank_id', this.bankId);
    if(this.selectedImg){
      invoice.append('invoice_image', this.selectedImg, this.selectedImg.name);
    }
    invoice.append('products', JSON.stringify(this.products));
    this.invoice.addInvoice(invoice).subscribe((res:any)=>{
      console.log(res);
      if(res.success==true){
        this.router.navigate(['/dashboard/purchases/list_invoice']);
      }
    })
  }

  resetInp(){
    this.productprice = 0;
    this.productQuantity = 0;
  }

  ngOnDestroy(): void {
    sessionStorage.removeItem('editInvoiceId');
  }
}
