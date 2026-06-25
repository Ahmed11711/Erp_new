import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { formatVoucherClientPartyName } from '../cash-client-selection';
import { PaymentSourcesService, PaymentSourceItem } from '../../../accounting/services/payment-sources.service';
import { Voucher, VoucherService } from '../../../accounting/services/voucher.service';

type CashParty = 'client' | 'supplier' | 'shipping_company' | 'collection_company';

export interface CashVoucherEditDialogData {
  voucherId: number;
}

@Component({
  selector: 'app-cash-voucher-edit-dialog',
  templateUrl: './cash-voucher-edit-dialog.component.html',
  styleUrls: ['./cash-voucher-edit-dialog.component.css'],
})
export class CashVoucherEditDialogComponent implements OnInit {
  loading = true;
  saving = false;
  loadError = '';

  party: CashParty = 'client';
  partyLabel = '';
  fundDirection: 'receipt' | 'payment' = 'receipt';
  paymentPlace: 'safe' | 'bank' | 'service_account' = 'safe';
  selectedSourceId: number | null = null;

  safes: PaymentSourceItem[] = [];
  banks: PaymentSourceItem[] = [];
  serviceAccounts: PaymentSourceItem[] = [];

  form = {
    date: '',
    amount: 0,
    notes: '',
    reference_number: '',
  };

  private voucher: Voucher | null = null;

  constructor(
    private dialogRef: MatDialogRef<CashVoucherEditDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: CashVoucherEditDialogData,
    private voucherService: VoucherService,
    private paymentSourcesService: PaymentSourcesService,
  ) {}

  ngOnInit(): void {
    this.paymentSourcesService.getPaymentSources().subscribe({
      next: (res) => {
        this.safes = (res.safes || []).filter((s) => !!s.account_id);
        this.banks = (res.banks || []).filter((b) => !!b.account_id);
        this.serviceAccounts = (res.service_accounts || []).filter((a) => !!a.account_id);
        this.loadVoucher();
      },
      error: () => {
        this.loadError = 'تعذر تحميل مصادر الدفع';
        this.loading = false;
      },
    });
  }

  get supportsPaymentDirection(): boolean {
    return this.party === 'shipping_company' || this.party === 'collection_company';
  }

  get partyTypeLabel(): string {
    switch (this.party) {
      case 'client': return 'عميل';
      case 'supplier': return 'مورد';
      case 'shipping_company': return 'شركة شحن / مندوب';
      case 'collection_company': return 'شركة تحصيل';
      default: return '—';
    }
  }

  sourceLabel(item: PaymentSourceItem): string {
    const tree = item.account ? ` — شجرة: ${item.account.code}` : '';
    return `${item.name}${tree} — رصيد: ${(item.balance ?? 0).toFixed(2)}`;
  }

  onPlaceChange(): void {
    this.selectedSourceId = null;
  }

  close(): void {
    this.dialogRef.close(false);
  }

  save(): void {
    if (!this.voucher?.id) {
      return;
    }

    const accountId = this.resolveAccountId();
    if (!accountId) {
      alert('اختر خزينة أو بنك أو حساب خدمي مرتبطاً بحساب شجري');
      return;
    }
    if (!this.form.date) {
      alert('اختر التاريخ');
      return;
    }
    if (!this.form.amount || this.form.amount <= 0) {
      alert('أدخل مبلغاً صحيحاً');
      return;
    }

    this.saving = true;
    this.voucherService.updateVoucher(this.voucher.id, {
      date: this.form.date,
      type: this.fundDirection,
      account_id: accountId,
      amount: this.form.amount,
      notes: this.form.notes || '',
      reference_number: this.form.reference_number || '',
    }).subscribe({
      next: () => {
        this.saving = false;
        this.dialogRef.close(true);
      },
      error: (err) => {
        this.saving = false;
        const msg = err?.error?.message
          || err?.error?.errors?.amount?.[0]
          || err?.error?.errors?.date?.[0]
          || 'تعذر حفظ التعديل';
        alert(msg);
      },
    });
  }

  private loadVoucher(): void {
    this.voucherService.getVoucher(this.data.voucherId).subscribe({
      next: (v) => {
        this.voucher = v;
        this.party = (v.voucher_type || 'client') as CashParty;
        this.partyLabel = v.client_or_supplier_name
          || formatVoucherClientPartyName(v)
          || v.client?.name
          || v.client?.company_name
          || v.supplier?.supplier_name
          || v.shipping_company?.name
          || v.collection_company?.name
          || '—';
        this.fundDirection = v.type === 'payment' ? 'payment' : 'receipt';
        this.form.date = this.toDateInput(v.date);
        this.form.amount = Number(v.amount) || 0;
        this.form.notes = v.notes || '';
        this.form.reference_number = v.reference_number || '';
        this.inferPaymentSource(v.account_id);
        this.loading = false;
      },
      error: () => {
        this.loadError = 'تعذر تحميل بيانات السند';
        this.loading = false;
      },
    });
  }

  private inferPaymentSource(accountId: number | null | undefined): void {
    if (!accountId) {
      return;
    }

    const safe = this.safes.find((s) => s.account_id === accountId);
    if (safe) {
      this.paymentPlace = 'safe';
      this.selectedSourceId = safe.id;
      return;
    }

    const bank = this.banks.find((b) => b.account_id === accountId);
    if (bank) {
      this.paymentPlace = 'bank';
      this.selectedSourceId = bank.id;
      return;
    }

    const service = this.serviceAccounts.find((a) => a.account_id === accountId);
    if (service) {
      this.paymentPlace = 'service_account';
      this.selectedSourceId = service.id;
    }
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

  private toDateInput(value: string | undefined): string {
    if (!value) {
      return new Date().toISOString().split('T')[0];
    }
    return String(value).slice(0, 10);
  }
}
