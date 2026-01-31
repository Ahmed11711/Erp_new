import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { environment } from 'src/env/env';
import { VoucherService } from '../../accounting/services/voucher.service';
import { BankService } from '../../accounting/services/bank.service';
import { SafeService } from '../../accounting/services/safe.service';

@Component({
   selector: 'app-add-capital',
   template: `
    <div class="container-fluid">
       <div class="d-flex justify-content-between align-items-center mb-3">
          <h2>إضافة مبلغ رأس مال</h2>
       </div>
       <div class="card p-3">
          <div class="row">
             <div class="col-md-6 mb-3">
                <label>التاريخ</label>
                <input type="date" class="form-control" [(ngModel)]="data.date">
             </div>
             
             <div class="col-md-6 mb-3">
                <label>المبلغ</label>
                <input type="number" class="form-control" [(ngModel)]="data.amount" min="1">
             </div>

             <div class="col-md-6 mb-3">
                <label>نوع الإيداع</label>
                <select class="form-control" [(ngModel)]="data.target_type" (change)="data.target_id = null">
                   <option value="bank">بنك</option>
                   <option value="safe">خزينة</option>
                </select>
             </div>

             <div class="col-md-6 mb-3" *ngIf="data.target_type === 'bank'">
                <label>البنك</label>
                <select class="form-control" [(ngModel)]="data.target_id">
                   <option *ngFor="let bank of banks" [value]="bank.id">{{bank.name}}</option>
                </select>
             </div>

             <div class="col-md-6 mb-3" *ngIf="data.target_type === 'safe'">
                <label>الخزينة</label>
                <select class="form-control" [(ngModel)]="data.target_id">
                   <option *ngFor="let safe of safes" [value]="safe.id">{{safe.name}}</option>
                </select>
             </div>

             <div class="col-md-6 mb-3">
                <label>حساب حقوق الملكية (رأس المال)</label>
                <select class="form-control" [(ngModel)]="data.equity_account_id">
                   <option *ngFor="let acc of equityAccounts" [value]="acc.id">{{acc.name}}</option>
                </select>
             </div>

             <div class="col-12 mb-3">
                <label>ملاحظات</label>
                <textarea class="form-control" [(ngModel)]="data.notes"></textarea>
             </div>
          </div>
          <div class="text-end">
             <button class="btn btn-primary" (click)="save()" [disabled]="loading">
                <span *ngIf="loading">جاري الحفظ...</span>
                <span *ngIf="!loading">حفظ</span>
             </button>
          </div>
       </div>
    </div>
  `,
   styles: []
})
export class AddCapitalComponent implements OnInit {
   data: any = {
      date: new Date().toISOString().split('T')[0],
      amount: 0,
      target_type: 'bank',
      target_id: null,
      equity_account_id: null,
      notes: ''
   };

   banks: any[] = [];
   safes: any[] = [];
   equityAccounts: any[] = [];
   loading = false;
   private apiUrl = environment.Url + '/accounting/capitals';

   constructor(
      private http: HttpClient,
      private router: Router,
      private bankService: BankService,
      private safeService: SafeService,
      private voucherService: VoucherService
   ) { }

   ngOnInit() {
      this.getBanks();
      this.getSafes();
      this.getEquityAccounts();
   }

   getBanks() {
      this.bankService.getAll().subscribe(res => {
         this.banks = res.data || (Array.isArray(res) ? res : []);
      });
   }

   getSafes() {
      this.safeService.getAll().subscribe(res => {
         this.safes = res.data || (Array.isArray(res) ? res : []);
      });
   }

   getEquityAccounts() {
      // We need filtering by type or parent. 
      // For now get all and maybe filter on frontend or just show all for selection.
      // Usually Equity is liability-like/credit balance.
      this.voucherService.getAccounts().subscribe(res => {
         const all = Array.isArray(res) ? res : (res.data || []);
         // Filter assuming there is a type property or just show all
         this.equityAccounts = all.filter((a: any) => a.type === 'equity' || a.account_type === 'equity' || a.type === 'liabilities');
         // If filtering yields nothing, show all as fallback
         if (this.equityAccounts.length === 0) this.equityAccounts = all;
      });
   }

   save() {
      if (!this.data.amount || !this.data.target_id || !this.data.equity_account_id) {
         alert('الرجاء تعبئة جميع الحقول المطلوبة');
         return;
      }

      this.loading = true;
      this.http.post(this.apiUrl, this.data).subscribe({
         next: (res) => {
            alert('تمت الإضافة بنجاح');
            this.router.navigate(['/dashboard/financial/capitals']);
         },
         error: (err) => {
            alert(err.error?.message || 'حدث خطأ');
            this.loading = false;
         }
      });
   }
}
