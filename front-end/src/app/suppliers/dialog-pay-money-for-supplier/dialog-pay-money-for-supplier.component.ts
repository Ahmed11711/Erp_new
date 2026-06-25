import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { SuppliersService } from '../services/suppliers.service';
import { PaymentSourcesService, PaymentSourceItem } from 'src/app/accounting/services/payment-sources.service';
import Swal from 'sweetalert2';

export interface SupplierPayDialogData {
  supplier: { id: number; supplier_name: string; balance: number };
  refreshData?: () => void;
  dialogTitle?: string;
  subtitle?: string;
  hint?: string;
  suggestedAmount?: number;
  maxAmount?: number;
  processingInvoiceId?: number;
  processingOrderId?: number;
}

@Component({
  selector: 'app-dialog-pay-money-for-supplier',
  templateUrl: './dialog-pay-money-for-supplier.component.html',
  styleUrls: ['./dialog-pay-money-for-supplier.component.css']
})
export class DialogPayMoneyForSupplierComponent implements OnInit {

  paymentType: 'bank' | 'safe' | 'service_account' = 'bank';
  bankId: number | null = null;
  safeId: number | null = null;
  serviceAccountId: number | null = null;
  payAmount: number | null = null;
  submitting = false;

  safes: PaymentSourceItem[] = [];
  banks: PaymentSourceItem[] = [];
  serviceAccounts: PaymentSourceItem[] = [];

  constructor(
    public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: SupplierPayDialogData,
    private supplierService: SuppliersService,
    private paymentSources: PaymentSourcesService
  ) {}

  ngOnInit() {
    const suggested = Number(this.data.suggestedAmount ?? 0);
    if (suggested > 0) {
      this.payAmount = suggested;
    }

    this.paymentSources.getPaymentSources().subscribe((res: any) => {
      this.safes = res.safes || [];
      this.banks = res.banks || [];
      this.serviceAccounts = res.service_accounts || [];
    });
  }

  paymentTypeChange(): void {
    this.bankId = null;
    this.safeId = null;
    this.serviceAccountId = null;
  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

  get canSubmit(): boolean {
    if (this.paymentType === 'bank') {
      return !!this.bankId && this.bankId > 0;
    }
    if (this.paymentType === 'safe') {
      return !!this.safeId && this.safeId > 0;
    }
    if (this.paymentType === 'service_account') {
      return !!this.serviceAccountId && this.serviceAccountId > 0;
    }
    return false;
  }

  submit(form: any) {
    const amount = Number(this.payAmount);
    if (!amount || amount <= 0) {
      Swal.fire('تنبيه', 'أدخل مبلغاً صحيحاً', 'warning');
      return;
    }

    const maxDue = this.data.maxAmount != null ? Number(this.data.maxAmount) : null;
    if (maxDue != null && maxDue > 0 && amount > maxDue + 0.000001) {
      Swal.fire('تنبيه', `المبلغ أكبر من المتبقي (${maxDue.toFixed(2)} ج)`, 'warning');
      return;
    }

    const supplierBalance = Number(this.data.supplier?.balance ?? 0);
    if (supplierBalance > 0 && amount > supplierBalance + 0.000001) {
      Swal.fire('تنبيه', `المبلغ أكبر من ذمة المورد (${supplierBalance.toFixed(2)} ج)`, 'warning');
      return;
    }

    const payload: any = {
      amount,
      payment_type: this.paymentType,
    };
    if (this.paymentType === 'safe') {
      payload.safe_id = this.safeId;
    } else if (this.paymentType === 'service_account') {
      payload.service_account_id = this.serviceAccountId;
    } else {
      payload.bank_id = this.bankId;
    }
    if (this.data.processingInvoiceId) {
      payload.processing_invoice_id = this.data.processingInvoiceId;
    }
    if (this.data.processingOrderId) {
      payload.processing_order_id = this.data.processingOrderId;
    }

    this.submitting = true;
    this.supplierService.supplierPay(this.data.supplier.id, payload).subscribe({
      next: (res: any) => {
        this.submitting = false;
        if (res.message === 'success') {
          Swal.fire('تم', 'تم تسجيل السداد وترحيل القيد المحاسبي', 'success');
          this.data.refreshData?.();
          this.dialogRef.close(true);
        } else {
          Swal.fire('خطأ', res.message || 'فشل السداد', 'error');
        }
      },
      error: (e) => {
        this.submitting = false;
        Swal.fire('خطأ', e?.error?.message || 'فشل السداد', 'error');
      },
    });
  }
}
