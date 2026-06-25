import { Component, OnInit, ViewChild } from '@angular/core';
import { MatPaginator } from '@angular/material/paginator';
import { MatDialog } from '@angular/material/dialog';
import { SuppliersService } from '../services/suppliers.service';
import { TypesService } from '../services/types.service';
import { DialogPayMoneyForSupplierComponent } from '../dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import { DialogEditSupplierComponent } from '../dialog-edit-supplier/dialog-edit-supplier.component';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-list-suppliers',
  templateUrl: './list-suppliers.component.html',
  styleUrls: ['./list-suppliers.component.css']
})
export class ListSuppliersComponent implements OnInit {
  types: any[] = [];
  suppliersData: any[] = [];
  phone = '';
  supplier_name = '';
  supplier_type = '';

  length = 0;
  pageSize = 5;
  page = 0;

  totalBalance = 0;
  selectedStatus: 'own' | 'want' | null = null;

  pageSizeOptions = [5, 10, 15, 50];
  selectedIds = new Set<number>();
  menuItem: { id: number; supplier_name?: string; balance?: number } | null = null;

  @ViewChild('listSupp', { static: false }) listSupp!: any;
  @ViewChild(MatPaginator, { static: false }) paginator!: MatPaginator;

  constructor(
    private tpes: TypesService,
    private suppliers: SuppliersService,
    private dialog: MatDialog,
    private rbac: RbacService,
  ) {}

  ngOnInit(): void {
    this.tpes.getTypes().subscribe((res: any) => {
      this.types = res;
    });
    this.loadData();
  }

  supplierTypeLabel(item: any): string {
    const rel = item?.supplierType ?? item?.supplier_type;
    if (rel && typeof rel === 'object') {
      return rel.supplier_type ?? '';
    }
    return '';
  }

  private buildSearchParams(): Record<string, string> {
    const param: Record<string, string> = {};
    if (this.phone.trim()) {
      param['supplier_phone'] = this.phone.trim();
    }
    if (this.supplier_name.trim()) {
      param['supplier_name'] = this.supplier_name.trim();
    }
    if (this.supplier_type) {
      param['supplier_type'] = this.supplier_type;
    }
    if (this.selectedStatus) {
      param['status'] = this.selectedStatus;
    }
    return param;
  }

  loadData(): void {
    this.suppliers.searchSuppliers(this.pageSize, this.page + 1, this.buildSearchParams()).subscribe({
      next: (data: any) => {
        this.suppliersData = Array.isArray(data?.suppliers?.data) ? data.suppliers.data : [];
        this.length = typeof data?.suppliers?.total === 'number' ? data.suppliers.total : 0;
        if (typeof data?.suppliers?.per_page === 'number') {
          this.pageSize = data.suppliers.per_page;
        }
        this.totalBalance = Number(data?.sum_of_balance ?? 0);
        this.pruneSelection();
      },
      error: () => {
        this.suppliersData = [];
        this.length = 0;
        this.totalBalance = 0;
      },
    });
  }

  onPageChange(event: any): void {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.loadData();
  }

  onTypeChange(event: Event): void {
    this.supplier_type = (event.target as HTMLSelectElement).value;
    this.runSearch();
  }

  onPhonechanges(event: Event): void {
    this.phone = (event.target as HTMLInputElement).value;
    this.runSearch();
  }

  onSuppliernamechanges(event: Event): void {
    this.supplier_name = (event.target as HTMLInputElement).value;
    this.runSearch();
  }

  runSearch(): void {
    this.page = 0;
    this.paginator?.firstPage();
    this.loadData();
  }

