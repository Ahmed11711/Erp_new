import { Component, Inject } from '@angular/core';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { Router } from '@angular/router';
import { CompaniesService } from '../services/companies.service';
import { PaymentSourcesService, PaymentSourceItem } from 'src/app/accounting/services/payment-sources.service';

@Component({
  selector: 'app-dialog-collect-from-customer-company',
  templateUrl: './dialog-collect-from-customer-company.component.html',
  styleUrls: ['./dialog-collect-from-customer-company.component.css']
})
export class DialogCollectFromCustomerCompanyComponent {

  paymentType: 'safe' | 'bank' | 'service_account' = 'bank';
  safes: PaymentSourceItem[] = [];
  banks: PaymentSourceItem[] = [];
  serviceAccounts: PaymentSourceItem[] = [];
  sourceId: number | null = null;

  constructor(public dialogRef: MatDialogRef<DialogCollectFromCustomerCompanyComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private companyService: CompaniesService,
    private paymentSources: PaymentSourcesService,
    private route: Router
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
    const payload: any = { amount: form.value.amount };
    if (this.paymentType === 'safe') payload.safe_id = this.sourceId;
    else if (this.paymentType === 'service_account') payload.service_account_id = this.sourceId;
    else payload.bank_id = this.sourceId;
    payload.payment_type = this.paymentType;

    this.companyService.companyCollect(this.data.company.id, payload).subscribe((res: any) => {
      if (res.message === 'success') {
        this.onCloseClick();
        this.data.refreshData();
      }
    });
  }
}
