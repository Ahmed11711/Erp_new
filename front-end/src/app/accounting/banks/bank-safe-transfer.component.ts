import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { BankService } from '../services/bank.service';
import { SafeService } from '../services/safe.service';

@Component({
    selector: 'app-bank-safe-transfer',
    template: `
    <div class="container-fluid">
       <div class="d-flex justify-content-between align-items-center mb-3">
          <h2>{{ pageTitle }}</h2>
       </div>
       <div class="card p-3">
          <div class="row">
             <ng-container *ngIf="mode === 'bank_to_safe'">
                 <div class="col-md-6 mb-3">
                    <label>من بنك</label>
                    <select class="form-control" [(ngModel)]="fromId">
                       <option *ngFor="let bank of banks" [value]="bank.id">{{bank.name}} ({{bank.balance}})</option>
                    </select>
                 </div>
                 <div class="col-md-6 mb-3">
                    <label>إلى خزينة</label>
                    <select class="form-control" [(ngModel)]="toId">
                       <option *ngFor="let safe of safes" [value]="safe.id">{{safe.name}} ({{safe.balance}})</option>
                    </select>
                 </div>
             </ng-container>

             <ng-container *ngIf="mode === 'safe_to_bank'">
                 <div class="col-md-6 mb-3">
                    <label>من خزينة</label>
                    <select class="form-control" [(ngModel)]="fromId">
                       <option *ngFor="let safe of safes" [value]="safe.id">{{safe.name}} ({{safe.balance}})</option>
                    </select>
                 </div>
                 <div class="col-md-6 mb-3">
                    <label>إلى بنك</label>
                    <select class="form-control" [(ngModel)]="toId">
                       <option *ngFor="let bank of banks" [value]="bank.id">{{bank.name}} ({{bank.balance}})</option>
                    </select>
                 </div>
             </ng-container>

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
                <span *ngIf="loading">جاري التحويل...</span>
                <span *ngIf="!loading">تحويل</span>
             </button>
          </div>
       </div>
    </div>
  `,
    styles: []
})
export class BankSafeTransferComponent implements OnInit {

    mode: 'bank_to_safe' | 'safe_to_bank' = 'bank_to_safe';
    pageTitle = 'تحويل من بنك إلى خزينة';

    banks: any[] = [];
    safes: any[] = [];
    loading = false;

    fromId: any = null;
    toId: any = null;
    amount: number = 0;
    date: string = new Date().toISOString().split('T')[0];
    notes: string = '';

    constructor(
        private route: ActivatedRoute,
        private bankService: BankService,
        private safeService: SafeService
    ) { }

    ngOnInit() {
        // Determine mode based on route path
        const path = this.route.snapshot.url[0].path;
        if (path === 'transfer-from-safe') {
            this.mode = 'safe_to_bank';
            this.pageTitle = 'تحويل من خزينة إلى بنك';
        } else {
            this.mode = 'bank_to_safe';
            this.pageTitle = 'تحويل من بنك إلى خزينة';
        }

        this.getData();
    }

    getData() {
        this.bankService.getAll().subscribe(res => {
            this.banks = res.data || (Array.isArray(res) ? res : []);
        });
        this.safeService.getAll().subscribe(res => {
            this.safes = res.data || (Array.isArray(res) ? res : []);
        });
    }

    submit() {
        if (!this.fromId || !this.toId || this.amount <= 0) {
            alert('يرجى تعبئة جميع الحقول بشكل صحيح');
            return;
        }

        this.loading = true;
        const payload = {
            type: this.mode === 'bank_to_safe' ? 'transfer_bank_to_safe' : 'transfer_safe_to_bank',
            from_id: this.fromId,
            to_id: this.toId,
            amount: this.amount,
            date: this.date,
            notes: this.notes
        };

        this.bankService.transfer(payload).subscribe({
            next: () => {
                alert('تم عملية التحويل بنجاح');
                this.loading = false;
                this.amount = 0;
                this.notes = '';
                this.getData(); // refresh balances
            },
            error: (err) => {
                alert(err.error?.message || 'حدث خطأ');
                this.loading = false;
            }
        });
    }
}
