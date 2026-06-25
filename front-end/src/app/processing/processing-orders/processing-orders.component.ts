import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ProcessingService } from '../services/processing.service';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/env/env';
import { ShippingCompanyService } from 'src/app/shipping/services/shipping-company.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-processing-orders',
  templateUrl: './processing-orders.component.html',
  styleUrls: ['./processing-orders.component.css'],
})
export class ProcessingOrdersComponent implements OnInit {
  orders: any[] = [];
  vendors: any[] = [];
  categories: any[] = [];
  representatives: any[] = [];
  dispatchTypes: { value: string; label: string }[] = [];
  statusLabels: Record<string, string> = {};
  loading = true;
  vendorsLoading = false;
  vendorsError: string | null = null;
  saving = false;
  nextDispatchNumber: string | null = null;

  showForm = false;
  selectedVendor: any = null;
  selectedRepresentative: any = null;
  vendorKeyword = 'supplier_name';
  categoryKeyword = 'category_name';
  representativeKeyword = 'name';

  form: any = {
    supplier_id: null,
    dispatch_date: new Date().toISOString().slice(0, 10),
    dispatch_type: 'goods_to_supplier',
    representative_type: null as 'internal' | 'external' | null,
    shipping_company_id: null,
    external_representative_name: '',
    notes: '',
    lines: [{ category_id: null, ordered_qty: null, expected_service_amount: 0 }],
  };

  constructor(
    private api: ProcessingService,
    private http: HttpClient,
    private router: Router,
    private shippingCompanyService: ShippingCompanyService,
  ) {}

  ngOnInit(): void {
    this.api.meta().subscribe((m) => {
      this.statusLabels = m?.order_statuses || {};
      this.dispatchTypes = m?.dispatch_types || [
        { value: 'goods_to_supplier', label: 'صرف بضاعة لمورد' },
        { value: 'amanat', label: 'صرف أمانات' },
        { value: 'custody', label: 'صرف عهدة' },
      ];
      if (m?.next_dispatch_number) {
        this.nextDispatchNumber = m.next_dispatch_number;
      }
    });
    this.loadVendors();
    this.loadRepresentatives();
    this.loadOrders();
    this.loadRawCategories();
  }

  loadOrders(): void {
    this.loading = true;
    this.api.listOrders({ itemsPerPage: 50 }).subscribe({
      next: (res: any) => {
        this.orders = res?.data || res || [];
        this.loading = false;
      },
      error: () => (this.loading = false),
    });
  }

  loadVendors(): void {
    this.vendorsLoading = true;
    this.vendorsError = null;
    this.api.listVendors().subscribe({
      next: (rows) => {
        this.vendors = this.normalizeVendors(rows);
        this.vendorsLoading = false;
      },
      error: (e) => {
        this.vendors = [];
        this.vendorsLoading = false;
        this.vendorsError = e?.error?.message || 'تعذر تحميل قائمة الموردين.';
      },
    });
  }

