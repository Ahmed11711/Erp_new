import { Component, OnInit } from '@angular/core';
import { FormControl } from '@angular/forms';
import { CollectionCompanyService } from '../services/collection-company.service';
import { ShippingCompanyService } from '../services/shipping-company.service';
import { TreeAccountService } from 'src/app/accounting/services/tree-account.service';
import Swal from 'sweetalert2';

interface AccountOption {
  id: number;
  label: string;
}

@Component({
  selector: 'app-collection-companies',
  templateUrl: './collection-companies.component.html',
  styleUrls: ['./collection-companies.component.css'],
})
export class CollectionCompaniesComponent implements OnInit {
  list: any[] = [];
  shippingCompanies: any[] = [];
  form: any = {
    name: '',
    phone: '',
    email: '',
    status: 'active',
    linked_shipping_company_id: null,
    receivable_tree_account_id: null,
  };
  editingId: number | null = null;

  unlinkedCount = 0;
  linkingAll = false;
  linkingId: number | null = null;

  receivableAccountCtrl = new FormControl<string | AccountOption>('');
  receivableAccountOptions: AccountOption[] = [];
  filteredReceivableAccounts: AccountOption[] = [];
  readonly autocompleteCap = 80;

  constructor(
    private api: CollectionCompanyService,
    private shippingApi: ShippingCompanyService,
    private treeAccountService: TreeAccountService
  ) {}

  ngOnInit(): void {
    this.load();
    this.loadUnlinkedSummary();
    this.shippingApi.shippingCompanySelect().subscribe((res: any) => {
      this.shippingCompanies = res || [];
    });
    this.loadTreeAccounts();
    this.receivableAccountCtrl.valueChanges.subscribe((v) => this.applyReceivableFilter(typeof v === 'string' ? v : ''));
  }

  load(): void {
    this.api.list().subscribe((res: any) => (this.list = res || []));
  }

  loadUnlinkedSummary(): void {
    this.api.unlinkedSummary().subscribe({
      next: (res: any) => {
        this.unlinkedCount = res?.unlinked_count ?? 0;
      },
    });
  }

  isLinked(row: any): boolean {
    return !!(row?.receivable_tree_account_id || row?.receivable_tree_account?.id);
  }

  linkAllUnlinked(): void {
    if (this.unlinkedCount < 1) {
      return;
    }
    Swal.fire({
      title: 'ربط غير المربوطين',
      text: `سيتم ربط ${this.unlinkedCount} شركة تحصيل غير مربوطة بحسابات تحت «شركات التحصيل» في شجرة الحسابات.`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'متابعة',
      cancelButtonText: 'إلغاء',
    }).then((r) => {
      if (!r.isConfirmed) {
        return;
      }
      this.linkingAll = true;
      this.api.linkUnlinked().subscribe({
        next: (res: any) => {
          this.linkingAll = false;
          Swal.fire('تم', res?.message || 'تم الربط بنجاح', 'success');
          this.loadUnlinkedSummary();
          this.load();
        },
        error: (e) => {
          this.linkingAll = false;
          Swal.fire('خطأ', e?.error?.message || 'فشل الربط', 'error');
        },
      });
    });
  }

  linkOne(row: any): void {
    if (this.isLinked(row)) {
      return;
    }
    this.linkingId = row.id;
    this.api.linkAccount(row.id).subscribe({
      next: (res: any) => {
        this.linkingId = null;
        const code = res?.receivable_tree_account?.code ? ` (${res.receivable_tree_account.code})` : '';
        Swal.fire('تم', `تم ربط ${row.name} بالحساب${code}`, 'success');
        this.loadUnlinkedSummary();
        this.load();
      },
      error: (e) => {
        this.linkingId = null;
        Swal.fire('خطأ', e?.error?.message || 'فشل الربط', 'error');
      },
    });
  }

