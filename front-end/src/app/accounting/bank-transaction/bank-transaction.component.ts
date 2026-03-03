import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AccountingReportService } from '../services/accounting-report.service';

@Component({
    selector: 'app-bank-transaction',
    templateUrl: './bank-transaction.component.html',
    styleUrls: ['./bank-transaction.component.css']
})
export class BankTransactionComponent implements OnInit {
    bankTransactionForm: FormGroup;
    bankAccounts: any[] = [];
    targetAccounts: any[] = [];
    loading: boolean = false;
    error: string = '';
    success: string = '';

    transactionTypes = [
        { value: 'withdrawal', label: 'سحب من البنك' },
        { value: 'deposit', label: 'إيداع في البنك' },
        { value: 'transfer_out', label: 'تحويل من البنك' },
        { value: 'transfer_in', label: 'تحويل إلى البنك' }
    ];

    constructor(
        private fb: FormBuilder,
        private accountingReportService: AccountingReportService
    ) {
        this.bankTransactionForm = this.fb.group({
            bank_account_id: ['', Validators.required],
            account_id: ['', Validators.required],
            amount: ['', [Validators.required, Validators.min(0.01)]],
            description: ['', Validators.required],
            transaction_type: ['withdrawal', Validators.required],
            reference_number: [''],
            transaction_date: [new Date().toISOString().split('T')[0], Validators.required]
        });
    }

    ngOnInit(): void {
        this.loadBankAccounts();
        this.loadTargetAccounts();
    }

    loadBankAccounts(): void {
        this.accountingReportService.getAccountingTree().subscribe({
            next: (response: any) => {
                this.bankAccounts = this.extractBankAccounts(response);
            },
            error: (err) => {
                console.error('Error loading bank accounts:', err);
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

    extractBankAccounts(accounts: any[]): any[] {
        let bankAccounts: any[] = [];
        
        accounts.forEach(account => {
            // Check if account is bank account
            if (account.type === 'asset' && 
                (account.name?.includes('بنك') || account.name?.includes('Bank') || 
                 account.name_en?.toLowerCase().includes('bank'))) {
                bankAccounts.push(account);
            }
            
            // Recursively check children
            if (account.children && account.children.length > 0) {
                bankAccounts = bankAccounts.concat(this.extractBankAccounts(account.children));
            }
        });
        
        return bankAccounts;
    }

    extractAllAccounts(accounts: any[]): any[] {
        let allAccounts: any[] = [];
        
        accounts.forEach(account => {
            if (account.parent_id) { // Only include leaf accounts
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
        if (this.bankTransactionForm.invalid) {
            this.error = 'يرجى ملء جميع الحقول المطلوبة بشكل صحيح';
            return;
        }

        this.loading = true;
        this.error = '';
        this.success = '';

        const transactionData = {
            ...this.bankTransactionForm.value,
            cash_account_id: this.bankTransactionForm.value.bank_account_id, // Map to cash transaction API
            transaction_type: this.bankTransactionForm.value.transaction_type === 'withdrawal' ? 'cash_out' : 'cash_in'
        };

        this.accountingReportService.processCashTransaction(transactionData).subscribe({
            next: (response: any) => {
                this.loading = false;
                if (response.success) {
                    this.success = response.message;
                    this.bankTransactionForm.reset({
                        transaction_type: 'withdrawal',
                        transaction_date: new Date().toISOString().split('T')[0]
                    });
                } else {
                    this.error = response.message;
                }
            },
            error: (err) => {
                this.loading = false;
                this.error = 'فشلت عملية البنك، يرجى المحاولة مرة أخرى';
            }
        });
    }

    resetForm(): void {
        this.bankTransactionForm.reset({
            transaction_type: 'withdrawal',
            transaction_date: new Date().toISOString().split('T')[0]
        });
        this.error = '';
        this.success = '';
    }
}
