import { Component, OnInit } from '@angular/core';
import { BankService } from '../services/bank.service';

@Component({
   selector: 'app-bank-transfer',
   template: `
    <div class="container-fluid">
       <div class="d-flex justify-content-between align-items-center mb-3">
          <h2>تحويل بين البنوك</h2>
       </div>
       <div class="card p-3">
          <div class="row">
             <div class="col-md-6 mb-3">
                <label>من بنك</label>
                <select class="form-control" [(ngModel)]="transferData.from_id">
                   <option *ngFor="let bank of banks" [value]="bank.id">{{bank.name}} ({{bank.balance}})</option>
                </select>
             </div>
             <div class="col-md-6 mb-3">
                <label>إلى بنك</label>
                <select class="form-control" [(ngModel)]="transferData.to_id">
                   <option *ngFor="let bank of banks" [value]="bank.id">{{bank.name}}</option>
                </select>
             </div>
             <div class="col-md-6 mb-3">
                <label>المبلغ</label>
                <input type="number" class="form-control" [(ngModel)]="transferData.amount" min="1">
             </div>
             <div class="col-md-6 mb-3">
                <label>التاريخ</label>
                <input type="date" class="form-control" [(ngModel)]="transferData.date">
             </div>
             <div class="col-12 mb-3">
                <label>ملاحظات</label>
                <textarea class="form-control" [(ngModel)]="transferData.notes"></textarea>
             </div>
          </div>
          <div class="text-end">
             <button class="btn btn-primary" (click)="submit()" [disabled]="loading">
                <span *ngIf="loading">جاري التحويل...</span>
                <span *ngIf="!loading">تحويل</span>
             </button>
          </div>
       </div>
    </div>
  `,
   styles: []
})
export class BankTransferComponent implements OnInit {
   banks: any[] = [];
   loading = false;
   transferData = {
      type: 'transfer_bank_to_bank',
      from_id: null,
      to_id: null,
      amount: 0,
      date: new Date().toISOString().split('T')[0],
      notes: ''
   };

   constructor(private bankService: BankService) { }

   ngOnInit() {
      this.getBanks();
   }

   getBanks() {
      this.bankService.getAll().subscribe(res => {
         this.banks = res.data || (Array.isArray(res) ? res : []);
      });
   }

   submit() {
      if (!this.transferData.from_id || !this.transferData.to_id || this.transferData.amount <= 0) {
         alert('يرجى تعبئة جميع الحقول بشكل صحيح');
         return;
      }
      if (this.transferData.from_id == this.transferData.to_id) {
         alert('لا يمكن التحويل لنفس البنك');
         return;
      }

      this.loading = true;
      this.bankService.transfer(this.transferData).subscribe({
         next: () => {
            alert('تم عملية التحويل بنجاح');
            this.loading = false;
            this.transferData.amount = 0;
            this.transferData.notes = '';
            this.getBanks(); // refresh balances
         },
         error: (err) => {
            alert(err.error?.message || 'حدث خطأ');
            this.loading = false;
         }
      });
   }
}
