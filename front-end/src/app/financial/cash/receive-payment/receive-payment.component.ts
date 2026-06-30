import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, ParamMap } from '@angular/router';
import { PaymentSourcesService, PaymentSourceItem } from '../../../accounting/services/payment-sources.service';
import { VoucherService } from '../../../accounting/services/voucher.service';
import { ToastService } from '../../../shared/toast/toast.service';
import {
  buildClientVoucherFields,
  CashClientCompanyOption,
  CashClientOptionsResponse,
} from '../cash-client-selection';

type CashParty = 'client' | 'supplier' | 'shipping_company' | 'collection_company';

@Component({
  selector: 'app-receive-payment',
  templateUrl: './receive-payment.component.html',
  styleUrls: ['./receive-payment.component.css']
})
export class ReceivePaymentComponent implements OnInit {
  party: CashParty = 'client';
  /** قبض = أموال تنزل للخزينة. صرف = دفع مستحقات (مدعوم لشركات الشحن وشركات التحصيل). */
  fundDirection: 'receipt' | 'payment' = 'receipt';
  paymentPlace: 'safe' | 'bank' | 'service_account' = 'safe';
  selectedSourceId: number | null = null;

  safes: PaymentSourceItem[] = [];
  banks: PaymentSourceItem[] = [];
  serviceAccounts: PaymentSourceItem[] = [];
  clientOptions: CashClientOptionsResponse = { companies: [], individuals: [] };
  selectedClientKey: string | null = null;
  clientSearch = '';
  suppliers: any[] = [];
  shippingPartners: { id: number; name: string; type: string; balance?: number }[] = [];
  collectionCompanies: { id: number; name: string; linked_shipping_company_id?: number | null; balance?: number }[] = [];

  voucher: any = {
    date: new Date().toISOString().split('T')[0],
    type: 'receipt',
    voucher_type: 'client',
    account_id: null as number | null,
    client_id: null as number | null,
    supplier_id: null as number | null,
    shipping_company_id: null as number | null,
    collection_company_id: null as number | null,
    amount: 0,
    notes: ''
  };

  /** يُمرَّر من تقرير الذمم لإغلاق سطور الطلب عند قبض المبلغ */
  settledOrderIds: number[] = [];

  constructor(
    private paymentSourcesService: PaymentSourcesService,
    private voucherService: VoucherService,
    private toast: ToastService,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    const routeParty = this.route.snapshot.data['defaultParty'] as CashParty | undefined;
    const routeVoucherType = this.route.snapshot.data['defaultVoucherType'] as
      | 'receipt'
      | 'payment'
      | undefined;

    if (routeParty) {
      this.applyParty(routeParty);
    }
    if (routeVoucherType === 'payment' || routeVoucherType === 'receipt') {
      this.setFundDirection(routeVoucherType);
    }

    this.applyQueryParams(this.route.snapshot.queryParamMap);

    this.route.queryParamMap.subscribe((params) => {
      this.applyQueryParams(params);
      this.applyPaymentSourceFromQuery(params);
    });

    this.paymentSourcesService.getPaymentSources().subscribe({
      next: (res) => {
        this.safes = (res.safes || []).filter((s) => !!s.account_id);
        this.banks = (res.banks || []).filter((b) => !!b.account_id);
        this.serviceAccounts = (res.service_accounts || []).filter((a) => !!a.account_id);
        this.applyPaymentSourceFromQuery(this.route.snapshot.queryParamMap);
      },
      error: () => this.toast.error('تعذر تحميل أماكن الإيداع')
    });

    this.loadClientOptions();

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
    this.voucherService.getCollectionCompaniesSelect().subscribe({
      next: (rows) => {
        const list = rows || [];
        this.collectionCompanies = list.slice().sort((a, b) =>
          (a.name || '').localeCompare(b.name || '', 'ar')
        );
      },
      error: () => this.toast.error('تعذر تحميل شركات التحصيل')
    });
  }

