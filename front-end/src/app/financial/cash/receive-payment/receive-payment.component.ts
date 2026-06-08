import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, ParamMap } from '@angular/router';
import { PaymentSourcesService, PaymentSourceItem } from '../../../accounting/services/payment-sources.service';
import { VoucherService } from '../../../accounting/services/voucher.service';
import { ToastService } from '../../../shared/toast/toast.service';

@Component({
  selector: 'app-receive-payment',
  templateUrl: './receive-payment.component.html',
  styleUrls: ['./receive-payment.component.css']
})
export class ReceivePaymentComponent implements OnInit {
  party: 'client' | 'supplier' | 'shipping_company' = 'client';
  /** قبض = أموال تنزل للخزينة من المندوب. صرف = دفع مستحقات للمندوب/شركة الشحن (مدعوم لجهة الشحن فقط). */
  fundDirection: 'receipt' | 'payment' = 'receipt';
  paymentPlace: 'safe' | 'bank' | 'service_account' = 'safe';
  selectedSourceId: number | null = null;

  safes: PaymentSourceItem[] = [];
  banks: PaymentSourceItem[] = [];
  serviceAccounts: PaymentSourceItem[] = [];
  clients: any[] = [];
  suppliers: any[] = [];
  shippingPartners: { id: number; name: string; type: string }[] = [];

  voucher: any = {
    date: new Date().toISOString().split('T')[0],
    type: 'receipt',
    voucher_type: 'client',
    account_id: null as number | null,
    client_id: null as number | null,
    supplier_id: null as number | null,
    shipping_company_id: null as number | null,
    amount: 0,
    notes: ''
  };

  /** يُمرَّر من تقرير ذمم الشحن لإغلاق سطور الطلب عند قبض المبلغ */
  settledOrderIds: number[] = [];

  constructor(
    private paymentSourcesService: PaymentSourcesService,
    private voucherService: VoucherService,
    private toast: ToastService,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    const routeParty = this.route.snapshot.data['defaultParty'] as
      | 'client'
      | 'supplier'
      | 'shipping_company'
      | undefined;
    const routeVoucherType = this.route.snapshot.data['defaultVoucherType'] as
      | 'receipt'
      | 'payment'
      | undefined;

    if (routeParty === 'supplier') {
      this.applyParty('supplier');
    } else if (routeParty === 'shipping_company') {
      this.applyParty('shipping_company');
    }
    if (routeVoucherType === 'payment' || routeVoucherType === 'receipt') {
      this.setFundDirection(routeVoucherType);
    }

    this.applyQueryParams(this.route.snapshot.queryParamMap);

    this.route.queryParamMap.subscribe((params) => {
      this.applyQueryParams(params);
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
    this.voucherService.getShippingCompaniesSelect().subscribe({
      next: (rows) => {
        const list = rows || [];
        this.shippingPartners = list.slice().sort((a, b) =>
          (a.name || '').localeCompare(b.name || '', 'ar')
        );
      },
      error: () => this.toast.error('تعذر تحميل شركات الشحن والمناديب')
    });
  }

  /** من تقرير الذمم: ?party=shipping_company&type=payment&shipping_company_id=5&amount=1000 */
  private applyQueryParams(params: ParamMap): void {
    const q = params.get('party');
    if (q === 'supplier') {
      this.applyParty('supplier');
    } else if (q === 'client') {
      this.applyParty('client');
    } else if (q === 'shipping_company') {
      this.applyParty('shipping_company');
    }
    const t = params.get('type');
    if (t === 'payment' || t === 'receipt') {
      this.setFundDirection(t);
    }
    const sid = params.get('shipping_company_id');
    if (sid) {
      const id = parseInt(sid, 10);
      if (!isNaN(id) && id > 0) {
        this.applyParty('shipping_company');
        this.voucher.shipping_company_id = id;
      }
    }
    const amt = params.get('amount');
    if (amt) {
      const a = parseFloat(amt);
      if (!isNaN(a) && a > 0) {
        this.voucher.amount = Math.round(a * 100) / 100;
      }
    }
    const note = params.get('note');
    if (note && note.trim() !== '') {
      this.voucher.notes = note;
    }
    const soi = params.get('settled_order_ids');
    if (soi && soi.trim() !== '') {
      this.settledOrderIds = soi
        .split(',')
        .map((s) => parseInt(s.trim(), 10))
        .filter((n) => !isNaN(n) && n > 0);
    } else {
      this.settledOrderIds = [];
    }
  }

  private applyParty(p: 'client' | 'supplier' | 'shipping_company'): void {
    this.party = p;
    this.voucher.voucher_type = p;
    this.voucher.client_id = null;
    this.voucher.supplier_id = null;
    this.voucher.shipping_company_id = null;
    if (p !== 'shipping_company') {
      this.setFundDirection('receipt');
      this.settledOrderIds = [];
    }
  }

  onPartyChange(): void {
    this.applyParty(this.party);
  }

  setFundDirection(dir: 'receipt' | 'payment'): void {
    if (this.party !== 'shipping_company' && dir === 'payment') {
      this.fundDirection = 'receipt';
      this.voucher.type = 'receipt';
      return;
    }
    this.fundDirection = dir;
    this.voucher.type = dir;
  }

  onFundDirectionChange(): void {
    this.setFundDirection(this.fundDirection);
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
    if (this.party === 'shipping_company' && !this.voucher.shipping_company_id) {
      this.toast.warning('اختر شركة الشحن أو المندوب');
      return;
    }
    if (!this.voucher.amount || this.voucher.amount <= 0) {
      this.toast.warning('أدخل مبلغاً صحيحاً');
      return;
    }

    const payload = {
      ...this.voucher,
      type: this.fundDirection,
      client_id: this.party === 'client' ? this.voucher.client_id : null,
      supplier_id: this.party === 'supplier' ? this.voucher.supplier_id : null,
      shipping_company_id: this.party === 'shipping_company' ? this.voucher.shipping_company_id : null,
      ...(this.party === 'shipping_company' && this.settledOrderIds.length
        ? { settled_order_ids: this.settledOrderIds }
        : {})
    };

    this.voucherService.createVoucher(payload).subscribe({
      next: () => {
        this.toast.success(this.fundDirection === 'payment' ? 'تم حفظ الصرف بنجاح' : 'تم حفظ القبض بنجاح');
        this.voucher = {
          date: new Date().toISOString().split('T')[0],
          type: this.fundDirection,
          voucher_type: this.party,
          account_id: null,
          client_id: null,
          supplier_id: null,
          shipping_company_id: null,
          amount: 0,
          notes: ''
        };
        this.settledOrderIds = [];
        this.selectedSourceId = null;
        this.onPartyChange();
        if (this.party === 'shipping_company') {
          this.setFundDirection(this.fundDirection);
        }
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'حدث خطأ أثناء الحفظ');
      }
    });
  }
}