  private loadTreeAccounts(): void {
    this.treeAccountService.getAll().subscribe({
      next: (res) => {
        const raw = (res as any)?.data;
        const arr = Array.isArray(raw) ? raw : raw ? [raw] : [];
        const flat = this.flattenAccounts(arr);
        if (flat.length > 0) {
          this.receivableAccountOptions = flat;
          this.applyReceivableFilter('');
          return;
        }
        this.treeAccountService.getTree().subscribe({
          next: (treeRes: any) => {
            const t = treeRes?.data ?? treeRes;
            const tArr = Array.isArray(t) ? t : t ? [t] : [];
            this.receivableAccountOptions = this.flattenAccounts(tArr);
            this.applyReceivableFilter('');
          },
        });
      },
    });
  }

  private flattenAccounts(nodes: any[]): AccountOption[] {
    const out: AccountOption[] = [];
    const walk = (list: any[]) => {
      for (const n of list || []) {
        if (n?.id != null && n?.name) {
          const code = n.code != null && n.code !== '' ? String(n.code) : '';
          out.push({ id: Number(n.id), label: code ? `${code} — ${n.name}` : String(n.name) });
        }
        if (Array.isArray(n?.children) && n.children.length) walk(n.children);
      }
    };
    walk(nodes);
    return out.sort((a, b) => a.label.localeCompare(b.label, 'ar'));
  }

  displayReceivableAccount = (v: AccountOption | string | null): string => {
    if (!v) return '';
    return typeof v === 'string' ? v : v.label;
  };

  onReceivableAccountSelected(ev: any): void {
    const acc = ev?.option?.value as AccountOption;
    if (acc?.id) {
      this.form.receivable_tree_account_id = acc.id;
      this.receivableAccountCtrl.setValue(acc, { emitEvent: false });
    }
  }

  private applyReceivableFilter(term: string): void {
    const q = (term || '').toString().trim().toLowerCase();
    let list = this.receivableAccountOptions;
    if (q) {
      list = list.filter((a) => a.label.toLowerCase().includes(q));
    }
    this.filteredReceivableAccounts = list.slice(0, this.autocompleteCap);
  }

