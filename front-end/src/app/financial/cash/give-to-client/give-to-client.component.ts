import { Component, OnInit } from '@angular/core';
import { PaymentSourcesService, PaymentSourceItem } from '../../../accounting/services/payment-sources.service';
import { VoucherService } from '../../../accounting/services/voucher.service';
import { ToastService } from '../../../shared/toast/toast.service';
import {
  buildClientVoucherFields,
  CashClientCompanyOption,
  CashClientOptionsResponse,
} from '../cash-client-selection';

@Component({
    selector: 'app-cash-give-to-client',
    templateUrl: './give-to-client.component.html',
    styleUrls: ['./give-to-client.component.css']
})
export class CashGiveToClientComponent implements OnInit {
    paymentPlace: 'safe' | 'bank' | 'service_account' = 'safe';
    selectedSourceId: number | null = null;

    safes: PaymentSourceItem[] = [];
    banks: PaymentSourceItem[] = [];
    serviceAccounts: PaymentSourceItem[] = [];
    clientOptions: CashClientOptionsResponse = { companies: [], individuals: [] };
    selectedClientKey: string | null = null;
    clientSearch = '';

    voucher: any = {
        date: new Date().toISOString().split('T')[0],
        type: 'payment',
        voucher_type: 'client',
        account_id: null,
        client_id: null,
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
        this.voucherService.getClientOptions().subscribe({
            next: (res) => {
                this.clientOptions = {
                    companies: res?.companies || [],
                    individuals: res?.individuals || [],
                };
            },
            error: () => this.toast.error('تعذر تحميل قائمة العملاء'),
        });
    }

    onClientSearchChange(): void {
        this.voucherService.getClientOptions(this.clientSearch).subscribe({
            next: (res) => {
                this.clientOptions = {
                    companies: res?.companies || [],
                    individuals: res?.individuals || [],
                };
            },
        });
    }

    onPlaceChange(): void {
        this.selectedSourceId = null;
        this.voucher.account_id = null;
    }

    get selectedClientCompany(): CashClientCompanyOption | null {
        if (!this.selectedClientKey || !this.selectedClientKey.startsWith('company:')) {
            return null;
        }
        const id = Number(this.selectedClientKey.slice('company:'.length));
        return this.clientOptions.companies.find((c) => c.id === id) ?? null;
    }

    get selectedIndividualPhone(): string | null {
        if (!this.selectedClientKey || !this.selectedClientKey.startsWith('individual:')) {
            return null;
        }
        return this.selectedClientKey.slice('individual:'.length) || null;
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
        if (!this.selectedClientKey) {
            this.toast.warning('اختر العميل');
            return;
        }
        if (!this.voucher.amount || this.voucher.amount <= 0) {
            this.toast.warning('أدخل مبلغاً صحيحاً');
            return;
        }

        const clientFields = buildClientVoucherFields(this.selectedClientKey, this.clientOptions);

        this.voucherService.createVoucher({
            ...this.voucher,
            ...clientFields,
        }).subscribe({
            next: () => {
                this.toast.success('تم الحفظ بنجاح');
                this.voucher = {
                    date: new Date().toISOString().split('T')[0],
                    type: 'payment',
                    voucher_type: 'client',
                    account_id: null,
                    client_id: null,
                    amount: 0,
                    notes: ''
                };
                this.selectedClientKey = null;
                this.selectedSourceId = null;
            },
            error: (err) => {
                this.toast.error(err.error?.message || 'حدث خطأ أثناء الحفظ');
            }
        });
    }
}
