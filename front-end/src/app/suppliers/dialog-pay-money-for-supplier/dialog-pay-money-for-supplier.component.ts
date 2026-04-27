import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { SuppliersService } from '../services/suppliers.service';
import { PaymentSourcesService, PaymentSourceItem } from 'src/app/accounting/services/payment-sources.service';

@Component({
  selector: 'app-dialog-pay-money-for-supplier',
  templateUrl: './dialog-pay-money-for-supplier.component.html',
  styleUrls: ['./dialog-pay-money-for-supplier.component.css']
})
export class DialogPayMoneyForSupplierComponent implements OnInit {

  /** نفس نمط فاتورة المشتريات: بنك / خزينة / حساب خدمي */
  paymentType: 'bank' | 'safe' | 'service_account' = 'bank';
  bankId: number | null = null;
  safeId: number | null = null;
  serviceAccountId: number | null = null;

  safes: PaymentSourceItem[] = [];
  banks: PaymentSourceItem[] = [];
  serviceAccounts: PaymentSourceItem[] = [];

  constructor(
    public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private supplierService: SuppliersService,
    private paymentSources: PaymentSourcesService
  ) {}

  ngOnInit() {
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
    const payload: any = {
      amount: form.value.amount,
      payment_type: this.paymentType,
    };
    if (this.paymentType === 'safe') {
      payload.safe_id = this.safeId;
    } else if (this.paymentType === 'service_account') {
      payload.service_account_id = this.serviceAccountId;
    } else {
      payload.bank_id = this.bankId;
    }

    this.supplierService.supplierPay(this.data.supplier.id, payload).subscribe((res: any) => {
      if (res.message === 'success') {
        this.onCloseClick();
        this.data.refreshData();
      }
    });
  }
}
