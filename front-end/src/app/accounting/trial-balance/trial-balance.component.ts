import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { AccountingReportService } from '../services/accounting-report.service';
import * as XLSX from 'xlsx';

@Component({
    selector: 'app-trial-balance',
    templateUrl: './trial-balance.component.html',
    styleUrls: ['./trial-balance.component.css']
})
export class TrialBalanceComponent implements OnInit {

    trialBalanceData: any[] = [];
    totals: any = {};
    loading: boolean = false;
    error: string = '';

    filterForm: FormGroup = new FormGroup({
        date_from: new FormControl(null),
        date_to: new FormControl(null),
        search: new FormControl(''),
        account_type: new FormControl(''),
        level: new FormControl('')
    });

    displayedColumns: string[] = [
        'account_code',
        'account_name',
        'opening_debit',
        'opening_credit',
        'movement_debit',
        'movement_credit',
        'closing_debit',
        'closing_credit'
    ];

    accountTypes = [
        { value: '', label: 'جميع الأنواع' },
        { value: 'asset', label: 'أصول' },
        { value: 'liability', label: 'التزامات' },
        { value: 'equity', label: 'حقوق ملكية' },
        { value: 'revenue', label: 'إيرادات' },
        { value: 'expense', label: 'مصروفات' }
    ];

    levels = [
        { value: '', label: 'جميع المستويات' },
        { value: 1, label: 'المستوى 1' },
        { value: 2, label: 'المستوى 2' },
        { value: 3, label: 'المستوى 3' },
        { value: 4, label: 'المستوى 4' },
        { value: 5, label: 'المستوى 5' }
    ];

    constructor(
        private accountingReportService: AccountingReportService
    ) {
        // Set default dates
        const today = new Date();
        const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

        this.filterForm.patchValue({
            date_from: this.formatDate(firstDayOfMonth),
            date_to: this.formatDate(today)
        });
    }

    ngOnInit(): void {
        this.loadTrialBalance();
    }

    loadTrialBalance(): void {
        this.loading = true;
        this.error = '';

        const params: any = {};

        if (this.filterForm.value.date_from) {
            params.date_from = this.filterForm.value.date_from;
        }

        if (this.filterForm.value.date_to) {
            params.date_to = this.filterForm.value.date_to;
        }

        if (this.filterForm.value.search) {
            params.search = this.filterForm.value.search;
        }

        if (this.filterForm.value.account_type) {
            params.account_type = this.filterForm.value.account_type;
        }

        if (this.filterForm.value.level) {
            params.level = this.filterForm.value.level;
        }

        this.accountingReportService.getTrialBalance(params).subscribe({
            next: (response: any) => {
                this.trialBalanceData = response.data || [];
                this.totals = response.totals || {};
                this.loading = false;
            },
            error: (err) => {
                console.error('Error loading trial balance:', err);
                this.error = 'حدث خطأ أثناء تحميل ميزان المراجعة';
                this.loading = false;
            }
        });
    }

    onFilter(): void {
        this.loadTrialBalance();
    }

    onReset(): void {
        const today = new Date();
        const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

        this.filterForm.patchValue({
            date_from: this.formatDate(firstDayOfMonth),
            date_to: this.formatDate(today),
            search: '',
            account_type: '',
            level: ''
        });

        this.loadTrialBalance();
    }

    exportToExcel(): void {
        const worksheet = XLSX.utils.json_to_sheet(
            this.trialBalanceData.map(item => ({
                'كود الحساب': item.account_code,
                'اسم الحساب': item.account_name,
                'رصيد أول المدة - مدين': item.opening_debit,
                'رصيد أول المدة - دائن': item.opening_credit,
                'الحركة - مدين': item.movement_debit,
                'الحركة - دائن': item.movement_credit,
                'رصيد آخر المدة - مدين': item.closing_debit,
                'رصيد آخر المدة - دائن': item.closing_credit
            }))
        );

        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'ميزان المراجعة');

        const fileName = `trial_balance_${this.filterForm.value.date_from}_${this.filterForm.value.date_to}.xlsx`;
        XLSX.writeFile(workbook, fileName);
    }

    print(): void {
        window.print();
    }

    private formatDate(date: Date): string {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    formatNumber(num: number): string {
        return num ? num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00';
    }
}
