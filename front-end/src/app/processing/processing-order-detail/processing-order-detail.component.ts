import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { MatDialog } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/env/env';
import { ProcessingService } from '../services/processing.service';
import {
  DialogPayMoneyForSupplierComponent,
  SupplierPayDialogData,
} from 'src/app/suppliers/dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import { RbacService } from 'src/app/core/rbac/rbac.service';
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
  productsLoading = false;
  productKeyword = 'category_name';
  deleting = false;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private api: ProcessingService,
    private dialog: MatDialog,
    private http: HttpClient,
    readonly rbac: RbacService,
  ) {}

  canDeleteOrder(): boolean {
    return this.rbac.can('processing.delete_order') || this.rbac.can('system.rbac');
  }

  deleteOrder(): void {
    if (!this.order?.id || !this.canDeleteOrder() || this.deleting) return;

    const label = this.primaryDispatch()?.dispatch_number || this.order.order_number || `#${this.order.id}`;
    Swal.fire({
      title: 'حذف أمر التشغيل؟',
      html: `سيتم حذف «<strong>${label}</strong>» وعكس المخزون والقيود وذمة المورد كأن الأمر لم يُنفَّذ.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم، احذف',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#b91c1c',
      input: 'text',
      inputPlaceholder: 'سبب الحذف (اختياري)',
    }).then((result) => {
      if (!result.isConfirmed) return;
      this.deleting = true;
      this.api.deleteOrder(this.order.id, result.value || undefined).subscribe({
        next: (res) => {
          this.deleting = false;
          Swal.fire('تم', res?.message || 'تم حذف أمر التشغيل وعكس البيانات', 'success').then(() => {
            this.router.navigate(['/dashboard/processing/orders']);
          });
        },
        error: (e) => {
          this.deleting = false;
          Swal.fire('خطأ', e?.error?.message || 'تعذر حذف أمر التشغيل', 'error');
        },
      });
    });
  }

  ngOnInit(): void {
    this.api.meta().subscribe((m) => {
      this.statusLabels = m?.order_statuses || {};
    });
    this.loadAllProducts();
    this.route.paramMap.subscribe((p) => {
      const id = Number(p.get('id'));
      if (id) this.load(id);
    });
  }

  /** كل الأصناف من كل المخازن — البحث محلي على القائمة الكاملة */
  loadAllProducts(): void {
    this.productsLoading = true;
    this.http.get<any>(`${environment.Url}/allcategories`).subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : res?.data || [];
        this.products = list
          .map((c: any) => ({
            ...c,
            category_name: c.category_name || '',
            warehouse: c.warehouse || c.stock?.name || '—',
          }))
          .sort((a: any, b: any) => {
            const wh = String(a.warehouse).localeCompare(String(b.warehouse), 'ar');
            if (wh !== 0) return wh;
            return String(a.category_name).localeCompare(String(b.category_name), 'ar');
          });
        this.productsLoading = false;
      },
      error: () => {
        this.products = [];
        this.productsLoading = false;
      },
    });
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

  receiptTotalWaste(rcpt: any): number {
    return this.receiptLinesOf(rcpt).reduce((s, l) => s + (Number(l.damaged_qty) || 0), 0);
  }

  receiptTotalService(rcpt: any): number {
    return this.receiptLinesOf(rcpt).reduce((s, l) => s + (Number(l.allocated_service_cost) || 0), 0);
  }

  costModeLabel(mode: string | null | undefined): string {
    if (mode === 'overwrite') return 'استبدال التكلفة';
    if (mode === 'weighted_average') return 'متوسط مرجّح';
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
          waste_qty: 0,
          remaining_qty: 0,
          material_unit_cost: materialUnitCost,
          expected_service_amount: Number(l.expected_service_amount) || 0,
          ordered_qty: orderedQty,
          received_total_cost: 0,
          cost_material: 0,
          cost_waste: 0,
          cost_service: 0,
          cost_manual: false,
          cost_apply_mode: 'weighted_average' as 'weighted_average' | 'overwrite',
          destination_category_id: null,
          destination_label: '',
          destination_meta: null as any,
          dest_unit_cost: null as number | null,
          dest_sell_price: null as number | null,
          suggested_unit_cost: 0,
          show_cost_details: false,
          add_new: false,
          new_product_name: '',
        };
        this.syncQtyTriplet(defaultLine, 'received');
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

  private round4(n: number): number {
    return Math.round(Math.max(0, n) * 10000) / 10000;
  }

  /**
   * المستلم + الهالك + المتبقي = لدى المورد.
   * تغيير أي خانة يعيد ضبط الباقي دون إجبار الهالك على امتصاص الفرق.
   */
  syncQtyTriplet(line: any, changed: 'received' | 'waste' | 'remaining'): void {
    const at = this.round4(Number(line.at_vendor) || 0);
    let good = this.round4(Number(line.received_qty) || 0);
    let waste = this.round4(Number(line.waste_qty) || 0);
    let remaining = this.round4(Number(line.remaining_qty) || 0);

    if (changed === 'received') {
      if (good > at) good = at;
      if (good + waste > at) waste = this.round4(at - good);
      remaining = this.round4(at - good - waste);
    } else if (changed === 'waste') {
      if (waste > at) waste = at;
      if (good + waste > at) good = this.round4(at - waste);
      remaining = this.round4(at - good - waste);
    } else {
      // remaining: يضبط الكمية المستلمة ويُبقي الهالك إن أمكن
      if (remaining > at) remaining = at;
      if (waste > at - remaining) waste = this.round4(at - remaining);
      good = this.round4(at - waste - remaining);
    }

    line.received_qty = good;
    line.waste_qty = waste;
    line.remaining_qty = remaining;
  }

  remainingAtVendor(line: any): number {
    return this.round4(Number(line.remaining_qty) || 0);
  }

  /** تكلفة الاستلام = تكلفة المعالجة فقط؛ وتُحسب تكلفة المواد/الهالك للعرض */
  recalcLineCost(line: any): void {
    const qty = Math.max(0, Number(line.received_qty) || 0);
    const waste = Math.max(0, Number(line.waste_qty) || 0);
    const matUnit = Number(line.material_unit_cost) || 0;
    const materialTotal = matUnit * (qty + waste);
    const wasteTotal = matUnit * waste;
    const orderedQty = Number(line.ordered_qty) || 0;
    const serviceTotal =
      orderedQty > 0
        ? ((Number(line.expected_service_amount) || 0) * qty) / orderedQty
        : 0;
    line.cost_material = Math.round(materialTotal * 100) / 100;
    line.cost_waste = Math.round(wasteTotal * 100) / 100;
    line.cost_service = Math.round(serviceTotal * 100) / 100;
    if (!line.cost_manual) {
      line.received_total_cost = Math.round(serviceTotal * 100) / 100;
    }
    line.suggested_unit_cost = this.suggestedReceiptUnitCost(line);
    if (line.dest_unit_cost == null || !line.cost_unit_manual) {
      line.dest_unit_cost = line.suggested_unit_cost || null;
    }
  }

  /** (مادة المستلم + مادة الهالك + معالجة) ÷ الكمية الجيّدة — الهالك يُرسمَل داخل تكلفة الصنف */
  suggestedReceiptUnitCost(line: any): number {
    const qty = Math.max(0, Number(line.received_qty) || 0);
    if (qty <= 0) return 0;
    const total =
      (Number(line.cost_material) || 0) + (Number(line.cost_service) || 0);
    return Math.round((total / qty) * 10000) / 10000;
  }

  previewWeightedAvg(line: any): number {
    const oldQty = Number(line.destination_meta?.quantity) || 0;
    const oldCost = Number(line.destination_meta?.avg_cost) || 0;
    const recvQty = Math.max(0, Number(line.received_qty) || 0);
    const recvCost = Number(line.dest_unit_cost) || this.suggestedReceiptUnitCost(line) || 0;
    const newQty = oldQty + recvQty;
    if (newQty <= 0) return recvCost;
    return Math.round(((oldQty * oldCost + recvQty * recvCost) / newQty) * 10000) / 10000;
  }

  previewOverwrite(line: any): number {
    return Number(line.dest_unit_cost) || this.suggestedReceiptUnitCost(line) || 0;
  }

  onReceiptQtyChange(line: any): void {
    this.syncQtyTriplet(line, 'received');
    line.cost_manual = false;
    line.cost_unit_manual = false;
    this.recalcLineCost(line);
  }

  onWasteQtyChange(line: any): void {
    this.syncQtyTriplet(line, 'waste');
    line.cost_unit_manual = false;
    this.recalcLineCost(line);
  }

  onRemainingQtyChange(line: any): void {
    this.syncQtyTriplet(line, 'remaining');
    line.cost_manual = false;
    line.cost_unit_manual = false;
    this.recalcLineCost(line);
  }

  onReceiptCostChange(line: any): void {
    line.cost_manual = true;
  }

  onDestUnitCostChange(line: any): void {
    line.cost_unit_manual = true;
  }

  onCostModeChange(line: any): void {
    // إعادة تعبئة التكلفة المقترحة إن لم يُعدّلها المستخدم يدوياً
    if (!line.cost_unit_manual) {
      line.dest_unit_cost = this.suggestedReceiptUnitCost(line) || null;
    }
  }

  toggleCostDetails(line: any): void {
    const opening = !line.show_cost_details;
    this.receiptLines.forEach((l) => (l.show_cost_details = false));
    line.show_cost_details = opening;
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
    line.destination_category_id = Number(item.id);
    line.destination_label = this.stripHighlightTags(String(item.category_name ?? ''));
    line.destination_meta = this.destinationMetaFromItem(item);
    line.add_new = false;
    line.new_product_name = '';
    line.cost_apply_mode = line.cost_apply_mode || 'weighted_average';
    line.cost_unit_manual = false;
    line.dest_sell_price = null;
    line.show_cost_details = false;
    this.recalcLineCost(line);
  }

  /** تكلفة الوحدة المقترحة لمنتج جديد = (مواد بالهالك + معالجة) لكل وحدة مستلمة */
  suggestedNewUnitCost(line: any): number {
    return this.suggestedReceiptUnitCost(line);
  }

  clearReceiptDest(line: any): void {
    line.destination_category_id = null;
    line.destination_label = '';
    line.destination_meta = null;
    line.dest_unit_cost = null;
    line.dest_sell_price = null;
    line.cost_unit_manual = false;
  }

  toggleNewProduct(line: any): void {
    line.add_new = !line.add_new;
    if (line.add_new) {
      line.destination_category_id = null;
      line.destination_label = '';
      line.destination_meta = {
        item_code: 'جديد',
        warehouse: '—',
        measurement: '—',
        quantity: 0,
        avg_cost: 0,
        current_unit_cost: 0,
        current_sell_price: 0,
      };
      line.cost_apply_mode = 'overwrite';
      line.cost_unit_manual = false;
      line.dest_sell_price = null;
      this.recalcLineCost(line);
    } else {
      line.new_product_name = '';
      this.clearReceiptDest(line);
    }
  }

  canReceipt(): boolean {
    return this.receiptLines.length > 0;
  }

  totalReceiptQty(): number {
    return this.receiptLines.reduce((s, l) => s + (Number(l.received_qty) || 0), 0);
  }

  totalWasteQty(): number {
    return this.receiptLines.reduce((s, l) => s + (Number(l.waste_qty) || 0), 0);
  }

  totalRemainingQty(): number {
    return this.receiptLines.reduce((s, l) => s + (Number(l.remaining_qty) || 0), 0);
  }

  totalReceiptCost(): number {
    return this.receiptLines.reduce((s, l) => s + this.lineTotalCost(l), 0);
  }

  createReceipt(): void {
    const lines = this.receiptLines.filter(
      (l) => Number(l.received_qty) > 0 || Number(l.waste_qty) > 0,
    );
    if (!lines.length) {
      Swal.fire('تنبيه', 'أدخل كميات الاستلام', 'warning');
      return;
    }
    const missingGood = lines.some((l) => Number(l.received_qty) <= 0);
    if (missingGood) {
      Swal.fire('تنبيه', 'أدخل الكمية المستلمة لكل سطر (الهالك وحده غير كافٍ)', 'warning');
      return;
    }
    const over = lines.some(
      (l) => Number(l.received_qty) + Number(l.waste_qty) > Number(l.at_vendor) + 0.000001,
    );
    if (over) {
      Swal.fire('تنبيه', 'المستلم + الهالك أكبر من الكمية لدى المورد', 'warning');
      return;
    }
    const missingDest = lines.some(
      (l) => !l.add_new && !l.destination_category_id,
    );
    if (missingDest) {
      Swal.fire('تنبيه', 'اختر صنف الاستلام (المنتج المجهز) لكل سطر', 'warning');
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
          damaged_qty: Number(l.waste_qty) || 0,
          received_total_cost: Number(l.received_total_cost) || 0,
          dest_unit_cost: l.dest_unit_cost != null ? Number(l.dest_unit_cost) : null,
          dest_sell_price: null,
          cost_apply_mode: l.cost_apply_mode || 'weighted_average',
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
