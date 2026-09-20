import { Component, OnInit } from '@angular/core';
import { PaymentSourcesService, PaymentSourceItem } from '../../../accounting/services/payment-sources.service';
import { VoucherService } from '../../../accounting/services/voucher.service';
import { ToastService } from '../../../shared/toast/toast.service';

@Component({
    selector: 'app-cash-pay-to-supplier',
    templateUrl: './pay-to-supplier.component.html',
    styleUrls: ['./pay-to-supplier.component.css']
})
export class CashPayToSupplierComponent implements OnInit {
    paymentPlace: 'safe' | 'bank' | 'service_account' = 'safe';
    selectedSourceId: number | null = null;

    safes: PaymentSourceItem[] = [];
    banks: PaymentSourceItem[] = [];
    serviceAccounts: PaymentSourceItem[] = [];
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
        private paymentSourcesService: PaymentSourcesService,
        private voucherService: VoucherService,
        private toast: ToastService
    ) { }

    ngOnInit(): void {
        this.paymentSourcesService.getPaymentSources().subscribe({
            next: (res) => {
                this.safes = res.safes || [];
                this.banks = res.banks || [];
                this.serviceAccounts = res.service_accounts || [];
            },
            error: () => this.toast.error('تعذر تحميل مصادر الدفع')
        });
        this.voucherService.getSuppliers().subscribe((res: any) => {
            this.suppliers = res.data || res;
        });
    }

    onPlaceChange(): void {
        this.selectedSourceId = null;
        this.voucher.account_id = null;
    }

    get selectedSupplier(): any | null {
        if (!this.voucher.supplier_id) {
            return null;
        }
        return this.suppliers.find((s) => s.id === this.voucher.supplier_id) ?? null;
    }

    private resolveAccountId(): number | null {
        const list =
            this.paymentPlace === 'safe'
                ? this.safes
                : this.paymentPlace === 'bank'
                    ? this.banks
                    : this.serviceAccounts;
        const item = list.find((x) => x.id === this.selectedSourceId);
        return item?.account_id ?? null;
    }

    save() {
        const accountId = this.resolveAccountId();
        this.voucher.account_id = accountId;

        if (!accountId) {
            this.toast.warning('اختر خزينة أو بنك أو حساب خدمي مرتبطاً بحساب شجري');
            return;
        }
        if (!this.voucher.supplier_id) {
            this.toast.warning('اختر المورد');
            return;
        }
        if (!this.voucher.amount || this.voucher.amount <= 0) {
            this.toast.warning('أدخل مبلغاً صحيحاً');
            return;
        }

        this.voucherService.createVoucher(this.voucher).subscribe({
            next: () => {
                this.toast.success('تم الحفظ بنجاح');
                this.voucher = {
                    date: new Date().toISOString().split('T')[0],
                    type: 'payment',
                    voucher_type: 'supplier',
                    account_id: null,
                    supplier_id: null,
                    amount: 0,
                    notes: ''
                };
                this.selectedSourceId = null;
            },
            error: (err) => {
                this.toast.error(err.error?.message || 'حدث خطأ أثناء الحفظ');
            }
        });
    }
}