  private pruneSelection(): void {
    const visible = new Set((this.suppliersData ?? []).map((item: { id: number }) => item.id));
    Array.from(this.selectedIds).forEach((id) => {
      if (!visible.has(id)) {
        this.selectedIds.delete(id);
      }
    });
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

  get allSelected(): boolean {
    const rows = this.suppliersData ?? [];
    return rows.length > 0 && rows.every((item: { id: number }) => this.selectedIds.has(item.id));
  }

  get someSelected(): boolean {
    const rows = this.suppliersData ?? [];
    const count = rows.filter((item: { id: number }) => this.selectedIds.has(item.id)).length;
    return count > 0 && count < rows.length;
  }

  toggleSelectAll(checked: boolean): void {
    if (checked) {
      (this.suppliersData ?? []).forEach((item: { id: number }) => this.selectedIds.add(item.id));
    } else {
      (this.suppliersData ?? []).forEach((item: { id: number }) => this.selectedIds.delete(item.id));
    }
  }

  deleteSelectedSuppliers(): void {
    if (!this.canDeleteSupplier()) {
      return;
    }

    const ids = Array.from(this.selectedIds);
    if (ids.length === 0) {
      return;
    }

    const withBalance = (this.suppliersData ?? []).filter(
      (item: { id: number; balance?: number }) =>
        ids.includes(item.id) && Math.abs(Number(item.balance ?? 0)) > 0.000001
    );
    const balanceWarning = withBalance.length > 0
      ? `<p class="text-warning mt-2"><strong>تنبيه:</strong> ${withBalance.length} مورد لهم أرصدة — سيُحذفون مع حساباتهم في الشجرة.</p>`
      : '';

    Swal.fire({
      title: 'تأكيد حذف المحدد؟',
      html: `سيتم حذف <strong>${ids.length}</strong> مورد وحساباتهم من شجرة الحسابات.${balanceWarning}`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم، احذف',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      this.suppliers.deleteSuppliers(ids).subscribe({
        next: (res: any) => {
          const results = res?.results ?? {};
          const deleted = results.deleted?.length ?? 0;
          const failed = results.failed ?? [];

          ids.forEach((id) => this.selectedIds.delete(id));
          this.loadData();

          if (failed.length > 0) {
            const lines = failed
              .map((f: { id: number; message: string }) => `#${f.id}: ${f.message}`)
              .join('<br>');
            Swal.fire({
              icon: 'warning',
              title: 'اكتمل جزئياً',
              html: `تم حذف: ${deleted} | فشل: ${failed.length}<br><small>${lines}</small>`,
            });
            return;
          }

          Swal.fire({ icon: 'success', title: 'تم الحذف', timer: 2500, showConfirmButton: false });
        },
        error: (err) => {
          const msg =
            err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر تنفيذ الحذف';
          Swal.fire({ icon: 'error', title: 'فشل الحذف', text: String(msg) });
        },
      });
    });
  }

  clrSearch(): void {
    this.selectedStatus = null;
    this.phone = '';
    this.supplier_name = '';
    this.supplier_type = '';
    this.page = 0;
    this.totalBalance = 0;
    this.paginator?.firstPage();
    this.listSupp?.resetForm();
    this.loadData();
  }

  openDialog(supplier: { id: number; supplier_name?: string; balance?: number }): void {
    const dialogRef = this.dialog.open(DialogPayMoneyForSupplierComponent, {
      width: '420px',
      maxWidth: '95vw',
      panelClass: 'supplier-pay-dialog',
      data: { supplier, refreshData: () => this.loadData() },
    });

    dialogRef.afterClosed().subscribe();
  }

  openEditDialog(supplier: { id: number }): void {
    this.dialog.open(DialogEditSupplierComponent, {
      width: '480px',
      maxWidth: '95vw',
      panelClass: 'supplier-edit-dialog-panel',
      data: { supplierId: supplier.id, refreshData: () => this.loadData() },
    });
  }

  deleteSupplier(supplier: { id: number; supplier_name?: string; balance?: number }): void {
    if (!this.canDeleteSupplier()) {
      return;
    }

    const balance = Number(supplier.balance ?? 0);
    const balanceWarning = Math.abs(balance) > 0.000001
      ? `<p class="text-warning mt-2"><strong>تنبيه:</strong> رصيد المورد <strong dir="ltr">${balance}</strong> — سيُحذف مع حسابه في الشجرة.</p>`
      : '';

    Swal.fire({
      title: 'تأكيد الحذف؟',
      html: `هل تريد حذف المورد <strong>${supplier.supplier_name ?? ''}</strong> وحسابه من شجرة الحسابات؟${balanceWarning}`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم، احذف',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      this.suppliers.deleteSupplier(supplier.id).subscribe({
        next: () => {
          this.loadData();
          Swal.fire({ icon: 'success', title: 'تم الحذف', timer: 2500, showConfirmButton: false });
        },
        error: (err) => {
          const msg =
            err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر تنفيذ الحذف';
          Swal.fire({ icon: 'error', title: 'فشل الحذف', text: String(msg) });
        },
      });
    });
  }

  canDeleteSupplier(): boolean {
    return this.rbac.can('suppliers.delete') || this.rbac.can('system.rbac');
  }

  canPurgeAllSuppliers(): boolean {
    return this.rbac.can('suppliers.purge_all') || this.rbac.can('system.rbac');
  }

  purgeAllSuppliers(): void {
    if (!this.canPurgeAllSuppliers()) {
      return;
    }

    this.suppliers.purgePreview().subscribe({
      next: (preview: { supplier_count: number; purchase_count: number; processing_count: number }) => {
        const lines = [
          `عدد الموردين: <strong>${preview.supplier_count}</strong>`,
          `فواتير المشتريات: <strong>${preview.purchase_count}</strong>`,
        ];
        if (preview.processing_count > 0) {
          lines.push(`أوامر التشغيل الخارجي: <strong>${preview.processing_count}</strong>`);
        }

        Swal.fire({
          title: 'حذف جميع الموردين؟',
          html: `
          <p>سيتم حذف جميع الموردين وحساباتهم في شجرة الحسابات، وفواتير المشتريات والسجلات المرتبطة.</p>
          <ul style="text-align:right;list-style:none;padding:0">${lines.map((l) => `<li>${l}</li>`).join('')}</ul>
          <p class="text-danger"><strong>هذه العملية لا يمكن التراجع عنها.</strong></p>
        `,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'نعم، احذف الكل',
          cancelButtonText: 'إلغاء',
          confirmButtonColor: '#d33',
        }).then((result) => {
          if (!result.isConfirmed) {
            return;
          }

          this.suppliers.purgeAll().subscribe({
            next: () => {
              this.loadData();
              Swal.fire({ icon: 'success', title: 'تم حذف جميع الموردين', timer: 3000, showConfirmButton: false });
            },
            error: (err) => {
              const msg =
                err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر تنفيذ الحذف';
              Swal.fire({ icon: 'error', title: 'فشل الحذف', text: String(msg) });
            },
          });
        });
      },
      error: (err) => {
        const msg =
          err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر تحميل المعاينة';
        Swal.fire({ icon: 'error', title: 'خطأ', text: String(msg) });
      },
    });
  }
}
