import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { PaymentSourcesService, PaymentSourceItem } from '../../../accounting/services/payment-sources.service';
import { VoucherService } from '../../../accounting/services/voucher.service';
import { ToastService } from '../../../shared/toast/toast.service';

@Component({
  selector: 'app-receive-payment',
  templateUrl: './receive-payment.component.html',
  styleUrls: ['./receive-payment.component.css']
})
export class ReceivePaymentComponent implements OnInit {
  party: 'client' | 'supplier' = 'client';
  paymentPlace: 'safe' | 'bank' | 'service_account' = 'safe';
  selectedSourceId: number | null = null;

  safes: PaymentSourceItem[] = [];
  banks: PaymentSourceItem[] = [];
  serviceAccounts: PaymentSourceItem[] = [];
  clients: any[] = [];
  suppliers: any[] = [];

  voucher: any = {
    date: new Date().toISOString().split('T')[0],
    type: 'receipt',
    voucher_type: 'client',
    account_id: null as number | null,
    client_id: null as number | null,
    supplier_id: null as number | null,
    amount: 0,
    notes: ''
  };

  constructor(
    private paymentSourcesService: PaymentSourcesService,
    private voucherService: VoucherService,
    private toast: ToastService,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    const routeParty = this.route.snapshot.data['defaultParty'] as 'client' | 'supplier' | undefined;
    if (routeParty === 'supplier') {
      this.applyParty('supplier');
    }

    this.route.queryParamMap.subscribe((params) => {
      const q = params.get('party');
      if (q === 'supplier') {
        this.applyParty('supplier');
      } else if (q === 'client') {
        this.applyParty('client');
      }
    });

    this.paymentSourcesService.getPaymentSources().subscribe({
      next: (res) => {
        this.safes = res.safes || [];
        this.banks = res.banks || [];
        this.serviceAccounts = res.service_accounts || [];
      },
      error: () => this.toast.error('تعذر تحميل أماكن الإيداع')
    });

    this.voucherService.getClients().subscribe((res: any) => {
      this.clients = res.data || res || [];
    });
    this.voucherService.getSuppliers().subscribe((res: any) => {
      this.suppliers = res.data || res || [];
    });
  }

  private applyParty(p: 'client' | 'supplier'): void {
    this.party = p;
    this.voucher.voucher_type = p;
    if (p === 'client') {
      this.voucher.supplier_id = null;
    } else {
      this.voucher.client_id = null;
    }
  }

  onPartyChange(): void {
    this.applyParty(this.party);
  }

  onPlaceChange(): void {
    this.selectedSourceId = null;
    this.voucher.account_id = null;
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

  save(): void {
    const accountId = this.resolveAccountId();
    this.voucher.account_id = accountId;

    if (!accountId) {
      this.toast.warning('اختر خزينة أو بنك أو حساب خدمي مرتبطاً بحساب شجري');
      return;
    }
    if (this.party === 'client' && !this.voucher.client_id) {
      this.toast.warning('اختر العميل');
      return;
    }
    if (this.party === 'supplier' && !this.voucher.supplier_id) {
      this.toast.warning('اختر المورد');
      return;
    }
    if (!this.voucher.amount || this.voucher.amount <= 0) {
      this.toast.warning('أدخل مبلغاً صحيحاً');
      return;
    }

    const payload = {
      ...this.voucher,
      client_id: this.party === 'client' ? this.voucher.client_id : null,
      supplier_id: this.party === 'supplier' ? this.voucher.supplier_id : null
    };

    this.voucherService.createVoucher(payload).subscribe({
      next: () => {
        this.toast.success('تم حفظ القبض بنجاح');
        this.voucher = {
          date: new Date().toISOString().split('T')[0],
          type: 'receipt',
          voucher_type: this.party,
          account_id: null,
          client_id: null,
          supplier_id: null,
          amount: 0,
          notes: ''
        };
        this.selectedSourceId = null;
        this.onPartyChange();
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'حدث خطأ أثناء الحفظ');
      }
    });
  }
}
