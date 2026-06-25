import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { MatDialog } from '@angular/material/dialog';
import { ProcessingService } from '../services/processing.service';
import {
  DialogPayMoneyForSupplierComponent,
  SupplierPayDialogData,
} from 'src/app/suppliers/dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-processing-order-detail',
  templateUrl: './processing-order-detail.component.html',
  styleUrls: ['./processing-order-detail.component.css'],
})
export class ProcessingOrderDetailComponent implements OnInit {
  order: any = null;
  statusLabels: Record<string, string> = {};
  receiptDate = new Date().toISOString().slice(0, 10);
  receiptLines: any[] = [];
  busy = false;

  constructor(
    private route: ActivatedRoute,
    private api: ProcessingService,
    private dialog: MatDialog,
  ) {}

  ngOnInit(): void {
    this.api.meta().subscribe((m) => {
      this.statusLabels = m?.order_statuses || {};
    });
    this.route.paramMap.subscribe((p) => {
      const id = Number(p.get('id'));
      if (id) this.load(id);
    });
  }

  load(id: number): void {
    this.api.getOrder(id).subscribe({
      next: (o) => {
        this.order = o;
        this.initReceiptLines();
      },
    });
  }

  primaryDispatch(): any {
    const notes = this.order?.dispatch_notes || this.order?.dispatchNotes || [];
    return notes.length ? notes[0] : null;
  }

  representativeLabel(): string {
    const d = this.primaryDispatch();
    if (!d) return '—';
    if (d.representative_type === 'internal') {
      return d.shipping_company?.name || d.shippingCompany?.name || 'مندوب بالنظام';
    }
    if (d.representative_type === 'external') {
      return d.external_representative_name || 'مندوب خارجي';
    }
    return '—';
  }

  initReceiptLines(): void {
    this.receiptLines = (this.order?.lines || [])
      .filter((l: any) => {
        const atVendor =
          Number(l.dispatched_qty) -
          Number(l.received_good_qty) -
          Number(l.received_damaged_qty) -
          Number(l.received_rejected_qty);
        return atVendor > 0.000001;
      })
      .map((l: any) => ({
        processing_order_line_id: l.id,
        category_name: l.category?.category_name,
        at_vendor: Math.max(
          0,
          Number(l.dispatched_qty) -
            Number(l.received_good_qty) -
            Number(l.received_damaged_qty) -
            Number(l.received_rejected_qty),
        ),
        received_qty: Math.max(
          0,
          Number(l.dispatched_qty) -
            Number(l.received_good_qty) -
            Number(l.received_damaged_qty) -
            Number(l.received_rejected_qty),
        ),
      }));
  }

  canReceipt(): boolean {
    return this.receiptLines.length > 0;
  }

  totalReceiptQty(): number {
    return this.receiptLines.reduce((s, l) => s + (Number(l.received_qty) || 0), 0);
  }

  createReceipt(): void {
    const lines = this.receiptLines.filter((l) => Number(l.received_qty) > 0);
    if (!lines.length) {
      Swal.fire('تنبيه', 'أدخل كميات الاستلام', 'warning');
      return;
    }
    this.busy = true;
    this.api
      .createReceipt({
        processing_order_id: this.order.id,
        receipt_date: this.receiptDate,
        lines: lines.map((l) => ({
          processing_order_line_id: l.processing_order_line_id,
          received_qty: l.received_qty,
          good_qty: l.received_qty,
        })),
      })
      .subscribe({
        next: (rcpt) => {
          this.api.postReceipt(rcpt.id).subscribe({
            next: () => {
              this.busy = false;
              Swal.fire('تم', 'تم ترحيل إذن الاستلام', 'success');
              this.load(this.order.id);
            },
            error: (e) => {
              this.busy = false;
              Swal.fire('خطأ', e?.error?.message || 'فشل ترحيل الاستلام', 'error');
            },
          });
        },
        error: (e) => {
          this.busy = false;
          Swal.fire('خطأ', e?.error?.message || 'فشل إنشاء الاستلام', 'error');
        },
      });
  }

  paySupplier(): void {
    const supplier = this.order?.supplier;
    if (!supplier?.id) {
      Swal.fire('تنبيه', 'لا يوجد مورد مرتبط', 'warning');
      return;
    }

    const openInvoices = (this.order.invoices || []).filter(
      (inv: any) =>
        ['posted', 'partially_paid'].includes(inv.status) && Number(inv.due_amount) > 0.000001,
    );
    const totalDue = openInvoices.reduce((s: number, inv: any) => s + Number(inv.due_amount || 0), 0);
    const supplierBalance = Number(supplier.balance ?? 0);
    const primaryInvoice = openInvoices.length === 1 ? openInvoices[0] : null;
    const maxPay =
      totalDue > 0
        ? Math.min(totalDue, supplierBalance > 0 ? supplierBalance : totalDue)
        : supplierBalance > 0
          ? supplierBalance
          : undefined;

    const dialogData: SupplierPayDialogData = {
      supplier: {
        id: supplier.id,
        supplier_name: supplier.supplier_name,
        balance: supplierBalance,
      },
      dialogTitle: 'سداد المورد',
      subtitle: this.primaryDispatch()?.dispatch_number
        ? `إذن صرف: ${this.primaryDispatch().dispatch_number}`
        : undefined,
      hint: 'اختر مصدر الدفع (بنك / خزينة / حساب خدمي) ثم أدخل المبلغ.',
      suggestedAmount: maxPay && maxPay > 0 ? maxPay : undefined,
      maxAmount: maxPay,
      processingInvoiceId: primaryInvoice?.id,
      processingOrderId: this.order.id,
      refreshData: () => this.load(this.order.id),
    };

    this.dialog.open(DialogPayMoneyForSupplierComponent, {
      width: '420px',
      maxWidth: '95vw',
      panelClass: 'supplier-pay-dialog',
      data: dialogData,
    });
  }
}
