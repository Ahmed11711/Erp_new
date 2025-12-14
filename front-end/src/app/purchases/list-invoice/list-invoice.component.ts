import { Component } from '@angular/core';
import { InvoiceService } from '../service/invoice.service';
import { SuppliersService } from 'src/app/suppliers/services/suppliers.service';
import { Router } from '@angular/router';
import Swal from 'sweetalert2';
import { AuthService } from 'src/app/auth/auth.service';

@Component({
  selector: 'app-list-invoice',
  templateUrl: './list-invoice.component.html',
  styleUrls: ['./list-invoice.component.css']
})
export class ListInvoiceComponent {
  user!:string;

  suppliers : any[] = [];
  keyword = 'supplier_name';
  supplierId!:number;
  invoices : any[] = [];
  recieveDate!:string
  status!:string

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  constructor(private invoice : InvoiceService, private supplier:SuppliersService, private route:Router, private authService:AuthService ) {
    sessionStorage.removeItem('editInvoiceId');
    sessionStorage.removeItem('invoiceId');
  }

  ngOnInit(){
    this.user = this.authService.getUser();
    this.supplier.suppliersname().subscribe((res:any)=>{
      this.suppliers = res;
    });
    this.search(arguments);
  }

  suplierSelected = false;
  supEvent(item){
    this.supplierId = item.id;
    this.suplierSelected = true;
    this.search(arguments);
  }


  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
  }


  onrecieveDateChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.recieveDate = target.value;
    this.search(event);

  }

  editInvoice(id){
    sessionStorage.setItem('editInvoiceId', id);
    // this.route.navigateByUrl(`/dashboard/purchases/add_invoice?invoiceId=${id}`);
    this.route.navigateByUrl(`/dashboard/purchases/add_invoice`);
  }

  invoiceDetails(id){
    sessionStorage.setItem('invoiceId', id);
    // this.route.navigateByUrl(`/dashboard/purchases/add_invoice?invoiceId=${id}`);
    this.route.navigateByUrl(`/dashboard/purchases/invoice`);
  }

  deleteInvoice(id){
    Swal.fire({
      title: ' تاكيد الحذف ؟',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم',
      cancelButtonText: 'لا',
    }).then((result:any) => {
      if (result.isConfirmed) {
        this.invoice.deleteInvoice(id).subscribe(res=>{
          if (res) {
            this.search(arguments);
            if (this.user == 'Admin') {
              Swal.fire({
                icon: 'success',
                timer: 3000,
                showConfirmButton:false
              })
            } else {
              Swal.fire({
                icon: 'success',
                text: 'في انتظار موافقة الأدمن',
                timer: 3000,
                showConfirmButton:false
              })
            }
          }
        }, error => {
          Swal.fire({
            icon: 'error',
            text: 'تم الحذف من قبل وفي انتظار موافقة الأدمن',
            timer: 3000,
            showConfirmButton:false
          })
        }
      )

    }})
  }

  resetInp(){
    this.supplierId = 0;
    if ('supplier_id' in this.param) {
      delete this.param.supplier_id;
    }
    this.search(arguments);
  }

  param = {};
  search(event:any){
    // const param = {};

    if(this.recieveDate){
      this.param['receipt_date']=this.recieveDate;
    }

    if(event.target?.id=='status'){
      this.status = event.target?.value;
    }

    if(this.status){
      this.param['invoice_type']=this.status;
    }

    if (this.supplierId && this.supplierId !=0) {
      this.param['supplier_id']=this.supplierId;
    }

    this.invoice.search(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      this.invoices = res.data;
      const currentDate = new Date().toISOString().split('T')[0];
      this.invoices.forEach(item => {
        item.created_at = currentDate;
      });
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

}
