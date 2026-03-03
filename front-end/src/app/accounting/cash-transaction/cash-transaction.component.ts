import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AccountingReportService } from '../services/accounting-report.service';

@Component({
    selector: 'app-cash-transaction',
    templateUrl: './cash-transaction.component.html',
    styleUrls: ['./cash-transaction.component.css']
})
export class CashTransactionComponent implements OnInit {
    cashTransactionForm: FormGroup;
    cashAccounts: any[] = [];
    targetAccounts: any[] = [];
    loading: boolean = false;
    error: string = '';
    success: string = '';

    transactionTypes = [
        { value: 'cash_out', label: 'دفع من الخزينة' },
        { value: 'cash_in', label: 'إيداع في الخزينة' }
    ];

    constructor(
        private fb: FormBuilder,
        private accountingReportService: AccountingReportService
    ) {
        this.cashTransactionForm = this.fb.group({
            cash_account_id: ['', Validators.required],
            account_id: ['', Validators.required],
            amount: ['', [Validators.required, Validators.min(0.01)]],
            description: ['', Validators.required],
            transaction_type: ['cash_out', Validators.required]
        });
    }

    ngOnInit(): void {
        this.loadCashAccounts();
        this.loadTargetAccounts();
    }

    loadCashAccounts(): void {
        this.accountingReportService.getAccountingTree().subscribe({
            next: (response: any) => {
                this.cashAccounts = this.extractCashAccounts(response);
            },
            error: (err) => {
                console.error('Error loading cash accounts:', err);
            }
        });
    }

    loadTargetAccounts(): void {
        this.accountingReportService.getAccountingTree().subscribe({
            next: (response: any) => {
                this.targetAccounts = this.extractAllAccounts(response);
            },
            error: (err) => {
                console.error('Error loading target accounts:', err);
            }
        });
    }

    extractCashAccounts(accounts: any[]): any[] {
        let cashAccounts: any[] = [];
        
        accounts.forEach(account => {
            // Check if account is cash/bank account
            if (account.type === 'asset' && 
                (account.name?.includes('خزينة') || account.name?.includes('بنك') || 
                 account.name_en?.toLowerCase().includes('cash') || account.name_en?.toLowerCase().includes('bank'))) {
                cashAccounts.push(account);
            }
            
            // Recursively check children
            if (account.children && account.children.length > 0) {
                cashAccounts = cashAccounts.concat(this.extractCashAccounts(account.children));
            }
        });
        
        return cashAccounts;
    }

    extractAllAccounts(accounts: any[]): any[] {
        let allAccounts: any[] = [];
        
        accounts.forEach(account => {
            if (account.parent_id) { // Only include leaf accounts (those with parents)
                allAccounts.push(account);
            }
            
            // Recursively check children
            if (account.children && account.children.length > 0) {
                allAccounts = allAccounts.concat(this.extractAllAccounts(account.children));
            }
        });
        
        return allAccounts;
    }

    onSubmit(): void {
        if (this.cashTransactionForm.invalid) {
            this.error = 'يرجى ملء جميع الحقول المطلوبة بشكل صحيح';
            return;
        }

        this.loading = true;
        this.error = '';
        this.success = '';

        const transactionData = this.cashTransactionForm.value;

        this.accountingReportService.processCashTransaction(transactionData).subscribe({
            next: (response: any) => {
                this.loading = false;
                if (response.success) {
                    this.success = response.message;
                    this.cashTransactionForm.reset({
                        transaction_type: 'cash_out'
                    });
                } else {
                    this.error = response.message;
                }
            },
            error: (err) => {
                this.loading = false;
                this.error = 'فشلت عملية الدفع، يرجى المحاولة مرة أخرى';
            }
        });
    }

    resetForm(): void {
        this.cashTransactionForm.reset({
            transaction_type: 'cash_out'
        });
        this.error = '';
        this.success = '';
    }
}
