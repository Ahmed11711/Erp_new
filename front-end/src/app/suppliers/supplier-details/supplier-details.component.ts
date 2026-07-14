import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { MatDialog } from '@angular/material/dialog';
import { SuppliersService } from '../services/suppliers.service';
import { DialogPayMoneyForSupplierComponent } from '../dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import { DialogEditSupplierComponent } from '../dialog-edit-supplier/dialog-edit-supplier.component';

@Component({
  selector: 'app-supplier-details',
  templateUrl: './supplier-details.component.html',
  styleUrls: ['./supplier-details.component.css']
})
export class SupplierDetailsComponent {
  id!: any;
  suppliersData: any = [];
  name!: string;
  /** لـ نافذة سداد المورد (خزينة / بنك / حساب خدمي) */
  supplierForPay: { id: number; supplier_name: string; balance: number } | null = null;

  length = 50;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15, 50];

  constructor(
    private supplier: SuppliersService,
    private router: ActivatedRoute,
    private dialog: MatDialog
  ) {
    this.id = this.router.snapshot.paramMap.get('id');
  }

  ngOnInit() {
    this.getData();
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.getData();
  }

  getData() {
    this.supplier.supplierDetails(this.id, this.pageSize, this.page + 1).subscribe((res: any) => {
      this.name = res.name;
      this.supplierForPay = res.supplier ?? null;

      this.suppliersData = res.data.data;
      this.suppliersData.forEach((item: any) => {
        if (item.balance_after != null) {
          item.balance_after = Number(item.balance_after).toFixed(2);
        }
        if (item.balance_before != null) {
          item.balance_before = Number(item.balance_before).toFixed(2);
        }
      });

      this.length = res.data.total;
      this.pageSize = res.data.per_page;
    });
  }

  isPayment(item: any): boolean {
    return item?.entry_kind === 'payment' || !!item?.is_payment
      || String(item?.invoice_number ?? '').startsWith('SP');
  }

  entryKind(item: any): string {
    if (item?.entry_kind) {
      return item.entry_kind;
    }
    if (this.isPayment(item)) {
      return 'payment';
    }
    const ref = String(item?.invoice_number ?? '');
    if (ref.startsWith('PVI') || item?.invoice_type === 'تشغيل خارجي') {
      return 'processing';
    }
    return 'purchase';
  }

  typeBadgeClass(item: any): string {
    const kind = this.entryKind(item);
    if (kind === 'payment') {
      return 'supplier-type-badge--payment';
    }
    if (kind === 'processing') {
      return 'supplier-type-badge--processing';
    }
    return 'supplier-type-badge--purchase';
  }

  rowClass(item: any): string {
    const kind = this.entryKind(item);
    if (kind === 'payment') {
      return 'supplier-row--payment';
    }
    if (kind === 'processing') {
      return 'supplier-row--processing';
    }
    return 'supplier-row--purchase';
  }

  getRouterLink(item: any): any[] | null {
    const kind = this.entryKind(item);
    if (kind === 'purchase' && item?.invoice_id) {
      return ['/dashboard/purchases/invoice', item.invoice_id];
    }
    if (kind === 'processing' && item?.related_order_id) {
      return ['/dashboard/processing/orders', item.related_order_id];
    }
    return null;
  }

  openPayDialog(): void {
    if (!this.supplierForPay) {
      return;
    }
    this.dialog.open(DialogPayMoneyForSupplierComponent, {
      width: '420px',
      maxWidth: '95vw',
      panelClass: 'supplier-pay-dialog',
      data: {
        supplier: this.supplierForPay,
        refreshData: () => this.getData(),
      },
    });
  }

  openEditDialog(): void {
    if (!this.id) {
      return;
    }
    this.dialog.open(DialogEditSupplierComponent, {
      width: '480px',
      maxWidth: '95vw',
      panelClass: 'supplier-edit-dialog-panel',
      data: {
        supplierId: Number(this.id),
        refreshData: () => this.getData(),
      },
    });
  }
}
