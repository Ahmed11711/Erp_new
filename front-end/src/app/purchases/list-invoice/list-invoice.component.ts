import { Component } from '@angular/core';
import { InvoiceService } from '../service/invoice.service';
import { SuppliersService } from 'src/app/suppliers/services/suppliers.service';
import { Router } from '@angular/router';
import Swal from 'sweetalert2';
import { AuthService } from 'src/app/auth/auth.service';
import { purchaseStatusBadgeClass } from 'src/app/shared/utils/purchase-invoice-status.util';
import { resolvePurchaseListInvoiceType, resolvePurchaseListShippingRep, resolvePurchaseShippingRep } from 'src/app/shared/utils/purchase-shipping-rep.util';

@Component({
  selector: 'app-list-invoice',
  templateUrl: './list-invoice.component.html',
  styleUrls: ['./list-invoice.component.css', '../purchase-ui.shared.css']
})
export class ListInvoiceComponent {
  user!:string;

  statusBadgeClass = purchaseStatusBadgeClass;
  shippingRepName = resolvePurchaseListShippingRep;
  invoiceTypeLabel = resolvePurchaseListInvoiceType;

  suppliers : any[] = [];
  keyword = 'supplier_name';
  supplierId!:number;
  invoices : any[] = [];
  recieveDate!:string
  status!:string
  purchaseIdSearch = '';
  private purchaseIdSearchTimer: ReturnType<typeof setTimeout> | undefined;

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];
  selectedIds = new Set<number>();

  constructor(private invoice : InvoiceService, private supplier:SuppliersService, private route:Router, private authService:AuthService ) {
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
    this.route.navigate(['/dashboard/purchases/add_invoice', id]);
  }

  invoiceDetails(id){
    this.route.navigate(['/dashboard/purchases/invoice', id]);
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
            this.selectedIds.delete(id);
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
          const msg = error?.error?.message ?? 'تم الحذف من قبل وفي انتظار موافقة الأدمن';
          Swal.fire({
            icon: 'error',
            text: msg,
            timer: 3000,
            showConfirmButton:false
          })
        }
      )

    }})
  }

  isSelectable(item: { status?: string | number }): boolean {
    return String(item?.status) !== '1';
  }

  isSelected(id: number): boolean {
    return this.selectedIds.has(id);
  }

  toggleSelection(id: number, checked: boolean): void {
    if (checked) {
      this.selectedIds.add(id);
    } else {
      this.selectedIds.delete(id);
    }
  }

  get selectableInvoices(): any[] {
    return (this.invoices ?? []).filter((item) => this.isSelectable(item));
  }

  get allSelectableSelected(): boolean {
    const selectable = this.selectableInvoices;
    return selectable.length > 0 && selectable.every((item) => this.selectedIds.has(item.id));
  }

  get someSelectableSelected(): boolean {
    const selectable = this.selectableInvoices;
    const selectedCount = selectable.filter((item) => this.selectedIds.has(item.id)).length;
    return selectedCount > 0 && selectedCount < selectable.length;
  }

  toggleSelectAll(checked: boolean): void {
    if (checked) {
      this.selectableInvoices.forEach((item) => this.selectedIds.add(item.id));
    } else {
      this.selectableInvoices.forEach((item) => this.selectedIds.delete(item.id));
    }
  }

  deleteSelectedInvoices(): void {
    const ids = Array.from(this.selectedIds);
    if (ids.length === 0) {
      return;
    }

    Swal.fire({
      title: 'تأكيد حذف المحدد؟',
      html: `سيتم حذف <strong>${ids.length}</strong> فاتورة مشتريات مع عكس آثارها على المخزون والمحاسبة.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم، احذف',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      this.invoice.deleteInvoices(ids).subscribe({
        next: (res: any) => {
          const results = res?.results ?? {};
          const deleted = results.deleted?.length ?? 0;
          const pending = results.pending?.length ?? 0;
          const failed = results.failed ?? [];

          ids.forEach((id) => this.selectedIds.delete(id));
          this.search(arguments);

          if (failed.length > 0) {
            const lines = failed.map((f: { id: number; message: string }) => `#${f.id}: ${f.message}`).join('<br>');
            Swal.fire({
              icon: 'warning',
              title: 'اكتمل جزئياً',
              html: `تم: ${deleted} | انتظار موافقة: ${pending} | فشل: ${failed.length}<br><small>${lines}</small>`,
            });
            return;
          }

          if (this.user === 'Admin') {
            Swal.fire({ icon: 'success', title: `تم حذف ${deleted} فاتورة`, timer: 3000, showConfirmButton: false });
          } else {
            Swal.fire({
              icon: 'success',
              title: `تم إرسال ${pending} طلب حذف`,
              text: 'في انتظار موافقة الأدمن',
              timer: 3500,
              showConfirmButton: false,
            });
          }
        },
        error: (err) => {
          const msg = err?.error?.message ?? err?.error?.results?.failed?.[0]?.message ?? 'تعذر تنفيذ الحذف الجماعي';
          Swal.fire({ icon: 'error', title: 'فشل الحذف', text: String(msg) });
        },
      });
    });
  }

  resetInp(){
    this.supplierId = 0;
    if ('supplier_id' in this.param) {
      delete this.param.supplier_id;
    }
    this.search(arguments);
  }

  onPurchaseIdSearchChange(): void {
    if (this.purchaseIdSearchTimer) {
      clearTimeout(this.purchaseIdSearchTimer);
    }
    this.purchaseIdSearchTimer = setTimeout(() => {
      this.page = 0;
      this.search(arguments);
    }, 400);
  }

  param: Record<string, string | number> = {};
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

    const idTerm = String(this.purchaseIdSearch ?? '').trim();
    if (idTerm) {
      this.param['purchase_id'] = idTerm;
    } else if ('purchase_id' in this.param) {
      delete this.param.purchase_id;
    }

    this.invoice.search(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      this.invoices = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

}
