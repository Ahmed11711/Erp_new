import { DatePipe } from '@angular/common';
import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { CategoryService } from 'src/app/categories/services/category.service';
import { BanksService } from 'src/app/financial/services/banks.service';
import { SafeService } from 'src/app/accounting/services/safe.service';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service';
import { SuppliersService } from 'src/app/suppliers/services/suppliers.service';
import { InvoiceService } from '../service/invoice.service';
import { finalize } from 'rxjs/operators';


@Component({
  selector: 'app-add-invoice',
  templateUrl: './add-invoice.component.html',
  styleUrls: ['./add-invoice.component.css']
})
export class AddInvoiceComponent implements OnInit {
  invoiceId;
  /** للعرض فقط عند التعديل */
  purchaseSerialId: number | null = null;
  errorMessage = '';
  /** يمنع النقر المتكرر أثناء إرسال الفاتورة */
  isSubmitting = false;
  /** رقم فاتورة داخلي مخصص؛ يُترك فارغاً للترقيم التلقائي PUR-xxxx */
  customInvoiceNo = '';
  /** رقم فاتورة المورد (ورقي) — اختياري */
  externalInvoiceNo = '';
  products:any[] = [];
  categories : any[] = [];
  suppliers : any[] = [];
  banks: any[] = [];
  safes: any[] = [];
  serviceAccounts: any[] = [];
  paymentType: 'bank' | 'safe' | 'service_account' = 'bank';
  safeId: number | null = null;
  serviceAccountId: number | null = null;
  keyword = 'supplier_name';
  catword = 'category_name';
  constructor(
    private bank: BanksService,
    private safeService: SafeService,
    private serviceAccountsService: ServiceAccountsService,
    private router: Router,
    private datePipe: DatePipe,
    private invoice: InvoiceService,
    private suppliersService: SuppliersService,
    private cat: CategoryService,
    private route: ActivatedRoute,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    this.invoiceId =
      this.route.snapshot.paramMap.get('id') ||
      this.route.snapshot.queryParamMap.get('editId');
    this.suppliersService.suppliersname().subscribe({
      next: (res: unknown) => {
        const raw = res != null && typeof res === 'object' && 'data' in (res as object)
          ? (res as { data: unknown }).data
          : res;
        this.suppliers = Array.isArray(raw) ? raw.filter((s: any) => s && (s.supplier_name ?? '').toString().trim() !== '') : [];
        this.cdr.markForCheck();
      },
      error: () => {
        this.suppliers = [];
        this.errorMessage = 'تعذّر تحميل قائمة الموردين. تحقّق من الصلاحيات أو اتصال الخادم.';
        this.cdr.markForCheck();
      },
    });

    this.cat.getCatBywarehouse('مخزن مواد خام').subscribe((res:any)=>{
      this.categories = res;
    })

    this.bank.bankSelect().subscribe((res: any) => {
      this.banks = res;
    });
    this.safeService.getAll({ per_page: 500 }).subscribe((res: any) => {
      this.safes = res.data || res || [];
    });
    this.serviceAccountsService.index().subscribe((res: any) => {
      this.serviceAccounts = res || [];
    });

    if (this.invoiceId) {
      this.purchaseSerialId = Number(this.invoiceId);
      this.invoice.getInvoiceById(this.invoiceId,true).subscribe(res=>{
        this.products = res['categories'];
        let status:any = document.getElementById('status');
        status.value = res['invoice'].invoice_type;
        this.status = res['invoice'].invoice_type;
        this.date = res['invoice'].receipt_date;
        this.totalInvice = res['invoice'].total_price;
        this.paidamount = res['invoice'].paid_amount;
        this.dueamount = res['invoice'].due_amount;
        this.transportcost = res['invoice'].transport_cost;
        this.paymentType = res['invoice'].payment_type || 'bank';
        this.bankId = res['invoice'].bank_id;
        this.safeId = res['invoice'].safe_id;
        this.serviceAccountId = res['invoice'].service_account_id;
        this.externalInvoiceNo = res['invoice'].external_invoice_no || '';
        const inv = res['invoice'];
        const no = inv?.invoice_no != null && String(inv.invoice_no).trim() !== ''
          ? String(inv.invoice_no).trim()
          : '';
        const num = inv?.invoice_number != null && String(inv.invoice_number).trim() !== ''
          ? String(inv.invoice_number).trim()
          : '';
        this.customInvoiceNo = no || num;
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
  paymentTypeChange() {
    this.bankId = null;
    this.safeId = null;
    this.serviceAccountId = null;
  }

  hasValidPaymentSource(): boolean {
    if (this.paidamount <= 0) return true;
    if (this.paymentType === 'bank') return !!this.bankId;
    if (this.paymentType === 'safe') return !!this.safeId;
    if (this.paymentType === 'service_account') return !!this.serviceAccountId;
    return false;
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
  /** يُرسل مع فاتورة المشتريات لربط السطر بصف واحد في categories (مخزن مواد خام) */
  categoryId: number | null = null;
  selectEvent(item) {
    this.productname = item.category_name;
    this.categoryId = item.id != null ? Number(item.id) : null;
    const qty = Number(item.quantity) || 0;
    const tp = Number(item.total_price) || 0;
    const wac = qty > 0 ? tp / qty : 0;
    if (wac > 0) {
      this.productprice = Math.round(wac * 10000) / 10000;
    } else if (item.unit_price != null && Number(item.unit_price) > 0) {
      this.productprice = Number(item.unit_price);
    } else {
      this.productprice = item.category_price;
    }
    this.originalPrice = this.productprice;
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
    this.products.push({
      product_quantity: this.productQuantity,
      price_edited: this.priceEdited,
      product_price: this.productprice,
      product_name: this.productname,
      total: this.productprice * this.productQuantity,
      product_unit: this.productUnit,
      category_id: this.categoryId,
    });
    this.calc();
    this.priceEdited = false;
  }
  dueamount=0;
  producttotal = 0;
  paidamount=0;
  transportcost=0;
  totalInvice = 0;

  addInvoice(form:any){
    if (this.isSubmitting) {
      return;
    }
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
    invoice.append('payment_type', this.paymentType);
    if (this.paymentType === 'bank' && this.bankId) {
      invoice.append('bank_id', this.bankId.toString());
    }
    if (this.paymentType === 'safe' && this.safeId) {
      invoice.append('safe_id', this.safeId.toString());
    }
    if (this.paymentType === 'service_account' && this.serviceAccountId) {
      invoice.append('service_account_id', this.serviceAccountId.toString());
    }
    const ext = (this.externalInvoiceNo || '').trim();
    if (ext) {
      invoice.append('external_invoice_no', ext);
    }
    const custom = (this.customInvoiceNo || '').trim();
    if (custom) {
      invoice.append('custom_invoice_no', custom);
    }
    if(this.selectedImg){
      invoice.append('invoice_image', this.selectedImg, this.selectedImg.name);
    }
    invoice.append('products', JSON.stringify(this.products));
    this.errorMessage = '';
    this.isSubmitting = true;
    this.invoice.addInvoice(invoice).pipe(
      finalize(() => { this.isSubmitting = false; })
    ).subscribe({
      next: (res:any)=>{
        if(res.success==true){
          this.router.navigate(['/dashboard/purchases/list_invoice']);
        }
      },
      error: (err) => {
        this.errorMessage = err.error?.message || 'حدث خطأ أثناء حفظ الفاتورة';
      }
    })
  }

  resetInp(){
    this.productprice = 0;
    this.productQuantity = 0;
    this.categoryId = null;
  }

}
