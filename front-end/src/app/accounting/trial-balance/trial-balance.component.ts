import { Component, HostListener, OnInit } from '@angular/core';
import { FormGroup, FormControl } from '@angular/forms';
import { AccountingReportService } from '../services/accounting-report.service';
import { TreeAccountService } from '../services/tree-account.service';
import { RbacService } from '../../core/rbac/rbac.service';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';
import * as XLSX from 'xlsx';

type FlatAccount = { id: number; code: string; name: string; type: string; level: number };

function round2(n: number): number {
    return Math.round((Number(n) || 0) * 100) / 100;
}

interface ImportLine {
    excel_row?: number;
    account_code: string;
    account_name?: string;
    account_name_excel?: string;
    account_name_system?: string;
    tree_account_id?: number;
    account_type?: string;
    level?: number;
    debit: number;
    credit: number;
    target_net: number;
    name_mismatch?: boolean;
    no_account_id?: boolean;
    reason?: string;
    // for missing rows UI
    parent_id?: number | null;
    type?: string;
    creating?: boolean;
    /** حساب نظام مختار للاستبدال بدل الإنشاء */
    replace_account_id?: number | null;
    mapped_from_excel?: boolean;
}

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

    // ── استيراد أرصدة افتتاحية ──
    showImportPanel = false;
    importFile: File | null = null;
    importOpeningDate = '';
    importCounterAccountId: number | null = null;
    importReason = '';
    importLoading = false;
    importApplying = false;
    importError = '';
    importSuccess = '';
    operationalSyncResult: {
        synced: Array<{ type: string; id: number; name: string; before: number; after: number; tree_code?: string }>;
        skipped: Array<{ type: string; id: number; name: string; reason: string }>;
        counts: Record<string, number>;
    } | null = null;
    matchedLines: ImportLine[] = [];
    missingLines: ImportLine[] = [];
    skippedLines: ImportLine[] = [];
    importTotals: any = null;
    flatAccounts: FlatAccount[] = [];
    loadingAccounts = false;
    counterSearch = '';
    counterDropdownOpen = false;
    parentSearch = '';
    parentDropdownCode: string | null = null;
    replaceSearch = '';
    replaceDropdownCode: string | null = null;

    accountTypes = [
        { value: '', label: 'جميع الأنواع' },
        { value: 'asset', label: 'أصول' },
        { value: 'liability', label: 'التزامات' },
        { value: 'equity', label: 'حقوق ملكية' },
        { value: 'revenue', label: 'إيرادات' },
        { value: 'expense', label: 'مصروفات' },
        { value: 'settlement', label: 'تسوية' }
    ];

    createAccountTypes = [
        { value: 'asset', label: 'أصول' },
        { value: 'liability', label: 'التزامات' },
        { value: 'equity', label: 'حقوق ملكية' },
        { value: 'revenue', label: 'إيرادات' },
        { value: 'expense', label: 'مصروفات' },
        { value: 'settlement', label: 'تسوية' }
    ];

    levels = [
        { value: '', label: 'جميع المستويات' },
        { value: 1, label: 'المستوى 1' },
        { value: 2, label: 'المستوى 2' },
        { value: 3, label: 'المستوى 3' },
        { value: 4, label: 'المستوى 4' },
        { value: 5, label: 'المستوى 5' }
    ];

    filterForm = new FormGroup({
        date_from: new FormControl<string | null>(null),
        date_to: new FormControl<string | null>(null),
        search: new FormControl(''),
        account_type: new FormControl(''),
        level: new FormControl('')
    });

    get isBalanced(): boolean {
        if (!this.totals) return true;
        return (
            (this.totals.opening_difference || 0) === 0 &&
            (this.totals.movement_difference || 0) === 0 &&
            (this.totals.closing_difference || 0) === 0
        );
    }

    get canImportOpening(): boolean {
        return this.rbac.canAny(RBAC_ROUTE.treeAccountBalanceAdjustment);
    }

    get canApplyImport(): boolean {
        return this.matchedLines.length > 0
            && !!this.importOpeningDate
            && !!this.importCounterAccountId
            && !this.importApplying
            && !this.importLoading;
    }

    get filteredCounterAccounts(): FlatAccount[] {
        const q = this.counterSearch.trim().toLowerCase();
        if (!q) {
            return this.flatAccounts;
        }
        return this.flatAccounts.filter(acc =>
            String(acc.code ?? '').toLowerCase().includes(q) ||
            (acc.name || '').toLowerCase().includes(q)
        );
    }

    get selectedCounterAccount(): FlatAccount | null {
        if (this.importCounterAccountId == null) {
            return null;
        }
        return this.flatAccounts.find(a => a.id === this.importCounterAccountId) ?? null;
    }

    constructor(
        private accountingReportService: AccountingReportService,
        private treeAccountService: TreeAccountService,
        private rbac: RbacService
    ) {
        const today = new Date();
        const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        this.filterForm.patchValue({
            date_from: this.formatDate(firstDayOfMonth),
            date_to: this.formatDate(today)
        });
        this.importOpeningDate = this.formatDate(today);
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

    toggleImportPanel(): void {
        this.showImportPanel = !this.showImportPanel;
        this.importError = '';
        this.importSuccess = '';
        if (this.showImportPanel && this.flatAccounts.length === 0) {
            this.loadFlatAccounts();
        }
    }

    onImportFileSelected(event: Event): void {
        const input = event.target as HTMLInputElement;
        const file = input.files?.[0] || null;
        this.importFile = file;
        this.importError = '';
        this.importSuccess = '';
        this.matchedLines = [];
        this.missingLines = [];
        this.skippedLines = [];
        this.importTotals = null;
    }

    previewImport(): void {
        if (!this.importFile) {
            this.importError = 'اختر ملف Excel أولاً';
            return;
        }
        this.importLoading = true;
        this.importError = '';
        this.importSuccess = '';
        this.operationalSyncResult = null;

        this.accountingReportService.previewTrialBalanceOpeningImport(this.importFile).subscribe({
            next: (res: any) => {
                const data = res?.data || res || {};
                this.matchedLines = Array.isArray(data.matched) ? data.matched : [];
                this.missingLines = (Array.isArray(data.missing) ? data.missing : []).map((m: ImportLine) => ({
                    ...m,
                    account_code: m.account_code || '',
                    type: m.no_account_id ? (m.type || 'asset') : this.guessTypeFromCode(m.account_code),
                    parent_id: null,
                    replace_account_id: null,
                    creating: false
                }));
                this.skippedLines = Array.isArray(data.skipped) ? data.skipped : [];
                this.refreshImportTotals();
                this.importLoading = false;
                this.loadFlatAccounts();

                const noCodeCount = this.missingLines.filter(m => !!m.no_account_id).length;
                if (this.missingLines.length > 0) {
                    this.importSuccess = `تمت المعاينة: ${this.matchedLines.length} مطابق، ${this.missingLines.length} ناقص`
                        + (noCodeCount ? ` منها ${noCodeCount} بدون AccountID` : '')
                        + ' — أضِف أو استبدل الناقص ثم رحّل.';
                } else if (this.matchedLines.length > 0) {
                    this.importSuccess = `تمت المعاينة: ${this.matchedLines.length} حساب جاهز للترحيل.`;
                } else {
                    this.importError = `لم يتم استخراج حسابات من الملف.`
                        + (this.skippedLines.length ? ` (تم تخطي ${this.skippedLines.length} صف)` : '')
                        + ' تأكد أن الملف تصدير ميزان المراجعة أو الأعمدة: AccountID, AccountName, PrevCRBalance, PrevDBBalance.';
                    this.importSuccess = '';
                }
            },
            error: (err) => {
                this.importLoading = false;
                this.importError = err?.error?.message || 'فشل معاينة الملف';
            }
        });
    }

    createMissing(line: ImportLine): void {
        if (!line.type) {
            this.importError = `حدد نوع الحساب لـ ${line.account_name_excel || line.account_code || 'الصف'}`;
            return;
        }
        if (line.no_account_id && !line.parent_id) {
            this.importError = `الحساب «${line.account_name_excel}» بدون كود — اختر حساباً أباً ليُنشأ تحته.`;
            return;
        }
        line.creating = true;
        this.importError = '';
        const rowKey = this.lineKey(line);

        this.accountingReportService.createMissingAccountFromImport({
            account_code: line.account_code || '',
            account_name: line.account_name_excel || line.account_code,
            type: line.type,
            parent_id: line.parent_id || null,
            debit: line.debit,
            credit: line.credit
        }).subscribe({
            next: (res: any) => {
                const createdLine = res?.data?.line || res?.line;
                if (createdLine) {
                    this.matchedLines = [...this.matchedLines, createdLine];
                }
                this.missingLines = this.missingLines.filter(m => this.lineKey(m) !== rowKey);
                this.refreshImportTotals();
                this.loadFlatAccounts();
                line.creating = false;
                this.importSuccess = `تم إنشاء الحساب ${createdLine?.account_code || ''} ونقله للمطابقة.`;
            },
            error: (err) => {
                line.creating = false;
                this.importError = err?.error?.message || `فشل إنشاء الحساب ${line.account_name_excel || line.account_code}`;
            }
        });
    }

    replaceWithExisting(line: ImportLine): void {
        if (!line.replace_account_id) {
            this.importError = `اختر حساباً موجوداً لربط «${line.account_name_excel || line.account_code}»`;
            return;
        }

        const account = this.flatAccounts.find(a => a.id === line.replace_account_id);
        if (!account) {
            this.importError = 'الحساب المختار غير موجود.';
            return;
        }

        const alreadyUsed = this.matchedLines.some(m => m.tree_account_id === account.id);
        if (alreadyUsed) {
            this.importError = `الحساب ${account.code} مربوط بالفعل بصف آخر في المطابقة.`;
            return;
        }

        const excelName = line.account_name_excel || '';
        const rowKey = this.lineKey(line);
        const mapped: ImportLine = {
            excel_row: line.excel_row,
            account_code: account.code,
            account_name_excel: excelName,
            account_name_system: account.name,
            tree_account_id: account.id,
            account_type: account.type,
            level: account.level,
            debit: line.debit || 0,
            credit: line.credit || 0,
            target_net: round2((line.debit || 0) - (line.credit || 0)),
            name_mismatch: this.normalizeName(excelName) !== this.normalizeName(account.name),
            mapped_from_excel: true
        };

        this.matchedLines = [...this.matchedLines, mapped];
        this.missingLines = this.missingLines.filter(m => this.lineKey(m) !== rowKey);
        this.replaceDropdownCode = null;
        this.replaceSearch = '';
        this.refreshImportTotals();
        this.importError = '';
        this.importSuccess = `تم ربط Excel [${excelName || line.account_code}] بالحساب الموجود ${account.code} — ${account.name}`;
    }

    applyImport(): void {
        if (!this.canApplyImport) {
            this.importError = 'أكمل التاريخ والحساب المقابل وبنود المطابقة أولاً';
            return;
        }
        if (this.missingLines.length > 0) {
            const ok = confirm(`يوجد ${this.missingLines.length} حساب ناقص لن يُرحَّل. هل تريد ترحيل المطابق فقط؟`);
            if (!ok) return;
        }

        this.importApplying = true;
        this.importError = '';
        this.importSuccess = '';
        this.operationalSyncResult = null;

        const lines = this.matchedLines
            .filter(l => !!l.tree_account_id)
            .map(l => ({
                tree_account_id: l.tree_account_id as number,
                debit: l.debit || 0,
                credit: l.credit || 0
            }));

        this.accountingReportService.applyTrialBalanceOpeningImport({
            opening_date: this.importOpeningDate,
            counter_account_id: this.importCounterAccountId as number,
            reason: this.importReason || undefined,
            lines
        }).subscribe({
            next: (res: any) => {
                this.importApplying = false;
                this.importSuccess = res?.message || 'تم ترحيل الأرصدة الافتتاحية بنجاح';
                this.operationalSyncResult = res?.data?.operational_sync || null;
                // اعرض ميزان المراجعة ابتداءً من تاريخ الافتتاح
                this.filterForm.patchValue({
                    date_from: this.importOpeningDate
                });
                this.loadTrialBalance();
            },
            error: (err) => {
                this.importApplying = false;
                this.importError = err?.error?.message || 'فشل ترحيل الأرصدة';
            }
        });
    }

    operationalTypeLabel(type: string): string {
        const map: Record<string, string> = {
            safe: 'خزنة',
            bank: 'بنك',
            service_account: 'حساب خدمة',
            supplier: 'مورد',
            customer_company: 'عميل شركة',
            shipping_company: 'شركة شحن'
        };
        return map[type] || type;
    }

    parentOptionsForType(type?: string): FlatAccount[] {
        if (!type) return this.flatAccounts;
        return this.flatAccounts.filter(a => a.type === type);
    }

    filteredParentAccounts(type?: string): FlatAccount[] {
        const options = this.parentOptionsForType(type);
        const q = this.parentSearch.trim().toLowerCase();
        if (!q) {
            return options;
        }
        return options.filter(acc =>
            String(acc.code ?? '').toLowerCase().includes(q) ||
            (acc.name || '').toLowerCase().includes(q)
        );
    }

    selectedParentAccount(line: ImportLine): FlatAccount | null {
        if (line.parent_id == null) {
            return null;
        }
        return this.flatAccounts.find(a => a.id === line.parent_id) ?? null;
    }

    toggleCounterDropdown(event: Event): void {
        event.stopPropagation();
        if (this.loadingAccounts) {
            return;
        }
        this.parentDropdownCode = null;
        this.counterDropdownOpen = !this.counterDropdownOpen;
        if (this.counterDropdownOpen) {
            this.counterSearch = '';
        }
    }

    selectCounter(acc: FlatAccount): void {
        this.importCounterAccountId = acc.id;
        this.counterDropdownOpen = false;
        this.counterSearch = '';
    }

    clearCounterSelection(event: Event): void {
        event.stopPropagation();
        this.importCounterAccountId = null;
        this.counterSearch = '';
    }

    toggleParentDropdown(event: Event, line: ImportLine): void {
        event.stopPropagation();
        const code = this.lineKey(line);
        if (this.parentDropdownCode === code) {
            this.parentDropdownCode = null;
            return;
        }
        this.counterDropdownOpen = false;
        this.replaceDropdownCode = null;
        this.parentDropdownCode = code;
        this.parentSearch = '';
    }

    selectParent(line: ImportLine, acc: FlatAccount | null): void {
        line.parent_id = acc?.id ?? null;
        if (acc?.type) {
            line.type = acc.type;
        }
        this.parentDropdownCode = null;
        this.parentSearch = '';
    }

    clearParentSelection(event: Event, line: ImportLine): void {
        event.stopPropagation();
        line.parent_id = null;
        this.parentSearch = '';
    }

    filteredReplaceAccounts(line: ImportLine): FlatAccount[] {
        const usedIds = new Set(
            this.matchedLines
                .map(m => m.tree_account_id)
                .filter((id): id is number => id != null)
        );
        const q = this.replaceSearch.trim().toLowerCase();
        return this.flatAccounts.filter(acc => {
            if (usedIds.has(acc.id) && acc.id !== line.replace_account_id) {
                return false;
            }
            if (!q) {
                return true;
            }
            return String(acc.code ?? '').toLowerCase().includes(q)
                || (acc.name || '').toLowerCase().includes(q);
        });
    }

    selectedReplaceAccount(line: ImportLine): FlatAccount | null {
        if (line.replace_account_id == null) {
            return null;
        }
        return this.flatAccounts.find(a => a.id === line.replace_account_id) ?? null;
    }

    toggleReplaceDropdown(event: Event, line: ImportLine): void {
        event.stopPropagation();
        const code = this.lineKey(line);
        if (this.replaceDropdownCode === code) {
            this.replaceDropdownCode = null;
            return;
        }
        this.counterDropdownOpen = false;
        this.parentDropdownCode = null;
        this.replaceDropdownCode = code;
        this.replaceSearch = '';
    }

    selectReplaceAccount(line: ImportLine, acc: FlatAccount): void {
        line.replace_account_id = acc.id;
        this.replaceDropdownCode = null;
        this.replaceSearch = '';
    }

    clearReplaceSelection(event: Event, line: ImportLine): void {
        event.stopPropagation();
        line.replace_account_id = null;
        this.replaceSearch = '';
    }

    @HostListener('document:click')
    closeAccountDropdowns(): void {
        this.counterDropdownOpen = false;
        this.parentDropdownCode = null;
        this.replaceDropdownCode = null;
    }

    private refreshImportTotals(): void {
        const debit = this.matchedLines.reduce((s, l) => s + (Number(l.debit) || 0), 0);
        const credit = this.matchedLines.reduce((s, l) => s + (Number(l.credit) || 0), 0);
        this.importTotals = {
            debit: round2(debit),
            credit: round2(credit),
            difference: round2(debit - credit),
            matched_count: this.matchedLines.length,
            missing_count: this.missingLines.length
        };
    }

    private normalizeName(name: string): string {
        return (name || '').trim().replace(/\s+/g, ' ').toLowerCase();
    }
    private loadFlatAccounts(): void {
        this.loadingAccounts = true;
        this.treeAccountService.getTree().subscribe({
            next: (data: any) => {
                const tree = Array.isArray(data) ? data : (data?.data ?? []);
                this.flatAccounts = [];
                this.flattenAccounts(tree);
                if (this.flatAccounts.length === 0) {
                    this.loadFlatAccountsFallback();
                    return;
                }
                this.loadingAccounts = false;
            },
            error: () => {
                this.loadFlatAccountsFallback();
            }
        });
    }

    private loadFlatAccountsFallback(): void {
        this.treeAccountService.getAll().subscribe({
            next: (res: any) => {
                const list = Array.isArray(res) ? res : (res?.data ?? []);
                this.flatAccounts = [];
                // قد تكون شجرة أو قائمة مسطّحة
                this.flattenAccounts(list);
                if (this.flatAccounts.length === 0 && Array.isArray(list)) {
                    for (const n of list) {
                        if (n?.id != null) {
                            this.flatAccounts.push({
                                id: n.id,
                                code: String(n.code ?? ''),
                                name: String(n.name ?? ''),
                                type: String(n.type ?? ''),
                                level: Number(n.level || 1)
                            });
                        }
                    }
                }
                this.loadingAccounts = false;
            },
            error: () => {
                this.loadingAccounts = false;
            }
        });
    }

    private flattenAccounts(nodes: any[]): void {
        for (const n of nodes || []) {
            if (n?.id != null) {
                this.flatAccounts.push({
                    id: n.id,
                    code: String(n.code ?? ''),
                    name: String(n.name ?? ''),
                    type: String(n.type ?? ''),
                    level: Number(n.level || 1)
                });
            }
            const kids = Array.isArray(n?.children)
                ? n.children
                : (Array.isArray(n?.children?.data) ? n.children.data : []);
            if (kids.length) {
                this.flattenAccounts(kids);
            }
        }
    }

    private guessTypeFromCode(code: string): string {
        const first = String(code || '').trim().charAt(0);
        switch (first) {
            case '1': return 'asset';
            case '2': return 'liability';
            case '3': return 'equity';
            case '4': return 'revenue';
            case '5': return 'expense';
            default: return 'asset';
        }
    }

    exportToExcel(): void {
        if (!this.trialBalanceData.length) {
            return;
        }

        const dateFrom = this.filterForm.value.date_from || '';
        const dateTo = this.filterForm.value.date_to || '';
        const typeLabel = this.accountTypes.find(t => t.value === this.filterForm.value.account_type)?.label
            || 'جميع الأنواع';
        const levelVal = this.filterForm.value.level;
        const levelLabel = this.levels.find(l => String(l.value) === String(levelVal ?? ''))?.label
            || 'جميع المستويات';

        const rows: unknown[][] = [
            ['ميزان المراجعة'],
            [`من تاريخ: ${dateFrom || '—'}`, `إلى تاريخ: ${dateTo || '—'}`],
            [`نوع الحساب: ${typeLabel}`, `المستوى: ${levelLabel}`],
            [],
            [
                'كود الحساب',
                'اسم الحساب',
                'النوع',
                'المستوى',
                'رصيد أول المدة - مدين',
                'رصيد أول المدة - دائن',
                'الحركة - مدين',
                'الحركة - دائن',
                'رصيد آخر المدة - مدين',
                'رصيد آخر المدة - دائن'
            ]
        ];

        for (const item of this.trialBalanceData) {
            const typeName = this.accountTypes.find(t => t.value === item.account_type)?.label
                || item.account_type
                || '';
            rows.push([
                item.account_code ?? '',
                item.account_name ?? '',
                typeName,
                item.level ?? '',
                Number(item.opening_debit) || 0,
                Number(item.opening_credit) || 0,
                Number(item.movement_debit) || 0,
                Number(item.movement_credit) || 0,
                Number(item.closing_debit) || 0,
                Number(item.closing_credit) || 0
            ]);
        }

        rows.push([
            '',
            'الإجمالي',
            '',
            '',
            Number(this.totals?.opening_debit) || 0,
            Number(this.totals?.opening_credit) || 0,
            Number(this.totals?.movement_debit) || 0,
            Number(this.totals?.movement_credit) || 0,
            Number(this.totals?.closing_debit) || 0,
            Number(this.totals?.closing_credit) || 0
        ]);

        const ws = XLSX.utils.aoa_to_sheet(rows);
        ws['!cols'] = [
            { width: 14 },
            { width: 36 },
            { width: 14 },
            { width: 10 },
            { width: 16 },
            { width: 16 },
            { width: 14 },
            { width: 14 },
            { width: 16 },
            { width: 16 }
        ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'ميزان المراجعة');
        if (!wb.Workbook) {
            wb.Workbook = { Views: [{}] };
        }
        if (!wb.Workbook.Views) {
            wb.Workbook.Views = [{}];
        }
        wb.Workbook.Views[0].RTL = true;

        XLSX.writeFile(wb, `ميزان_المراجعة_${dateFrom || 'from'}_${dateTo || 'to'}.xlsx`);
    }

    print(): void {
        window.print();
    }

    trackByAccount(index: number, item: any): any {
        return item?.account_id;
    }

    trackByIndex(index: number): number {
        return index;
    }

    trackByCode(index: number, item: ImportLine): string {
        return this.lineKey(item) + '_' + index;
    }

    lineKey(line: ImportLine): string {
        if (line?.excel_row != null && line.excel_row !== undefined) {
            return 'row_' + line.excel_row;
        }
        return 'code_' + (line?.account_code || '') + '_' + (line?.account_name_excel || '');
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
