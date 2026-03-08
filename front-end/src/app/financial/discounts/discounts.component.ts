import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { MatSnackBar } from '@angular/material/snack-bar';
import { CimmitmentService } from '../services/cimmitment.service';
import { environment } from 'src/env/env';

@Component({
  selector: 'app-discounts',
  templateUrl: './discounts.component.html',
  styleUrls: ['./discounts.component.css']
})
export class DiscountsComponent implements OnInit {
  data: any[] = [];
  totalDeserved = 0;
  totalPaid = 0;
  totalRemaining = 0;

  // Payment UI state
  paymentSources: any = { safes: [], banks: [], service_accounts: [] };
  sourcesLoaded = false;
  selectedCommitment: any | null = null;
  payType: 'safe' | 'bank' | 'service_account' = 'safe';
  paySourceId: number | null = null;
  payAmount: number | null = null;
  payDesc: string | null = null;
  loadingPay = false;
  errorMsg: string | null = null;

  constructor(
    private cimmitmentService: CimmitmentService,
    private http: HttpClient,
    private snackBar: MatSnackBar
  ) {}

  ngOnInit(): void {
    this.refreshData();
    this.loadPaymentSources();
  }

  refreshData(): void {
    this.cimmitmentService.data().subscribe({
      next: (result: any) => {
        this.data = Array.isArray(result) ? result : (result?.data || []);
        this.totalDeserved = 0;
        this.totalPaid = 0;
        this.totalRemaining = 0;
        this.data.forEach((elm: any) => {
          this.totalDeserved += parseFloat(elm.deserved_amount) || 0;
          this.totalPaid += parseFloat(elm.paid_amount) || 0;
          const remaining = elm.remaining_amount ?? (parseFloat(elm.deserved_amount) - parseFloat(elm.paid_amount || 0));
          this.totalRemaining += remaining || 0;
        });
      }
    });
  }

  loadPaymentSources(): void {
    this.http.get<any>(`${environment.Url}/accounting/payment-sources`).subscribe({
      next: (res) => {
        this.paymentSources = res || { safes: [], banks: [], service_accounts: [] };
        this.sourcesLoaded = true;
      },
      error: () => {
        this.paymentSources = { safes: [], banks: [], service_accounts: [] };
        this.sourcesLoaded = true;
      }
    });
  }

  openPay(commitment: any): void {
    this.selectedCommitment = commitment;
    this.payAmount = Math.max(0, (commitment.remaining_amount ?? (commitment.deserved_amount - (commitment.paid_amount || 0))));
    this.payType = 'safe';
    this.paySourceId = null;
    this.payDesc = null;
    this.errorMsg = null;
  }

  closePay(): void {
    this.selectedCommitment = null;
    this.payAmount = null;
    this.paySourceId = null;
    this.payDesc = null;
    this.errorMsg = null;
  }

  get currentSources(): any[] {
    if (!this.paymentSources) return [];
    if (this.payType === 'safe') return this.paymentSources.safes || [];
    if (this.payType === 'bank') return this.paymentSources.banks || [];
    return this.paymentSources.service_accounts || [];
  }

  submitPay(): void {
    if (!this.selectedCommitment) { return; }
    const remaining = this.selectedCommitment.remaining_amount ?? (this.selectedCommitment.deserved_amount - (this.selectedCommitment.paid_amount || 0));
    if (!this.payAmount || this.payAmount <= 0) {
      this.errorMsg = 'أدخل مبلغاً صالحاً';
      return;
    }
    if (this.payAmount > remaining) {
      this.errorMsg = 'المبلغ يتجاوز المتبقي';
      return;
    }
    const source = this.currentSources.find(s => s.id === this.paySourceId);
    if (!source?.account_id) {
      this.errorMsg = 'اختر مصدر الدفع';
      return;
    }
    this.loadingPay = true;
    this.errorMsg = null;
    this.cimmitmentService.pay(this.selectedCommitment.id, {
      amount: this.payAmount,
      cash_account_id: source.account_id,
      payment_source_type: this.payType,
      payment_source_id: source.id,
      description: this.payDesc || undefined
    }).subscribe({
      next: () => {
        this.loadingPay = false;
        this.closePay();
        this.refreshData();
        this.snackBar.open('تم تسجيل الدفع بنجاح', 'إغلاق', { duration: 3000 });
      },
      error: (err) => {
        this.loadingPay = false;
        this.errorMsg = err?.error?.message || 'فشل تنفيذ السداد';
      }
    });
  }
}
