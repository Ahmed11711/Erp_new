import { Component, Inject } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { SuppliersService } from '../services/suppliers.service';
import { PaymentSourcesService, PaymentSourceItem } from 'src/app/accounting/services/payment-sources.service';

@Component({
  selector: 'app-dialog-pay-money-for-supplier',
  templateUrl: './dialog-pay-money-for-supplier.component.html',
  styleUrls: ['./dialog-pay-money-for-supplier.component.css']
})
export class DialogPayMoneyForSupplierComponent {

  paymentType: 'safe' | 'bank' | 'service_account' = 'bank';
  safes: PaymentSourceItem[] = [];
  banks: PaymentSourceItem[] = [];
  serviceAccounts: PaymentSourceItem[] = [];
  sourceId: number | null = null;

  constructor(public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
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

  get sourceList(): PaymentSourceItem[] {
    if (this.paymentType === 'safe') return this.safes;
    if (this.paymentType === 'service_account') return this.serviceAccounts;
    return this.banks;
  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

  get canSubmit(): boolean {
    return !!this.sourceId && this.sourceId > 0;
  }

  submit(form: any) {
    const payload: any = { amount: form.value.amount, payment_type: this.paymentType };
    if (this.paymentType === 'safe') payload.safe_id = this.sourceId;
    else if (this.paymentType === 'service_account') payload.service_account_id = this.sourceId;
    else payload.bank_id = this.sourceId;

    this.supplierService.supplierPay(this.data.supplier.id, payload).subscribe((res: any) => {
      if (res.message === 'success') {
        this.onCloseClick();
        this.data.refreshData();
      }
    });
  }
}
