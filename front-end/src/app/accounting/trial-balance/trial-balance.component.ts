import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl } from '@angular/forms';
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
    validation: any = {};
    loading = false;
    error = '';
    showValidation = false;

    get isBalanced(): boolean {
        if (!this.totals) return true;
        return (
            (this.totals.opening_difference || 0) === 0 &&
            (this.totals.movement_difference || 0) === 0 &&
            (this.totals.closing_difference || 0) === 0
        );
    }

    filterForm = new FormGroup({
        date_from: new FormControl<string | null>(null),
        date_to: new FormControl<string | null>(null),
        search: new FormControl(''),
        account_type: new FormControl(''),
        level: new FormControl('')
    });

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

    constructor(private accountingReportService: AccountingReportService) {
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

        const params: Record<string, any> = {};
        const formVal = this.filterForm.value;

        if (formVal.date_from) params['date_from'] = formVal.date_from;
        if (formVal.date_to) params['date_to'] = formVal.date_to;
        if (formVal.search) params['search'] = formVal.search;
        if (formVal.account_type) params['account_type'] = formVal.account_type;
        if (formVal.level) params['level'] = formVal.level;

        this.accountingReportService.getTrialBalance(params).subscribe({
            next: (response: any) => {
                this.trialBalanceData = response.data || [];
                this.totals = response.totals || {};
                this.validation = response.validation || {};
                this.loading = false;
                this.showValidation = true;
            },
            error: (err) => {
                console.error('Error loading trial balance:', err);
                this.error = err?.error?.message || 'حدث خطأ أثناء تحميل ميزان المراجعة';
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
        const data = this.trialBalanceData.map(item => ({
            'كود الحساب': item.account_code,
            'اسم الحساب': item.account_name,
            'رصيد أول المدة - مدين': item.opening_debit,
            'رصيد أول المدة - دائن': item.opening_credit,
            'الحركة - مدين': item.movement_debit,
            'الحركة - دائن': item.movement_credit,
            'رصيد آخر المدة - مدين': item.closing_debit,
            'رصيد آخر المدة - دائن': item.closing_credit
        }));

        data.push({
            'كود الحساب': '',
            'اسم الحساب': 'الإجمالي',
            'رصيد أول المدة - مدين': this.totals.opening_debit,
            'رصيد أول المدة - دائن': this.totals.opening_credit,
            'الحركة - مدين': this.totals.movement_debit,
            'الحركة - دائن': this.totals.movement_credit,
            'رصيد آخر المدة - مدين': this.totals.closing_debit,
            'رصيد آخر المدة - دائن': this.totals.closing_credit
        });

        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'ميزان المراجعة');

        const dateFrom = this.filterForm.value.date_from || '';
        const dateTo = this.filterForm.value.date_to || '';
        XLSX.writeFile(wb, `ميزان_المراجعة_${dateFrom}_${dateTo}.xlsx`);
    }

    print(): void {
        window.print();
    }

    trackByAccount(index: number, item: any): any {
        return item?.account_id;
    }

    isZeroRow(account: any): boolean {
        return (
            account.opening_debit === 0 &&
            account.opening_credit === 0 &&
            account.movement_debit === 0 &&
            account.movement_credit === 0 &&
            account.closing_debit === 0 &&
            account.closing_credit === 0
        );
    }

    formatNumber(num: number): string {
        if (num === null || num === undefined) return '0.00';
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    private formatDate(date: Date): string {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
}
