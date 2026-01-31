import { Component, OnInit } from '@angular/core';
import { BankService } from '../../../accounting/services/bank.service';
import { VoucherService } from '../../../accounting/services/voucher.service';

@Component({
    selector: 'app-pay-to-supplier',
    templateUrl: './pay-to-supplier.component.html',
    styleUrls: ['./pay-to-supplier.component.css']
})
export class PayToSupplierComponent implements OnInit {
    banks: any[] = [];
    suppliers: any[] = [];

    voucher: any = {
        date: new Date().toISOString().split('T')[0],
        type: 'payment',
        voucher_type: 'supplier',
        account_id: null,
        supplier_id: null,
        amount: 0,
        notes: ''
    };

    constructor(
        private bankService: BankService,
        private voucherService: VoucherService
    ) { }

    ngOnInit(): void {
        this.bankService.getAll().subscribe((res: any) => {
            this.banks = res.data || res;
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
                    type: 'payment',
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
