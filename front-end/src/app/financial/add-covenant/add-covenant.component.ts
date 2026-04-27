import { Component, OnInit } from '@angular/core';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { PaymentSourcesService } from 'src/app/accounting/services/payment-sources.service';
import { ToastService } from 'src/app/shared/toast/toast.service';
import { CovenantService } from '../services/covenant.service';

@Component({
  selector: 'app-add-covenant',
  templateUrl: './add-covenant.component.html',
  styleUrls: ['./add-covenant.component.css']
})
export class AddCovenantComponent implements OnInit {
  errormessage = false;
  safesData: any[] = [];
  banksData: any[] = [];
  serviceAccountsData: any[] = [];
  dateFrom = new Date().toISOString().slice(0, 10);
  paymentType: 'safe' | 'bank' | 'service_account' = 'safe';

  form = new FormGroup({
    transaction_date: new FormControl<string | null>(null, [Validators.required]),
    covenant_type: new FormControl<'نقدية' | 'عينية' | null>(null, [Validators.required]),
    holder_kind: new FormControl<string | null>(null, [Validators.required]),
    payment_type: new FormControl<'safe' | 'bank' | 'service_account'>('safe', [Validators.required]),
    safe_id: new FormControl<number | null>(null),
    bank_id: new FormControl<number | null>(null),
    service_account_id: new FormControl<number | null>(null),
    price: new FormControl<number | null>(null, [Validators.required, Validators.min(0.01)]),
    description: new FormControl<string | null>(null, [Validators.required]),
    note: new FormControl<string | null>(null, [Validators.required]),
  });

  saving = false;

  constructor(
    private paymentSourcesService: PaymentSourcesService,
    private toast: ToastService,
    private covenantService: CovenantService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.form.patchValue({
      transaction_date: this.dateFrom,
      covenant_type: 'نقدية',
      holder_kind: 'petty_custodian',
      payment_type: 'safe',
    });
    this.paymentType = 'safe';

    this.paymentSourcesService.getPaymentSources().subscribe({
      next: (res) => {
        this.safesData = res.safes || [];
        this.banksData = res.banks || [];
        this.serviceAccountsData = res.service_accounts || [];
      },
      error: () => this.toast.error('تعذر تحميل مصادر الصرف (خزائن، بنوك، محافظ)')
    });
  }

  onPaymentTypeChange(): void {
    this.paymentType = this.form.get('payment_type')?.value || 'safe';
    this.form.patchValue({
      safe_id: null,
      bank_id: null,
      service_account_id: null
    });
  }

  get isSourceSelected(): boolean {
    const pt = this.form.get('payment_type')?.value;
    if (pt === 'safe') return this.form.get('safe_id')?.value != null;
    if (pt === 'bank') return this.form.get('bank_id')?.value != null;
    if (pt === 'service_account') return this.form.get('service_account_id')?.value != null;
    return false;
  }

  private resolveSourceLabel(): string {
    const pt = this.form.get('payment_type')?.value;
    const id =
      pt === 'safe'
        ? this.form.get('safe_id')?.value
        : pt === 'bank'
          ? this.form.get('bank_id')?.value
          : this.form.get('service_account_id')?.value;
    const list =
      pt === 'safe' ? this.safesData : pt === 'bank' ? this.banksData : this.serviceAccountsData;
    const row = list?.find((x: any) => Number(x.id) === Number(id));
    return row ? `${row.name}` : '';
  }

  onDateFromChange(event: Event): void {
    const v = (event.target as HTMLInputElement).value;
    this.form.patchValue({ transaction_date: v });
  }

  submitform(): void {
    this.errormessage = false;
    this.form.markAllAsTouched();

    if (this.form.invalid || !this.isSourceSelected) {
      this.errormessage = true;
      if (!this.isSourceSelected) {
        this.toast.warning('اختر مصدر الصرف: خزينة نقدية، أو بنك، أو محفظة إلكترونية (فودافون كاش من القائمة الأخيرة)');
      } else {
        this.toast.error('من فضلك أكمل الحقول المطلوبة');
      }
      return;
    }

    const v = this.form.getRawValue();
    const payload = {
      transaction_date: v.transaction_date!,
      covenant_type: v.covenant_type!,
      holder_kind: v.holder_kind!,
      payment_type: v.payment_type!,
      safe_id: v.payment_type === 'safe' ? v.safe_id : null,
      bank_id: v.payment_type === 'bank' ? v.bank_id : null,
      service_account_id: v.payment_type === 'service_account' ? v.service_account_id : null,
      amount: Number(v.price),
      description: v.description,
      note: v.note
    };

    this.saving = true;
    this.covenantService.create(payload).subscribe({
      next: () => {
        this.saving = false;
        this.toast.success(`تم حفظ العهدة (مصدر الصرف: ${this.resolveSourceLabel()}).`);
        this.router.navigate(['/dashboard/financial/covenant']);
      },
      error: (err) => {
        this.saving = false;
        const msg =
          err?.error?.message ||
          (typeof err?.error === 'string' ? err.error : null) ||
          'تعذر حفظ العهدة';
        this.toast.error(msg);
      }
    });
  }
}
