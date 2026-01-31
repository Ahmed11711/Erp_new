import { Component, OnInit } from '@angular/core';
import { SafeService } from '../../../accounting/services/safe.service';
import { VoucherService } from '../../../accounting/services/voucher.service';

@Component({
    selector: 'app-cash-receive-from-client',
    templateUrl: './receive-from-client.component.html',
    styleUrls: ['./receive-from-client.component.css']
})
export class CashReceiveFromClientComponent implements OnInit {
    safes: any[] = [];
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
        private safeService: SafeService,
        private voucherService: VoucherService
    ) { }

    ngOnInit(): void {
        this.safeService.getAll().subscribe((res: any) => {
            this.safes = res.data || res;
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
