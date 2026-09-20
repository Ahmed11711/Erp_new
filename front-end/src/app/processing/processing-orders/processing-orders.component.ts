import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { ProcessingService } from '../services/processing.service';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/env/env';
import { ShippingCompanyService } from 'src/app/shipping/services/shipping-company.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
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
  private categorySearchTimer: ReturnType<typeof setTimeout> | null = null;
  private listSearchTimer: ReturnType<typeof setTimeout> | null = null;

  listSearch = '';
  filterSupplierId: number | null = null;
  filterStatus = '';
  totalOrders = 0;
  deletingId: number | null = null;

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
    readonly rbac: RbacService,
  ) {}

  canDeleteOrder(): boolean {
    return this.rbac.can('processing.delete_order') || this.rbac.can('system.rbac');
  }

  deleteOrder(o: any): void {
    if (!this.canDeleteOrder() || this.deletingId != null) return;

    const label = this.dispatchNumber(o) || o.order_number || `#${o.id}`;
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
      this.deletingId = o.id;
      this.api.deleteOrder(o.id, result.value || undefined).subscribe({
        next: (res) => {
          this.deletingId = null;
          Swal.fire('تم', res?.message || 'تم حذف أمر التشغيل وعكس البيانات', 'success');
          this.loadOrders();
        },
        error: (e) => {
          this.deletingId = null;
          Swal.fire('خطأ', e?.error?.message || 'تعذر حذف أمر التشغيل', 'error');
        },
      });
    });
  }

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
  }

  loadOrders(): void {
    this.loading = true;
    const params: Record<string, string | number> = { itemsPerPage: 50 };
    const q = this.listSearch.trim();
    if (q) params['q'] = q;
    if (this.filterSupplierId) params['supplier_id'] = this.filterSupplierId;
    if (this.filterStatus) params['status'] = this.filterStatus;

    this.api.listOrders(params).subscribe({
      next: (res: any) => {
        this.orders = res?.data || [];
        this.totalOrders = res?.total ?? this.orders.length;
        this.loading = false;
      },
      error: () => (this.loading = false),
    });
  }

  onListSearchInput(): void {
    if (this.listSearchTimer) clearTimeout(this.listSearchTimer);
    this.listSearchTimer = setTimeout(() => this.loadOrders(), 350);
  }

  clearListFilters(): void {
    this.listSearch = '';
    this.filterSupplierId = null;
    this.filterStatus = '';
    this.loadOrders();
  }

  hasListFilters(): boolean {
    return !!(this.listSearch.trim() || this.filterSupplierId || this.filterStatus);
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
      },
      error: () => {
        this.representatives = [];
      },
    });
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

  searchRawCategories(query: string): void {
    const q = (query ?? '').trim();
    if (!q) {
      this.categories = [];
      return;
    }
    this.http
      .get<any>(`${environment.Url}/categories/search`, {
        params: {
          itemsPerPage: 50,
          warehouse: 'مخزن مواد خام',
          category_name: q,
        },
      })
      .subscribe({
        next: (res) => {
          this.categories = (res?.data || []).map((c: any) => ({
            ...c,
            category_name: c.category_name || '',
          }));
        },
        error: () => {
          this.categories = [];
        },
      });
  }

  onCategoryInputChanged(value: string): void {
    if (this.categorySearchTimer) {
      clearTimeout(this.categorySearchTimer);
    }
    const q = (value ?? '').trim();
    if (!q) {
      this.categories = [];
      return;
    }
    this.categorySearchTimer = setTimeout(() => this.searchRawCategories(q), 300);
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
    this.categories = [];
    this.loadVendors();
    this.loadNextDispatchNumber();
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
    line.category_label = this.stripHighlightTags(String(item.category_name ?? ''));
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
      return name.includes(q) || code.includes(q);
    });
  };

  filterVendorSearch = (items: any[], query: string) => {
    const q = (query ?? '').trim().toLowerCase();
    if (!q) return [...items];
    return items.filter((item) => {
      const name = this.stripHighlightTags(String(item.supplier_name ?? '')).toLowerCase();
      return name.includes(q);
    });
  };

  selectedVendorLabel = (item: any): string => {
    if (!item?.supplier_name) return '';
    return this.stripHighlightTags(String(item.supplier_name));
  };

  filterRepresentativeSearch = (items: any[], query: string) => {
    const q = (query ?? '').trim().toLowerCase();
    if (!q) return [...items];
    return items.filter((item) => {
      const name = this.stripHighlightTags(String(item.name ?? '')).toLowerCase();
      return name.includes(q);
    });
  };

  selectedRepresentativeLabel = (item: any): string => {
    if (!item?.name) return '';
    return this.stripHighlightTags(String(item.name));
  };

  save(): void {
    if (!this.form.supplier_id) {
      Swal.fire('تنبيه', 'اختر المورد (يصرف إلى)', 'warning');
      return;
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

  dispatchDate(o: any): string {
    const notes = o?.dispatch_notes || o?.dispatchNotes || [];
    if (notes.length && notes[0].dispatch_date) {
      return notes[0].dispatch_date;
    }
    return o.expected_return_date || o.created_at || '';
  }
}
