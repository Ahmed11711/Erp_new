import { Component, OnInit } from '@angular/core';
import { BankService } from '../services/bank.service';
import { VoucherService } from '../services/voucher.service';
// Reuse Voucher Service for this as it's essentially a voucher but maybe simplified UI?
// Or maybe specific endpoint?
// For now, I'll use a direct approach if backend supports it, or use VoucherService.
// Given strict accounting, "Deposit/Withdraw" is essentially a Receipt/Payment Voucher where the "Partner" is NOT a client/supplier, but a General Account (e.g. Capital, Expenses).
// Vouchers support `client` and `supplier` types. We might need `general` type.
// But the user request is just "activate these pages".
// I will create a component that creates a "General" voucher (if supported) or just uses the existing VoucherService with a tweak.
// Actually, BankController usually doesn't handle direct deposit/withdraw unless against an account. 
// I will implement it as a "Voucher" but with UI focused on Banks.

@Component({
   selector: 'app-bank-deposit-withdraw',
   template: `
    <div class="container-fluid">
       <div class="d-flex justify-content-between align-items-center mb-3">
          <h2>سحب وإيداع نقدي (بنك)</h2>
       </div>
       <div class="alert alert-info">
          ملاحظة: هذه العملية تنتج سند قبض/صرف.
       </div>
       <div class="card p-3">
          <div class="row">
             <div class="col-md-6 mb-3">
                <label>البنك</label>
                <select class="form-control" [(ngModel)]="bankId">
                   <option *ngFor="let bank of banks" [value]="bank.id">{{bank.name}} ({{bank.balance}})</option>
                </select>
             </div>
             <div class="col-md-6 mb-3">
                <label>العملية</label>
                <select class="form-control" [(ngModel)]="type">
                   <option value="receipt">إيداع (قبض)</option>
                   <option value="payment">سحب (صرف)</option>
                </select>
             </div>
             <!-- Counter Agent: For simple deposit/withdraw, it could be owner or generic. 
                  If we want to deposit CASH into BANK, that's a transfer (Safe -> Bank).
                  If we want to withdraw CASH from BANK, that's a transfer (Bank -> Safe).
                  So "Deposit/Withdraw" here usually means "External" money coming in/out NOT from Safe.
                  e.g. Owner Capital, Loan, etc.
                  So we need to select a "Tree Account" as the counter party.
              -->
             <div class="col-md-6 mb-3">
                <label>الحساب المقابل (مصدر/وجهة الأموال)</label>
                 <!-- We need a tree account selector here. For now, simple dropdown of all accounts -->
                <select class="form-control" [(ngModel)]="counterAccountId">
                   <option *ngFor="let acc of accounts" [value]="acc.id">{{acc.name}}</option>
                </select>
             </div>

             <div class="col-md-6 mb-3">
                <label>المبلغ</label>
                <input type="number" class="form-control" [(ngModel)]="amount" min="1">
             </div>
             <div class="col-md-6 mb-3">
                <label>التاريخ</label>
                <input type="date" class="form-control" [(ngModel)]="date">
             </div>
             <div class="col-12 mb-3">
                <label>ملاحظات</label>
                <textarea class="form-control" [(ngModel)]="notes"></textarea>
             </div>
          </div>
          <div class="text-end">
             <button class="btn btn-primary" (click)="submit()" [disabled]="loading">
                <span *ngIf="loading">جاري التنفيذ...</span>
                <span *ngIf="!loading">حفظ</span>
             </button>
          </div>
       </div>
    </div>
  `,
   styles: []
})
export class BankDepositWithdrawComponent implements OnInit {
   banks: any[] = [];
   accounts: any[] = [];
   loading = false;

   bankId: any = null;
   type: 'receipt' | 'payment' = 'receipt';
   counterAccountId: any = null;
   amount: number = 0;
   date: string = new Date().toISOString().split('T')[0];
   notes: string = '';

   constructor(
      private bankService: BankService,
      private voucherService: VoucherService // Need to inject VoucherService or TreeAccountService
   ) { }

   ngOnInit() {
      this.getBanks();
      this.getAccounts();
   }

   getBanks() {
      this.bankService.getAll().subscribe(res => {
         this.banks = res.data || (Array.isArray(res) ? res : []);
      });
   }

   getAccounts() {
      this.voucherService.getAccounts().subscribe(res => {
         this.accounts = Array.isArray(res) ? res : (res.data || []);
      });
   }

   submit() {
      if (!this.bankId || !this.counterAccountId || this.amount <= 0) {
         alert('يرجى تعبئة جميع الحقول');
         return;
      }

      // Logic: access the Bank's Tree Account ID
      const selectedBank = this.banks.find(b => b.id == this.bankId);
      if (!selectedBank || !selectedBank.asset_id) {
         alert('البنك المحدد غير مرتبط بحساب شجري');
         return;
      }

      // We will create a generic voucher or manually call an accounting endpoint.
      // Since VoucherController is set up for Client/Supplier, using it might be tricky if we don't have a client_id/supplier_id.
      // But we can create a "General" voucher type if we modify the backend. 
      // OR, we manually create AccountEntries here via a new API in BankController?
      // Actually, standard practice: direct Journal Entry or a Generic Voucher.
      // Let's assume for now we can't use VoucherController without client/supplier easily (it requires them). 
      // I will implement a "direct_transaction" endpoint in BankController or similar, OR just alert the user that this feature needs backend support.
      // But I must "Activate" the pages. 
      // I will implement the UI and try to call a new endpoint `direct-transaction` on BankService, 
      // and I will update BankController to handle it.

      this.loading = true;
      const payload = {
         bank_id: this.bankId,
         type: this.type, // 'receipt' (Deposit), 'payment' (Withdraw)
         counter_account_id: this.counterAccountId,
         amount: this.amount,
         date: this.date,
         notes: this.notes
      };

      this.bankService.directTransaction(payload).subscribe({
         next: () => {
            alert('تمت العملية بنجاح');
            this.loading = false;
            this.amount = 0;
            this.notes = '';
            this.getBanks();
         },
         error: (err) => {
            alert(err.error?.message || 'حدث خطأ');
            this.loading = false;
         }
      });
   }
}