  edit(row: any): void {
    this.editingId = row.id;
    setTimeout(() => document.querySelector('.cc-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    this.form = {
      name: row.name,
      phone: row.phone || '',
      email: row.email || '',
      status: row.status || 'active',
      linked_shipping_company_id: row.linked_shipping_company_id || null,
      receivable_tree_account_id: row.receivable_tree_account_id || null,
    };
    const acc = row.receivable_tree_account;
    if (acc?.id) {
      const code = acc.code ? `${acc.code} — ` : '';
      this.receivableAccountCtrl.setValue({ id: acc.id, label: `${code}${acc.name}` }, { emitEvent: false });
    } else {
      this.receivableAccountCtrl.setValue('', { emitEvent: false });
    }
  }

  reset(): void {
    this.editingId = null;
    this.form = {
      name: '',
      phone: '',
      email: '',
      status: 'active',
      linked_shipping_company_id: null,
      receivable_tree_account_id: null,
    };
    this.receivableAccountCtrl.setValue('', { emitEvent: false });
  }

  save(): void {
    if (!this.form.name?.trim()) {
      Swal.fire('تنبيه', 'الاسم مطلوب', 'warning');
      return;
    }
    const payload = { ...this.form };
    if (!payload.receivable_tree_account_id) {
      payload.receivable_tree_account_id = null;
    }
    const req = this.editingId ? this.api.update(this.editingId, payload) : this.api.create(payload);
    req.subscribe({
      next: () => {
        Swal.fire('تم', 'حُفظت البيانات', 'success');
        this.reset();
        this.loadUnlinkedSummary();
        this.load();
      },
      error: (e) => Swal.fire('خطأ', e?.error?.message || 'فشل الحفظ', 'error'),
    });
  }

  remove(id: number): void {
    Swal.fire({
      title: 'حذف شركة التحصيل؟',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم',
      cancelButtonText: 'لا',
    }).then((r) => {
      if (!r.isConfirmed) return;
      this.api.delete(id).subscribe({
        next: () => {
          this.load();
          Swal.fire('تم', 'تم الحذف', 'success');
        },
        error: (e) => Swal.fire('خطأ', e?.error?.message || 'فشل الحذف', 'error'),
      });
    });
  }

  // --- Reconciliation ---
  reconcilePanel = false;
  reconcileCompanyId: number | null = null;
  reconcileCompanyName = '';
  reconcileDateFrom = '';
  reconcileDateTo = '';
  reconcileLoading = false;
  reconcileRunning = false;
  reconcileOrderCount: number | null = null;
  reconcileOrders: any[] = [];
  reconcileResult: any = null;

  openReconcilePanel(company: any): void {
    this.reconcilePanel = true;
    this.reconcileCompanyId = company.id;
    this.reconcileCompanyName = company.name;
    this.reconcileDateFrom = '';
    this.reconcileDateTo = '';
    this.reconcileOrderCount = null;
    this.reconcileOrders = [];
    this.reconcileResult = null;
    this.reconcileLoading = false;
    this.reconcileRunning = false;
    setTimeout(() => {
      document.querySelector('.reconcile-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 50);
  }

  closeReconcilePanel(): void {
    this.reconcilePanel = false;
    this.reconcileCompanyId = null;
  }

  onReconcileDateChange(): void {
    this.reconcileOrderCount = null;
    this.reconcileOrders = [];
    this.reconcileResult = null;
  }

  loadReconcileOrders(): void {
    if (!this.reconcileCompanyId || !this.reconcileDateFrom || !this.reconcileDateTo) return;
    this.reconcileLoading = true;
    this.reconcileResult = null;

    this.api.reconcileOrders(this.reconcileCompanyId, this.reconcileDateFrom, this.reconcileDateTo).subscribe({
      next: (res: any) => {
        this.reconcileLoading = false;
        const orders = res?.orders || [];
        this.reconcileOrders = orders.map((o: any) => ({ ...o, selected: true }));
        this.reconcileOrderCount = orders.length;
      },
      error: (err: any) => {
        this.reconcileLoading = false;
        Swal.fire('خطأ', err?.error?.message || 'فشل تحميل الطلبات', 'error');
      },
    });
  }

  selectAllReconcileOrders(): void {
    this.reconcileOrders.forEach(o => o.selected = true);
  }

  deselectAllReconcileOrders(): void {
    this.reconcileOrders.forEach(o => o.selected = false);
  }

  selectedReconcileCount(): number {
    return this.reconcileOrders.filter(o => o.selected).length;
  }

  executeReconcile(): void {
    if (!this.reconcileCompanyId || !this.reconcileDateFrom || !this.reconcileDateTo) return;

    const selectedIds = this.reconcileOrders.filter(o => o.selected).map(o => o.id);
    const allSelected = selectedIds.length === this.reconcileOrders.length;
    const orderIds = allSelected ? undefined : selectedIds;

    const count = allSelected ? this.reconcileOrders.length : selectedIds.length;
    if (count === 0 && this.reconcileOrders.length > 0) {
      Swal.fire('تنبيه', 'اختر طلبات أولاً', 'warning');
      return;
    }

    Swal.fire({
      title: 'إعادة حساب المديونيات',
      text: `سيتم إعادة حساب مديونيات «${this.reconcileCompanyName}» لعدد ${count || 'جميع'} طلب. المتابعة؟`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'متابعة',
      cancelButtonText: 'إلغاء',
    }).then((r) => {
      if (!r.isConfirmed) return;

      this.reconcileRunning = true;
      this.reconcileResult = null;

      this.api.reconcileReceivables(
        this.reconcileCompanyId!,
        this.reconcileDateFrom,
        this.reconcileDateTo,
        orderIds
      ).subscribe({
        next: (res: any) => {
          this.reconcileRunning = false;
          this.reconcileResult = res;
          if (res?.success) {
            this.load();
          }
        },
        error: (err: any) => {
          this.reconcileRunning = false;
          this.reconcileResult = { success: false, message: err?.error?.message || 'فشلت العملية' };
        },
      });
    });
  }
}