  /** من تقرير الذمم: ?party=collection_company&type=receipt&collection_company_id=5&amount=1000 */
  private applyQueryParams(params: ParamMap): void {
    const q = params.get('party');
    if (q === 'supplier') {
      this.applyParty('supplier');
    } else if (q === 'client') {
      this.applyParty('client');
    } else if (q === 'shipping_company') {
      this.applyParty('shipping_company');
    } else if (q === 'collection_company') {
      this.applyParty('collection_company');
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
    const ccid = params.get('collection_company_id');
    if (ccid) {
      const id = parseInt(ccid, 10);
      if (!isNaN(id) && id > 0) {
        this.applyParty('collection_company');
        this.voucher.collection_company_id = id;
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

  /** ?payment_place=bank&source_id=3 أو safe_id / bank_id / service_account_id */
  private applyPaymentSourceFromQuery(params: ParamMap): void {
    const place = params.get('payment_place');
    if (place === 'safe' || place === 'bank' || place === 'service_account') {
      this.paymentPlace = place;
    }

    const rawId =
      params.get('source_id') ??
      params.get('safe_id') ??
      params.get('bank_id') ??
      params.get('service_account_id');
    if (!rawId) return;

    const id = parseInt(rawId, 10);
    if (isNaN(id) || id <= 0) return;

    const list =
      this.paymentPlace === 'safe'
        ? this.safes
        : this.paymentPlace === 'bank'
          ? this.banks
          : this.serviceAccounts;
    if (list.some((x) => x.id === id)) {
      this.selectedSourceId = id;
    }
  }

  onClientSearchChange(): void {
    this.loadClientOptions(this.clientSearch);
  }

  private loadClientOptions(search = ''): void {
    this.voucherService.getClientOptions(search).subscribe({
      next: (res) => {
        this.clientOptions = {
          companies: res?.companies || [],
          individuals: res?.individuals || [],
        };
      },
      error: () => this.toast.error('تعذر تحميل قائمة العملاء'),
    });
  }

  sourceLabel(item: PaymentSourceItem): string {
    const tree = item.account ? ` — شجرة: ${item.account.code}` : '';
    return `${item.name}${tree} — رصيد: ${(item.balance ?? 0).toFixed(2)}`;
  }

  private supportsPaymentDirection(): boolean {
    return this.party === 'shipping_company' || this.party === 'collection_company';
  }

  private applyParty(p: CashParty): void {
    this.party = p;
    this.voucher.voucher_type = p;
    this.voucher.client_id = null;
    this.selectedClientKey = null;
    this.voucher.supplier_id = null;
    this.voucher.shipping_company_id = null;
    this.voucher.collection_company_id = null;
    if (!this.supportsPaymentDirection()) {
      this.setFundDirection('receipt');
      this.settledOrderIds = [];
    }
  }

  onPartyChange(): void {
    this.applyParty(this.party);
  }

  setFundDirection(dir: 'receipt' | 'payment'): void {
    if (!this.supportsPaymentDirection() && dir === 'payment') {
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

  get selectedSupplier(): any | null {
    if (this.party !== 'supplier' || !this.voucher.supplier_id) {
      return null;
    }
    return this.suppliers.find((s) => s.id === this.voucher.supplier_id) ?? null;
  }

  get selectedClientCompany(): CashClientCompanyOption | null {
    if (this.party !== 'client' || !this.selectedClientKey || !this.selectedClientKey.startsWith('company:')) {
      return null;
    }
    const id = Number(this.selectedClientKey.slice('company:'.length));
    return this.clientOptions.companies.find((c) => c.id === id) ?? null;
  }

  get selectedIndividualPhone(): string | null {
    if (this.party !== 'client' || !this.selectedClientKey || !this.selectedClientKey.startsWith('individual:')) {
      return null;
    }
    return this.selectedClientKey.slice('individual:'.length) || null;
  }

  get selectedShippingPartner(): { id: number; name: string; type: string; balance?: number } | null {
    if (this.party !== 'shipping_company' || !this.voucher.shipping_company_id) {
      return null;
    }
    return this.shippingPartners.find((s) => s.id === this.voucher.shipping_company_id) ?? null;
  }

  get selectedCollectionCompany(): { id: number; name: string; balance?: number } | null {
    if (this.party !== 'collection_company' || !this.voucher.collection_company_id) {
      return null;
    }
    return this.collectionCompanies.find((c) => c.id === this.voucher.collection_company_id) ?? null;
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
    if (this.party === 'client' && !this.selectedClientKey) {
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
    if (this.party === 'collection_company' && !this.voucher.collection_company_id) {
      this.toast.warning('اختر شركة التحصيل');
      return;
    }
    if (!this.voucher.amount || this.voucher.amount <= 0) {
      this.toast.warning('أدخل مبلغاً صحيحاً');
      return;
    }

    const clientFields = this.party === 'client'
      ? buildClientVoucherFields(this.selectedClientKey, this.clientOptions)
      : {
          client_kind: null,
          client_id: null,
          individual_customer_phone: null,
          individual_customer_name: null,
        };

    const payload = {
      ...this.voucher,
      type: this.fundDirection,
      ...clientFields,
      supplier_id: this.party === 'supplier' ? this.voucher.supplier_id : null,
      shipping_company_id: this.party === 'shipping_company' ? this.voucher.shipping_company_id : null,
      collection_company_id: this.party === 'collection_company' ? this.voucher.collection_company_id : null,
      ...((this.party === 'shipping_company' || this.party === 'collection_company') && this.settledOrderIds.length
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
          collection_company_id: null,
          amount: 0,
          notes: ''
        };
        this.settledOrderIds = [];
        this.selectedSourceId = null;
        this.selectedClientKey = null;
        this.onPartyChange();
        if (this.supportsPaymentDirection()) {
          this.setFundDirection(this.fundDirection);
        }
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'حدث خطأ أثناء الحفظ');
      }
    });
  }
}