  loadRepresentatives(): void {
    this.shippingCompanyService.shippingCompanySelect().subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : [];
        this.representatives = list
          .filter((item: any) => String(item?.type ?? '') === 'مندوب' && String(item?.name ?? '').trim() !== '')
          .sort((a: any, b: any) => String(a?.name ?? '').localeCompare(String(b?.name ?? ''), 'ar'));
        this.applyDefaultRepresentative();
      },
      error: () => {
        this.representatives = [];
      },
    });
  }

  private applyDefaultRepresentative(): void {
    if (this.selectedRepresentative || !this.representatives.length || !this.showForm) {
      return;
    }
    const rep = this.representatives[0];
    this.onRepresentativeSelected(rep);
  }

  private normalizeVendors(rows: unknown): any[] {
    if (Array.isArray(rows)) {
      return rows;
    }
    if (rows && typeof rows === 'object') {
      const obj = rows as Record<string, unknown>;
      if (Array.isArray(obj['data'])) {
        return obj['data'] as any[];
      }
    }
    return [];
  }

  loadRawCategories(): void {
    this.http
      .get<any>(`${environment.Url}/categories/search`, {
        params: { itemsPerPage: 500, warehouse: 'مخزن مواد خام' },
      })
      .subscribe({
        next: (res) => {
          this.categories = (res?.data || []).map((c: any) => ({
            ...c,
            category_name: c.category_name || '',
          }));
        },
      });
  }

  statusClass(s: string): string {
    const map: Record<string, string> = {
      draft: 'st-draft',
      approved: 'st-approved',
      in_progress: 'st-progress',
      partially_received: 'st-partial',
      completed: 'st-done',
      cancelled: 'st-cancel',
    };
    return map[s] || 'st-draft';
  }

  openForm(): void {
    this.selectedVendor = null;
    this.selectedRepresentative = null;
    this.form = {
      supplier_id: null,
      dispatch_date: new Date().toISOString().slice(0, 10),
      dispatch_type: 'goods_to_supplier',
      representative_type: null,
      shipping_company_id: null,
      external_representative_name: '',
      notes: '',
      lines: [this.newLine()],
    };
    this.showForm = true;
    this.loadVendors();
    this.loadNextDispatchNumber();
    this.applyDefaultRepresentative();
  }

  loadNextDispatchNumber(): void {
    this.api.meta().subscribe({
      next: (m) => {
        this.nextDispatchNumber = m?.next_dispatch_number || null;
      },
    });
  }

  newLine() {
    return {
      category_id: null,
      category_label: '',
      ordered_qty: null,
      expected_service_amount: 0,
    };
  }

  onVendorSelected(vendor: any): void {
    if (!vendor?.id) return;
    this.selectedVendor = vendor;
    this.form.supplier_id = vendor.id;
  }

  clearVendor(): void {
    this.selectedVendor = null;
    this.form.supplier_id = null;
  }

  onLineCategorySelected(line: any, item: any): void {
    if (!item?.id) return;
    line.category_id = item.id;
    line.category_label = item.category_name;
  }

  clearLineCategory(line: any): void {
    line.category_id = null;
    line.category_label = '';
  }

  setRepType(type: 'internal' | 'external' | null): void {
    this.form.representative_type = type;
    if (type !== 'internal') {
      this.selectedRepresentative = null;
      this.form.shipping_company_id = null;
    }
    if (type !== 'external') {
      this.form.external_representative_name = '';
    }
  }

  onRepresentativeSelected(rep: any): void {
    if (!rep?.id) return;
    this.selectedRepresentative = rep;
    this.form.shipping_company_id = rep.id;
    this.form.representative_type = 'internal';
  }

  clearRepresentative(): void {
    this.selectedRepresentative = null;
    this.form.shipping_company_id = null;
  }

  closeForm(): void {
    this.showForm = false;
  }

  addLine(): void {
    this.form.lines.push(this.newLine());
  }

  removeLine(i: number): void {
    if (this.form.lines.length > 1) {
      this.form.lines.splice(i, 1);
    }
  }

  totalQty(): number {
    return (this.form.lines || []).reduce(
      (s: number, l: any) => s + (Number(l.ordered_qty) || 0),
      0,
    );
  }

  totalAmount(): number {
    return (this.form.lines || []).reduce(
      (s: number, l: any) => s + (Number(l.expected_service_amount) || 0),
      0,
    );
  }

  dispatchTypeLabel(value: string): string {
    return this.dispatchTypes.find((t) => t.value === value)?.label || value;
  }

  save(): void {
    if (!this.form.supplier_id) {
      Swal.fire('تنبيه', 'اختر المورد (يصرف إلى)', 'warning');
      return;
    }
    if (!this.form.representative_type && this.representatives.length) {
      this.applyDefaultRepresentative();
    }
    if (this.form.representative_type === 'internal' && !this.form.shipping_company_id) {
      Swal.fire('تنبيه', 'اختر مندوباً من قائمة المناديب', 'warning');
      return;
    }
    if (
      this.form.representative_type === 'external' &&
      !String(this.form.external_representative_name || '').trim()
    ) {
      Swal.fire('تنبيه', 'أدخل اسم المندوب الخارجي', 'warning');
      return;
    }

    const validLines = (this.form.lines || []).filter(
      (l: any) => l.category_id && Number(l.ordered_qty) > 0,
    );
    if (!validLines.length) {
      Swal.fire('تنبيه', 'أضف صنفاً واحداً على الأقل مع كمية أكبر من صفر', 'warning');
      return;
    }

    const payload: any = {
      supplier_id: this.form.supplier_id,
      dispatch_date: this.form.dispatch_date,
      dispatch_type: this.form.dispatch_type,
      notes: this.form.notes,
      expected_service_total: this.totalAmount(),
      lines: validLines.map((l: any) => ({
        category_id: l.category_id,
        ordered_qty: l.ordered_qty,
        expected_service_amount: l.expected_service_amount ?? 0,
      })),
    };

    if (this.form.representative_type) {
      payload.representative_type = this.form.representative_type;
      if (this.form.representative_type === 'internal') {
        payload.shipping_company_id = this.form.shipping_company_id;
      } else {
        payload.external_representative_name = this.form.external_representative_name.trim();
      }
    }

    this.saving = true;
    this.api.submitDispatchVoucher(payload).subscribe({
      next: (res) => {
        this.saving = false;
        this.closeForm();
        const dispatchNo = res?.dispatch?.dispatch_number || '';
        Swal.fire(
          'تم',
          dispatchNo
            ? `تم ترحيل إذن الصرف ${dispatchNo} وإضافة المبلغ لذمة المورد`
            : 'تم ترحيل إذن الصرف وإضافة المبلغ لذمة المورد',
          'success',
        );
        this.loadOrders();
        if (res?.order?.id) {
          this.router.navigate(['/dashboard/processing/orders', res.order.id]);
        }
      },
      error: (e) => {
        this.saving = false;
        Swal.fire('خطأ', e?.error?.message || 'فشل الحفظ', 'error');
      },
    });
  }

  openDetail(o: any): void {
    this.router.navigate(['/dashboard/processing/orders', o.id]);
  }

  dispatchNumber(o: any): string {
    const notes = o?.dispatch_notes || o?.dispatchNotes || [];
    return notes.length ? notes[0].dispatch_number : o.order_number;
  }
}
