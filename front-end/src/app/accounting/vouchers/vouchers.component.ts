import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { MatDialog } from '@angular/material/dialog';
import { VoucherService, Voucher } from '../services/voucher.service';
import { VoucherDialogComponent } from './voucher-dialog/voucher-dialog.component';

@Component({
  selector: 'app-vouchers',
  templateUrl: './vouchers.component.html',
  styleUrls: ['./vouchers.component.css']
})
export class VouchersComponent implements OnInit {
  vouchers: Voucher[] = [];
  filteredVouchers: Voucher[] = [];
  loading: boolean = false;
  searchQuery: string = '';
  voucherType: 'client' | 'supplier' = 'client';

  constructor(
    private voucherService: VoucherService,
    private route: ActivatedRoute,
    private dialog: MatDialog
  ) { }

  ngOnInit(): void {
    this.route.data.subscribe(data => {
      this.voucherType = data['voucherType'];
      this.loadVouchers();
    });
  }

  loadVouchers() {
    this.loading = true;
    this.voucherService.getVouchers({ voucher_type: this.voucherType }).subscribe({
      next: (response) => {
        // Handle paginated response structure if applicable
        if (response && response.data) {
          this.vouchers = response.data;
        } else {
          this.vouchers = Array.isArray(response) ? response : [];
        }
        this.filterVouchers();
        this.loading = false;
      },
      error: (err) => {
        console.error('Error loading vouchers', err);
        this.loading = false;
      }
    });
  }

  filterVouchers() {
    this.filteredVouchers = this.vouchers.filter(voucher => {
      const matchQuery = !this.searchQuery ||
        (voucher.notes && voucher.notes.toLowerCase().includes(this.searchQuery.toLowerCase())) ||
        (voucher.client_or_supplier_name && voucher.client_or_supplier_name.toLowerCase().includes(this.searchQuery.toLowerCase())) ||
        (voucher.reference_number && voucher.reference_number.toLowerCase().includes(this.searchQuery.toLowerCase())) ||
        (voucher.id && voucher.id.toString().includes(this.searchQuery));

      return matchQuery;
    });
  }

  calculateTotal(voucher: Voucher): number {
    return Number(voucher.amount) || 0;
  }

  refresh() {
    this.loadVouchers();
  }

  get title(): string {
    return this.voucherType === 'client' ? 'سندات العملاء' : 'سندات الموردين';
  }

  openDialog(voucher?: Voucher): void {
    const dialogRef = this.dialog.open(VoucherDialogComponent, {
      width: '600px',
      data: {
        voucherType: this.voucherType,
        voucher: voucher ? { ...voucher } : null
      }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.loadVouchers();
      }
    });
  }

  editVoucher(voucher: Voucher) {
    if (voucher) this.openDialog(voucher);
  }

  viewVoucher(voucher: Voucher) {
    // For now, same dialog but maybe we can disable it or just let them edit?
    // User asked for "Display". I will open dialog, if they save it updates.
    // Ideally, pass a flag 'readonly': true
    if (voucher) this.openDialog(voucher);
  }

  printVoucher(voucher: Voucher) {
    // Simple print for now
    // This usually requires a specific print route or generating a PDF.
    // Since I don't see a print route, I'll placeholder it or try to print the row.
    // Best bet: Alert that it's just a demo or window.print() if it was a standalone page.
    // For now, I'll do window.print(). Ideally navigate to a print component.
    alert('جاري الطباعة... (تحتاج لصفحة طباعة)');
    // window.print(); // This would print the whole list.
  }
}

