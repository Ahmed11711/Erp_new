import { Component, OnInit } from '@angular/core';
import { SafeService } from '../../../accounting/services/safe.service';
import { VoucherService } from '../../../accounting/services/voucher.service';

@Component({
    selector: 'app-cash-receive-from-supplier',
    templateUrl: './receive-from-supplier.component.html',
    styleUrls: ['./receive-from-supplier.component.css']
})
export class CashReceiveFromSupplierComponent implements OnInit {
    safes: any[] = [];
    suppliers: any[] = [];

    voucher: any = {
        date: new Date().toISOString().split('T')[0],
        type: 'receipt', // Receiving money (loan/advance) FROM Supplier into Safe
        voucher_type: 'supplier',
        account_id: null,
        supplier_id: null,
        amount: 0,
        notes: ''
    };

    constructor(
        private safeService: SafeService,
        private voucherService: VoucherService
    ) { }

    ngOnInit(): void {
        this.safeService.getAll().subscribe((res: any) => {
            this.safes = res.data || res;
        });
        this.voucherService.getSuppliers().subscribe((res: any) => {
            this.suppliers = res.data || res;
        });
    }

    save() {
        if (!this.voucher.account_id || !this.voucher.supplier_id || this.voucher.amount <= 0) {
            alert('يرجى ملء جميع الحقول المطلوبة');
            return;
        }
        this.voucherService.createVoucher(this.voucher).subscribe({
            next: (res) => {
                alert('تم الحفظ بنجاح');
                this.voucher = {
                    date: new Date().toISOString().split('T')[0],
                    type: 'receipt',
                    voucher_type: 'supplier',
                    account_id: null,
                    supplier_id: null,
                    amount: 0,
                    notes: ''
                };
            },
            error: (err) => {
                alert(err.error?.message || 'Error');
            }
        });
    }
}
