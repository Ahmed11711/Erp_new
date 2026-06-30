import { Component, Inject, OnInit } from '@angular/core';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { PaymentSourcesService, PaymentSourceItem } from 'src/app/accounting/services/payment-sources.service';
import { OrderService } from '../services/order.service';
import Swal from 'sweetalert2';

export type PrepaidAdjustmentAction = 'reverse' | 'change_source';

@Component({
  selector: 'app-dialog-prepaid-adjustment',
  templateUrl: './dialog-prepaid-adjustment.component.html',
  styleUrls: ['./dialog-prepaid-adjustment.component.css'],
})
export class DialogPrepaidAdjustmentComponent implements OnInit {
  submitting = false;
  safes: PaymentSourceItem[] = [];
  banks: PaymentSourceItem[] = [];
  serviceAccounts: PaymentSourceItem[] = [];

  form = new FormGroup({
    action: new FormControl<PrepaidAdjustmentAction>('reverse', { nonNullable: true, validators: [Validators.required] }),
    paymentType: new FormControl<'safe' | 'bank' | 'service_account'>('bank', { nonNullable: true }),
    sourceId: new FormControl<number | null>(null),
    note: new FormControl<string>(''),
  });

  constructor(
    public dialogRef: MatDialogRef<DialogPrepaidAdjustmentComponent>,
    @Inject(MAT_DIALOG_DATA) public data: {
      orderId: number;
      prepaidAmount: number;
      currentPaymentType?: string | null;
      currentSourceLabel?: string | null;
      defaultAction?: PrepaidAdjustmentAction;
      defaultPaymentType?: 'safe' | 'bank' | 'service_account';
      refresh: () => void;
    },
    private orderService: OrderService,
    private paymentSources: PaymentSourcesService,
  ) {}

  ngOnInit(): void {
    const defaultAction = this.data.defaultAction ?? 'change_source';
    const defaultPaymentType = this.data.defaultPaymentType ?? 'safe';
    this.form.patchValue({
      action: defaultAction,
      paymentType: defaultPaymentType,
    });
    if (defaultAction === 'change_source') {
      this.form.get('sourceId')?.setValidators([Validators.required]);
      this.form.get('sourceId')?.updateValueAndValidity();
    }

    this.paymentSources.getPaymentSources().subscribe((res: any) => {
      this.safes = res.safes || [];
      this.banks = res.banks || [];
      this.serviceAccounts = res.service_accounts || [];
    });

    this.form.get('action')?.valueChanges.subscribe((action) => {
      const sourceControl = this.form.get('sourceId');
      if (action === 'change_source') {
        sourceControl?.setValidators([Validators.required]);
      } else {
        sourceControl?.clearValidators();
        sourceControl?.setValue(null);
      }
      sourceControl?.updateValueAndValidity();
    });
  }

  get selectedAction(): PrepaidAdjustmentAction {
    return this.form.controls.action.value;
  }

  get paymentType(): 'safe' | 'bank' | 'service_account' {
    return this.form.controls.paymentType.value;
  }

  get sourceList(): PaymentSourceItem[] {
    if (this.paymentType === 'safe') return this.safes;
    if (this.paymentType === 'service_account') return this.serviceAccounts;
    return this.banks;
  }

  get currentSourceText(): string {
    if (this.data.currentSourceLabel) {
      return this.data.currentSourceLabel;
    }
    const t = this.data.currentPaymentType;
    if (t === 'safe') return 'خزينة';
    if (t === 'service_account') return 'حساب خدمي';
    if (t === 'bank') return 'بنك';
    return '—';
  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

  submit(): void {
    if (this.form.invalid || this.submitting) {
      this.form.markAllAsTouched();
      return;
    }

    const action = this.form.controls.action.value;
    const note = String(this.form.controls.note.value ?? '').trim() || undefined;

    if (action === 'reverse') {
      Swal.fire({
        icon: 'warning',
        title: 'عكس مبلغ تحت الحساب؟',
        html: `سيتم إرجاع <strong>${this.data.prepaidAmount}</strong> إلى مصدر الدفع الحالي وزيادة صافي الطلب.`,
        showCancelButton: true,
        confirmButtonText: 'نعم، عكس',
        cancelButtonText: 'إلغاء',
      }).then((result) => {
        if (result.isConfirmed) {
          this.runSubmit({ action: 'reverse', note });
        }
      });
      return;
    }

    const paymentType = this.form.controls.paymentType.value;
    const sourceId = this.form.controls.sourceId.value;
    if (!sourceId) {
      Swal.fire({ icon: 'error', title: 'اختر مصدر الدفع الجديد' });
      return;
    }

    Swal.fire({
      icon: 'question',
      title: 'تغيير مصدر الدفع؟',
      html: `سيتم نقل مبلغ <strong>${this.data.prepaidAmount}</strong> من ${this.currentSourceText} إلى المصدر الجديد.`,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (result.isConfirmed) {
        const body: any = { action: 'change_source', payment_type: paymentType, note };
        if (paymentType === 'safe') body.safe_id = sourceId;
        else if (paymentType === 'service_account') body.service_account_id = sourceId;
        else body.bank_id = sourceId;
        this.runSubmit(body);
      }
    });
  }

  private runSubmit(body: Record<string, unknown>): void {
    this.submitting = true;
    this.orderService.adjustPrepaid(this.data.orderId, body).subscribe({
      next: (res: any) => {
        this.submitting = false;
        this.data.refresh();
        this.dialogRef.close(true);
        Swal.fire({
          icon: 'success',
          title: res?.message || 'تم بنجاح',
          timer: 2200,
          showConfirmButton: false,
        });
      },
      error: (err) => {
        this.submitting = false;
        Swal.fire({
          icon: 'error',
          title: err?.error?.message || 'فشل التنفيذ',
        });
      },
    });
  }
}
