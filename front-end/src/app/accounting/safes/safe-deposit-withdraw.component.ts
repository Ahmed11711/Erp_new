import { Component, OnInit } from '@angular/core';
import { SafeService } from '../services/safe.service';
import { TreeAccountService } from '../services/tree-account.service';

@Component({
    selector: 'app-safe-deposit-withdraw',
    template: `
    <div class="container-fluid">
       <div class="d-flex justify-content-between align-items-center mb-3">
          <h2>سحب وإيداع نقدي (خزينة)</h2>
       </div>
       <div class="alert alert-info">
          ملاحظة: هذه العملية تنتج قيد يومي وتسجل حركة في الخزينة والحساب المقابل.
       </div>
       <div class="card p-3">
          <div class="row">
             <div class="col-md-6 mb-3">
                <label>الخزينة</label>
                <select class="form-control" [(ngModel)]="safeId">
                   <option *ngFor="let safe of safes" [value]="safe.id">{{safe.name}} ({{safe.balance}})</option>
                </select>
             </div>
             <div class="col-md-6 mb-3">
                <label>العملية</label>
                <select class="form-control" [(ngModel)]="type">
                   <option value="receipt">إيداع (قبض)</option>
                   <option value="payment">سحب (صرف)</option>
                </select>
             </div>
             <div class="col-md-6 mb-3">
                <label>الحساب المقابل (مصدر/وجهة الأموال)</label>
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
export class SafeDepositWithdrawComponent implements OnInit {
    safes: any[] = [];
    accounts: any[] = [];
    loading = false;

    safeId: any = null;
    type: 'receipt' | 'payment' = 'receipt';
    counterAccountId: any = null;
    amount: number = 0;
    date: string = new Date().toISOString().split('T')[0];
    notes: string = '';

    constructor(
        private safeService: SafeService,
        private treeAccountService: TreeAccountService
    ) { }

    ngOnInit() {
        this.getSafes();
        this.getAccounts();
    }

    getSafes() {
        this.safeService.getAll().subscribe(res => {
            this.safes = res.data || (Array.isArray(res) ? res : []);
        });
    }

    getAccounts() {
        this.treeAccountService.getAll().subscribe(res => {
            this.accounts = Array.isArray(res) ? res : (res.data || []);
            // If data is paginated/nested, adjust accordingly. SafesComponent checks res.data or res array.
            if (res.data) {
                this.accounts = Array.isArray(res.data) ? res.data : [res.data];
            }
        });
    }

    submit() {
        if (!this.safeId || !this.counterAccountId || this.amount <= 0) {
            alert('يرجى تعبئة جميع الحقول');
            return;
        }

        this.loading = true;
        const payload = {
            safe_id: this.safeId,
            type: this.type,
            counter_account_id: this.counterAccountId,
            amount: this.amount,
            date: this.date,
            notes: this.notes
        };

        // Ensure SafeService has directTransaction method OR use generic http call if missing.
        // BankService had it. I need to make sure SafeService has it. 
        // I checked SafeService in previous step (File 159), it DOES NOT have directTransaction method.
        // I need to add it to SafeService as well.
        // For now, I will use 'any' cast or add it next.

        (this.safeService as any).directTransaction(payload).subscribe({
            next: () => {
                alert('تمت العملية بنجاح');
                this.loading = false;
                this.amount = 0;
                this.notes = '';
                this.getSafes();
            },
            error: (err: any) => {
                alert(err.error?.message || 'حدث خطأ');
                this.loading = false;
            }
        });
    }
}
