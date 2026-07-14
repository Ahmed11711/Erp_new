import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { MatDialog } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/env/env';
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
  invoiceStatusLabels: Record<string, string> = {
    draft: 'مسودة',
    posted: 'مرحّلة',
    partially_paid: 'مدفوعة جزئياً',
    paid: 'مدفوعة',
    cancelled: 'ملغاة',
  };
  receiptDate = new Date().toISOString().slice(0, 10);
  receiptLines: any[] = [];
  busy = false;
  products: any[] = [];
  productKeyword = 'category_name';
  private productSearchTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(
    private route: ActivatedRoute,
    private api: ProcessingService,
    private dialog: MatDialog,
    private http: HttpClient,
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

  searchProducts(query: string): void {
    const q = (query ?? '').trim();
    if (!q) {
      this.products = [];
      return;
    }
    this.http
      .get<any>(`${environment.Url}/categories/search`, {
        params: { itemsPerPage: 50, category_name: q },
      })
      .subscribe({
        next: (res) => {
          this.products = (res?.data || []).map((c: any) => ({
            ...c,
            category_name: c.category_name || '',
          }));
        },
      });
  }

  onProductInputChanged(value: string): void {
    if (this.productSearchTimer) {
      clearTimeout(this.productSearchTimer);
    }
    const q = (value ?? '').trim();
    if (!q) {
      this.products = [];
      return;
    }
    this.productSearchTimer = setTimeout(() => this.searchProducts(q), 300);
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

  get postedReceipts(): any[] {
    const list = this.order?.receipts || this.order?.Receipts || [];
    return Array.isArray(list) ? list : [];
  }

  receiptLinesOf(rcpt: any): any[] {
    const list = rcpt?.lines || [];
    return Array.isArray(list) ? list : [];
  }

  destCategoryOf(line: any): any {
    return line?.destination_category || line?.destinationCategory || null;
  }

  receiptDestName(line: any): string {
    return this.destCategoryOf(line)?.category_name || line?.category?.category_name || '—';
  }

  receiptDestUnit(line: any): string {
    const dc = this.destCategoryOf(line);
    return dc?.measurement?.unit || dc?.measurement?.name || '';
  }

  receiptLineQty(line: any): number {
    return (
      (Number(line?.good_qty) || 0) +
      (Number(line?.damaged_qty) || 0) +
      (Number(line?.rejected_qty) || 0)
    );
  }

  receiptTotalQty(rcpt: any): number {
    return this.receiptLinesOf(rcpt).reduce((s, l) => s + (Number(l.good_qty) || 0), 0);
  }

  receiptTotalService(rcpt: any): number {
    return this.receiptLinesOf(rcpt).reduce((s, l) => s + (Number(l.allocated_service_cost) || 0), 0);
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
      .map((l: any) => {
        const atVendor = Math.max(
          0,
          Number(l.dispatched_qty) -
            Number(l.received_good_qty) -
            Number(l.received_damaged_qty) -
            Number(l.received_rejected_qty),
        );
        const materialUnitCost =
          Number(l.unit_material_cost) || this.materialUnitCostFromDispatch(l) || 0;
        const orderedQty = Number(l.ordered_qty) || 0;
        const defaultLine = {
          processing_order_line_id: l.id,
          category_name: l.category?.category_name,
          at_vendor: atVendor,
          received_qty: atVendor,
          material_unit_cost: materialUnitCost,
          expected_service_amount: Number(l.expected_service_amount) || 0,
          ordered_qty: orderedQty,
          received_total_cost: 0,
          cost_material: 0,
          cost_service: 0,
          cost_manual: false,
          destination_category_id: null,
          destination_label: '',
          destination_meta: null as any,
          dest_unit_cost: null as number | null,
          dest_sell_price: null as number | null,
          add_new: false,
          new_product_name: '',
        };
        this.recalcLineCost(defaultLine);
        return defaultLine;
      });
  }

  /** تكلفة وحدة المواد من إذونات الصرف المرحّلة (احتياطي إن لم تُحفظ على السطر) */
  materialUnitCostFromDispatch(orderLine: any): number {
    const notes = this.order?.dispatch_notes || this.order?.dispatchNotes || [];
    for (const note of notes) {
      for (const dl of note.lines || []) {
        if (Number(dl.processing_order_line_id) === Number(orderLine.id)) {
          const uc = Number(dl.unit_cost);
          if (uc > 0) return uc;
        }
      }
    }
    return 0;
  }

  /** تكلفة الاستلام = تكلفة المعالجة فقط (وليست سعر الصنف ولا قيمة المواد) */
  recalcLineCost(line: any): void {
    const qty = Math.max(0, Number(line.received_qty) || 0);
    const materialTotal = (Number(line.material_unit_cost) || 0) * qty;
    const orderedQty = Number(line.ordered_qty) || 0;
    const serviceTotal =
      orderedQty > 0
        ? ((Number(line.expected_service_amount) || 0) * qty) / orderedQty
        : 0;
    line.cost_material = Math.round(materialTotal * 100) / 100;
    line.cost_service = Math.round(serviceTotal * 100) / 100;
    if (!line.cost_manual) {
      line.received_total_cost = Math.round(serviceTotal * 100) / 100;
    }
  }

  onReceiptQtyChange(line: any): void {
    line.cost_manual = false;
    this.recalcLineCost(line);
  }

  onReceiptCostChange(line: any): void {
    line.cost_manual = true;
  }

  destinationMetaFromItem(item: any): any {
    if (!item) return null;
    const qty = Number(item.quantity) || 0;
    const totalPrice = Number(item.total_price) || 0;
    const avgCost = qty > 0 ? totalPrice / qty : Number(item.unit_price) || 0;
    return {
      item_code: item.item_code || '—',
      warehouse: item.warehouse || '—',
      measurement: item.measurement?.unit || item.measurement?.name || '—',
      quantity: qty,
      avg_cost: Math.round(avgCost * 10000) / 10000,
      current_unit_cost: Math.round((Number(item.unit_price) || avgCost) * 10000) / 10000,
      current_sell_price: Math.round((Number(item.category_price) || 0) * 10000) / 10000,
    };
  }

  lineTotalCost(line: any): number {
    return Number(line.received_total_cost) || 0;
  }

  onReceiptDestSelected(line: any, item: any): void {
    if (!item?.id) return;
    line.destination_category_id = item.id;
    line.destination_label = this.stripHighlightTags(String(item.category_name ?? ''));
    line.destination_meta = this.destinationMetaFromItem(item);
    line.add_new = false;
    line.new_product_name = '';
    // Prefill the item's own cost & selling price so the user can keep or change them.
    line.dest_unit_cost = line.destination_meta?.current_unit_cost ?? null;
    line.dest_sell_price = line.destination_meta?.current_sell_price || null;
    this.recalcLineCost(line);
  }

  /** تكلفة الوحدة المقترحة لمنتج جديد = (مواد + معالجة) لكل وحدة */
  suggestedNewUnitCost(line: any): number {
    const qty = Math.max(0, Number(line.received_qty) || 0);
    if (qty <= 0) return 0;
    const total = (Number(line.cost_material) || 0) + (Number(line.cost_service) || 0);
    return Math.round((total / qty) * 100) / 100;
  }

  clearReceiptDest(line: any): void {
    line.destination_category_id = null;
    line.destination_label = '';
    line.destination_meta = null;
    line.dest_unit_cost = null;
    line.dest_sell_price = null;
  }

  toggleNewProduct(line: any): void {
    line.add_new = !line.add_new;
    if (line.add_new) {
      line.destination_category_id = null;
      line.destination_label = '';
      line.destination_meta = null;
      line.dest_unit_cost = this.suggestedNewUnitCost(line) || null;
      line.dest_sell_price = null;
    } else {
      line.new_product_name = '';
    }
  }

  canReceipt(): boolean {
    return this.receiptLines.length > 0;
  }

  totalReceiptQty(): number {
    return this.receiptLines.reduce((s, l) => s + (Number(l.received_qty) || 0), 0);
  }

  totalReceiptCost(): number {
    return this.receiptLines.reduce((s, l) => s + this.lineTotalCost(l), 0);
  }

  private stripHighlightTags(value: string): string {
    return String(value ?? '').replace(/<\/?b>/gi, '');
  }

  selectedCategoryLabel = (item: any): string => {
    if (!item?.category_name) return '';
    return this.stripHighlightTags(String(item.category_name));
  };

  filterCategorySearch = (items: any[], query: string) => {
    const q = (query ?? '').trim().toLowerCase();
    if (!q) return [...items];
    return items.filter((item) => {
      const name = this.stripHighlightTags(String(item.category_name ?? '')).toLowerCase();
      const code = String(item.item_code ?? '').toLowerCase();
      const wh = String(item.warehouse ?? '').toLowerCase();
      return name.includes(q) || code.includes(q) || wh.includes(q);
    });
  };

  createReceipt(): void {
    const lines = this.receiptLines.filter((l) => Number(l.received_qty) > 0);
    if (!lines.length) {
      Swal.fire('تنبيه', 'أدخل كميات الاستلام', 'warning');
      return;
    }
    const missingNewName = lines.some(
      (l) => l.add_new && !String(l.new_product_name || '').trim(),
    );
    if (missingNewName) {
      Swal.fire('تنبيه', 'أدخل اسم المنتج الجديد للصنف المجهز', 'warning');
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
          received_total_cost: Number(l.received_total_cost) || 0,
          dest_unit_cost: l.dest_unit_cost != null ? Number(l.dest_unit_cost) : null,
          dest_sell_price: l.dest_sell_price != null ? Number(l.dest_sell_price) : null,
          destination_category_id: l.add_new ? null : l.destination_category_id || null,
          new_product_name: l.add_new ? (l.new_product_name || '').trim() : null,
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
