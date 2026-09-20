import { Component, Inject, OnInit } from '@angular/core';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { PaymentSourcesService, PaymentSourceItem } from 'src/app/accounting/services/payment-sources.service';
import { CollectionCompanyService } from '../services/collection-company.service';
import { OrderService } from '../services/order.service';
import Swal from 'sweetalert2';

export type PrepaidAdjustmentAction = 'reverse' | 'change_source';
export type PrepaidPaymentType = 'safe' | 'bank' | 'service_account' | 'collection_company';

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
  collectionCompanies: { id: number; name: string; status?: string }[] = [];

  form = new FormGroup({
    action: new FormControl<PrepaidAdjustmentAction>('reverse', { nonNullable: true, validators: [Validators.required] }),
    paymentType: new FormControl<PrepaidPaymentType>('bank', { nonNullable: true }),
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
      defaultPaymentType?: PrepaidPaymentType;
      refresh: () => void;
    },
    private orderService: OrderService,
    private paymentSources: PaymentSourcesService,
    private collectionCompanyService: CollectionCompanyService,
  ) {}

  ngOnInit(): void {
    const defaultAction = this.data.defaultAction ?? 'change_source';
    const defaultPaymentType = this.data.defaultPaymentType ?? 'safe';
    this.form.patchValue({
      action: defaultAction,
      paymentType: defaultPaymentType,
    });
    if (defaultAction === 'change_source') {
      this.updateSourceValidators('change_source');
    }

    this.paymentSources.getPaymentSources().subscribe((res: any) => {
      this.safes = res.safes || [];
      this.banks = res.banks || [];
      this.serviceAccounts = res.service_accounts || [];
    });

    this.collectionCompanyService.select().subscribe((res: any) => {
      const list = Array.isArray(res) ? res : res?.data ?? [];
      this.collectionCompanies = list.filter((c: any) => c?.id && c.status !== 'inactive');
    });

    this.form.get('action')?.valueChanges.subscribe((action) => {
      this.updateSourceValidators(action);
    });

    this.form.get('paymentType')?.valueChanges.subscribe(() => {
      this.form.controls.sourceId.setValue(null);
      this.updateSourceValidators(this.form.controls.action.value);
    });
  }

  private updateSourceValidators(action: PrepaidAdjustmentAction): void {
    const sourceControl = this.form.get('sourceId');
    if (action === 'change_source') {
      sourceControl?.setValidators([Validators.required]);
    } else {
      sourceControl?.clearValidators();
      sourceControl?.setValue(null);
    }
    sourceControl?.updateValueAndValidity();
  }

  get selectedAction(): PrepaidAdjustmentAction {
    return this.form.controls.action.value;
  }

  get paymentType(): PrepaidPaymentType {
    return this.form.controls.paymentType.value;
  }

  get sourceList(): PaymentSourceItem[] {
    if (this.paymentType === 'collection_company') {
      return this.collectionCompanies as PaymentSourceItem[];
    }
    if (this.paymentType === 'safe') return this.safes;
    if (this.paymentType === 'service_account') return this.serviceAccounts;
    return this.banks;
  }

  get sourceFieldLabel(): string {
    if (this.paymentType === 'collection_company') return 'شركة التحصيل';
    if (this.paymentType === 'safe') return 'الخزينة';
    if (this.paymentType === 'bank') return 'البنك';
    return 'الحساب الخدمي';
  }

  get currentSourceText(): string {
    if (this.data.currentSourceLabel) {
      return this.data.currentSourceLabel;
    }
    const t = this.data.currentPaymentType;
    if (t === 'safe') return 'خزينة';
    if (t === 'service_account') return 'حساب خدمي';
    if (t === 'bank') return 'بنك';
    if (t === 'collection_company') return 'شركة تحصيل';
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

    const targetLabel =
      paymentType === 'collection_company'
        ? this.collectionCompanies.find((c) => c.id === sourceId)?.name ?? 'شركة تحصيل'
        : this.sourceList.find((item) => item.id === sourceId)?.name ?? this.sourceFieldLabel;

    Swal.fire({
      icon: 'question',
      title: 'تغيير مصدر الدفع؟',
      html:
        paymentType === 'collection_company'
          ? `سيتم نقل مبلغ <strong>${this.data.prepaidAmount}</strong> من ${this.currentSourceText} إلى مديونية على <strong>${targetLabel}</strong> (بدون إيداع بنك/خزينة).`
          : `سيتم نقل مبلغ <strong>${this.data.prepaidAmount}</strong> من ${this.currentSourceText} إلى <strong>${targetLabel}</strong>.`,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (result.isConfirmed) {
        const body: any = { action: 'change_source', payment_type: paymentType, note };
        if (paymentType === 'safe') body.safe_id = sourceId;
        else if (paymentType === 'service_account') body.service_account_id = sourceId;
        else if (paymentType === 'collection_company') body.collection_company_id = sourceId;
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
