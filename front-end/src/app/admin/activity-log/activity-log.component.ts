import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl } from '@angular/forms';
import { UserService } from 'src/app/manage-system/services/user.service';
import { OrderService } from 'src/app/shipping/services/order.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';

@Component({
  selector: 'app-activity-log',
  templateUrl: './activity-log.component.html',
  styleUrls: ['./activity-log.component.css']
})
export class ActivityLogComponent implements OnInit {

  dataList: any[] = [];
  userdata: any[] = [];
  modules: string[] = [];
  loadError = '';

  length = 0;
  pageSize = 25;
  page = 0;
  pageSizeOptions = [25, 50, 100, 200];

  methods = [
    { value: 'POST', label: 'إضافة' },
    { value: 'PUT', label: 'تعديل' },
    { value: 'PATCH', label: 'تعديل' },
    { value: 'DELETE', label: 'حذف' },
  ];

  /** خريطة المورد (segment من مسار الـ API) إلى شاشة العنصر في الواجهة */
  private readonly entityRoutes: Record<string, string> = {
    orders: '/dashboard/shipping/editorder',
    order: '/dashboard/shipping/editorder',
    expenses: '/dashboard/financial/editexpense',
    expense: '/dashboard/financial/editexpense',
    purchases: '/dashboard/purchases/invoice',
    purchase: '/dashboard/purchases/invoice',
    suppliers: '/dashboard/suppliers/supplier_details',
    supplier: '/dashboard/suppliers/supplier_details',
    categories: '/dashboard/categories/edit_category',
    category: '/dashboard/categories/edit_category',
    recipe: '/dashboard/manufacturing/editrecipe',
    recipes: '/dashboard/manufacturing/editrecipe',
    processing: '/dashboard/processing/orders',
    shippingcompany: '/dashboard/shipping/shippingcompanydetails',
    leads: '/dashboard/corparates-sales/leads',
  };

  constructor(
    private OrderService: OrderService,
    private userService: UserService,
    public rbac: RbacService,
  ) {}

  get canEditActivity(): boolean {
    return this.rbac.can('system.activity_log.edit');
  }

  entityLink(log: any): any[] | null {
    const id = Number(log?.subject_id ?? 0);
    if (!id) {
      return null;
    }
    const type = String(log?.entity_type ?? '').toLowerCase();
    const base = type ? this.entityRoutes[type] : undefined;
    return base ? [base, id] : null;
  }

  canOpenEntity(log: any): boolean {
    return this.canEditActivity && !!this.entityLink(log);
  }

  form: FormGroup = new FormGroup({
    created_at: new FormControl(''),
    user_id: new FormControl('0'),
    module: new FormControl('0'),
    method: new FormControl(''),
    search: new FormControl(''),
  });

  ngOnInit(): void {
    this.form.valueChanges.subscribe(() => {
      this.page = 0;
      this.getData();
    });

    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const day = today.getDate();
    this.form.patchValue({
      created_at: `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`,
    }, { emitEvent: false });

    this.getData();
    this.getUsers();
    this.getModules();
  }

  getUsers() {
    this.userService.compactDirectory().subscribe((res: any) => {
      this.userdata = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
    });
  }

  getModules() {
    this.OrderService.getActivityModules().subscribe((res: any) => {
      this.modules = Array.isArray(res) ? res : [];
    });
  }

  private buildParams(): Record<string, string | number> {
    const params: Record<string, string | number> = {
      itemsPerPage: this.pageSize,
      page: this.page + 1,
    };

    const createdAt = String(this.form.value.created_at ?? '').trim();
    if (createdAt && createdAt !== '0') {
      params.created_at = createdAt;
    }

    const userId = Number(this.form.value.user_id ?? 0);
    if (userId > 0) {
      params.user_id = userId;
    }

    const module = String(this.form.value.module ?? '').trim();
    if (module && module !== '0') {
      params.module = module;
    }

    const method = String(this.form.value.method ?? '').trim();
    if (method) {
      params.method = method;
    }

    const search = String(this.form.value.search ?? '').trim();
    if (search) {
      params.search = search;
    }

    return params;
  }

  getData() {
    this.loadError = '';
    this.OrderService.getActivityLogs(this.buildParams()).subscribe({
      next: (res: any) => {
        this.dataList = Array.isArray(res?.data) ? res.data : [];
        this.length = Number(res?.total ?? 0);
        this.pageSize = Number(res?.per_page ?? this.pageSize);
      },
      error: (err) => {
        this.dataList = [];
        this.length = 0;
        this.loadError = err?.error?.message || 'تعذّر تحميل سجل النشاط.';
      },
    });
  }

  methodLabel(method: any): string {
    const m = String(method ?? '').toUpperCase();
    switch (m) {
      case 'POST': return 'إضافة';
      case 'PUT':
      case 'PATCH': return 'تعديل';
      case 'DELETE': return 'حذف';
      default: return m || '—';
    }
  }

  methodClass(method: any): string {
    const m = String(method ?? '').toUpperCase();
    switch (m) {
      case 'POST': return 'badge-create';
      case 'PUT':
      case 'PATCH': return 'badge-edit';
      case 'DELETE': return 'badge-delete';
      default: return 'badge-neutral';
    }
  }

  metaPreview(meta: any): string {
    if (!meta) {
      return '';
    }
    try {
      const text = typeof meta === 'string' ? meta : JSON.stringify(meta);
      return text.length > 120 ? text.slice(0, 120) + '…' : text;
    } catch {
      return '';
    }
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.getData();
  }
}
