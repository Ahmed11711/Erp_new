import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { environment } from 'src/env/env';
import { BankService } from '../../services/bank.service';
import { SafeService } from '../../services/safe.service';
import { ServiceAccountsService } from '../../../financial/services/service-accounts.service';

@Component({
    selector: 'app-create-fixed-asset',
    templateUrl: './create-fixed-asset.component.html',
    styleUrls: ['./create-fixed-asset.component.css']
})
export class CreateFixedAssetComponent implements OnInit {
    form: FormGroup;
    accounts: any[] = [];
    banks: any[] = [];
    safes: any[] = [];
    serviceAccounts: any[] = [];

    constructor(
        private fb: FormBuilder,
        private http: HttpClient,
        private router: Router,
        private bankService: BankService,
        private safeService: SafeService,
        private serviceAccountsService: ServiceAccountsService
    ) {
        this.form = this.fb.group({
            name: ['', Validators.required],
            code: [''],
            description: [''],
            asset_date: [new Date().toISOString().split('T')[0], Validators.required],
            purchase_date: [new Date().toISOString().split('T')[0]],
            payment_amount: [0, Validators.required], // Kept for paying
            asset_amount: [0], // Legacy
            purchase_price: [0, Validators.required],
            current_value: [0],
            scrap_value: [0],
            life_span: [1, Validators.required],
            asset_account_id: [null, Validators.required],
            depreciation_account_id: [null],
            expense_account_id: [null],
            payment_source_type: ['bank', Validators.required],
            bank_id: [null as number | null],
            safe_id: [null as number | null],
            service_account_id: [null as number | null],
        });
    }

    ngOnInit(): void {
        this.loadAccounts();
        this.loadBanks();
        this.loadSafes();
        this.loadServiceAccounts();
    }

    loadAccounts() {
        this.http.get<any>(`${environment.Url}/tree_accounts`).subscribe(res => {
            this.accounts = res.data || res;
        });
    }

    loadBanks() {
        this.bankService.getAll().subscribe(res => {
            this.banks = res.data || (Array.isArray(res) ? res : []);
        });
    }

    loadSafes() {
        this.safeService.getAll().subscribe(res => {
            this.safes = res.data || (Array.isArray(res) ? res : []);
        });
    }

    loadServiceAccounts() {
        this.serviceAccountsService.index().subscribe((res: any) => {
            this.serviceAccounts = Array.isArray(res) ? res : (res?.data || []);
        });
    }

    onPaymentSourceChange() {
        this.form.patchValue({
            bank_id: null,
            safe_id: null,
            service_account_id: null,
        });
    }

    private paymentSelectionValid(): boolean {
        const t = this.form.get('payment_source_type')?.value;
        if (t === 'bank' && this.form.get('bank_id')?.value != null) {
            return true;
        }
        if (t === 'safe' && this.form.get('safe_id')?.value != null) {
            return true;
        }
        if (t === 'service_account' && this.form.get('service_account_id')?.value != null) {
            return true;
        }
        alert('الرجاء اختيار مصدر الدفع (بنك أو خزينة أو حساب خدمي)');
        return false;
    }

    onSubmit() {
        if (this.form.invalid) return;
        if (!this.paymentSelectionValid()) return;

        const v = this.form.getRawValue();
        const payload = {
            ...v,
            bank_id: v.payment_source_type === 'bank' ? v.bank_id : null,
            safe_id: v.payment_source_type === 'safe' ? v.safe_id : null,
            service_account_id: v.payment_source_type === 'service_account' ? v.service_account_id : null,
        };

        this.http.post(`${environment.Url}/assets`, payload).subscribe({
            next: () => {
                alert('تم حفظ الأصل بنجاح');
                this.router.navigate(['/dashboard/accounting/fixed-assets']);
            },
            error: (err) => {
                const msg = err.error?.message || err.error?.error || 'تعذر حفظ الأصل';
                alert(typeof msg === 'string' ? msg : 'تعذر حفظ الأصل');
                console.error(err);
            }
        });
    }
}
