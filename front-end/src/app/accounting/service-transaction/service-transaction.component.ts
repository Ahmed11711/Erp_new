import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AccountingReportService } from '../services/accounting-report.service';

@Component({
    selector: 'app-service-transaction',
    templateUrl: './service-transaction.component.html',
    styleUrls: ['./service-transaction.component.css']
})
export class ServiceTransactionComponent implements OnInit {
    serviceTransactionForm: FormGroup;
    serviceAccounts: any[] = [];
    targetAccounts: any[] = [];
    loading: boolean = false;
    error: string = '';
    success: string = '';

    transactionTypes = [
        { value: 'payment', label: 'دفع خدمة' },
        { value: 'receipt', label: 'استلام خدمة' },
        { value: 'expense', label: 'مصروف خدمة' },
        { value: 'income', label: 'إيراد خدمة' }
    ];

    constructor(
        private fb: FormBuilder,
        private accountingReportService: AccountingReportService
    ) {
        this.serviceTransactionForm = this.fb.group({
            service_account_id: ['', Validators.required],
            account_id: ['', Validators.required],
            amount: ['', [Validators.required, Validators.min(0.01)]],
            description: ['', Validators.required],
            transaction_type: ['payment', Validators.required],
            service_date: [new Date().toISOString().split('T')[0], Validators.required],
            invoice_number: ['']
        });
    }

    ngOnInit(): void {
        this.loadServiceAccounts();
        this.loadTargetAccounts();
    }

    loadServiceAccounts(): void {
        this.accountingReportService.getAccountingTree().subscribe({
            next: (response: any) => {
                this.serviceAccounts = this.extractServiceAccounts(response);
            },
            error: (err) => {
                console.error('Error loading service accounts:', err);
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

    extractServiceAccounts(accounts: any[]): any[] {
        let serviceAccounts: any[] = [];
        
        accounts.forEach(account => {
            // Check if account is service account
            if (account.type === 'expense' || account.type === 'revenue' || 
                account.name?.includes('خدمة') || account.name?.includes('خدمات') ||
                account.name_en?.toLowerCase().includes('service')) {
                serviceAccounts.push(account);
            }
            
            // Recursively check children
            if (account.children && account.children.length > 0) {
                serviceAccounts = serviceAccounts.concat(this.extractServiceAccounts(account.children));
            }
        });
        
        return serviceAccounts;
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
        if (this.serviceTransactionForm.invalid) {
            this.error = 'يرجى ملء جميع الحقول المطلوبة بشكل صحيح';
            return;
        }

        this.loading = true;
        this.error = '';
        this.success = '';

        const transactionData = {
            ...this.serviceTransactionForm.value,
            cash_account_id: this.serviceTransactionForm.value.service_account_id,
            transaction_type: this.serviceTransactionForm.value.transaction_type === 'payment' || 
                              this.serviceTransactionForm.value.transaction_type === 'expense' ? 'cash_out' : 'cash_in'
        };

        this.accountingReportService.processCashTransaction(transactionData).subscribe({
            next: (response: any) => {
                this.loading = false;
                if (response.success) {
                    this.success = response.message;
                    this.serviceTransactionForm.reset({
                        transaction_type: 'payment',
                        service_date: new Date().toISOString().split('T')[0]
                    });
                } else {
                    this.error = response.message;
                }
            },
            error: (err) => {
                this.loading = false;
                this.error = 'فشلت معاملة الحساب الخدمي، يرجى المحاولة مرة أخرى';
            }
        });
    }

    resetForm(): void {
        this.serviceTransactionForm.reset({
            transaction_type: 'payment',
            service_date: new Date().toISOString().split('T')[0]
        });
        this.error = '';
        this.success = '';
    }
}
