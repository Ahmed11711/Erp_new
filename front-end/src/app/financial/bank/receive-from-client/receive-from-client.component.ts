import { Component, OnInit } from '@angular/core';
import { BankService } from '../../../accounting/services/bank.service';
import { VoucherService } from '../../../accounting/services/voucher.service';

@Component({
    selector: 'app-receive-from-client',
    templateUrl: './receive-from-client.component.html',
    styleUrls: ['./receive-from-client.component.css']
})
export class ReceiveFromClientComponent implements OnInit {
    banks: any[] = [];
    clients: any[] = [];

    voucher: any = {
        date: new Date().toISOString().split('T')[0],
        type: 'receipt',
        voucher_type: 'client',
        account_id: null,
        client_id: null,
        amount: 0,
        notes: ''
    };

    constructor(
        private bankService: BankService,
        private voucherService: VoucherService
    ) { }

    ngOnInit(): void {
        this.bankService.getAll().subscribe((res: any) => {
            // Handle pagination or array
            this.banks = res.data || res;
            // Filter out banks without asset_id? Or controller ensures it.
        });
        this.voucherService.getClients().subscribe((res: any) => {
            this.clients = res.data || res;
        });
    }

    save() {
        if (!this.voucher.account_id || !this.voucher.client_id || this.voucher.amount <= 0) {
            alert('يرجى ملء جميع الحقول المطلوبة');
            return;
        }
        this.voucherService.createVoucher(this.voucher).subscribe({
            next: (res) => {
                alert('تم الحفظ بنجاح');
                this.voucher = {
                    date: new Date().toISOString().split('T')[0],
                    type: 'receipt',
                    voucher_type: 'client',
                    account_id: null,
                    client_id: null,
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
