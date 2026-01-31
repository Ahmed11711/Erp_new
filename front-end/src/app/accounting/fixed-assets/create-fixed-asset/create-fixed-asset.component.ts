import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { environment } from 'src/env/env';

@Component({
    selector: 'app-create-fixed-asset',
    templateUrl: './create-fixed-asset.component.html',
    styleUrls: ['./create-fixed-asset.component.css']
})
export class CreateFixedAssetComponent implements OnInit {
    form: FormGroup;
    accounts: any[] = [];
    banks: any[] = [];

    constructor(
        private fb: FormBuilder,
        private http: HttpClient,
        private router: Router
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
            bank_id: [null] // Or Credit Account
        });
    }

    ngOnInit(): void {
        this.loadAccounts();
        this.loadBanks();
    }

    loadAccounts() {
        this.http.get<any>(`${environment.Url}/tree_accounts`).subscribe(res => {
            this.accounts = res.data || res;
        });
    }

    loadBanks() {
        this.http.get<any>(`${environment.Url}/banks`).subscribe(res => {
            this.banks = res.data || res;
        });
    }

    onSubmit() {
        if (this.form.invalid) return;

        this.http.post(`${environment.Url}/assets`, this.form.value).subscribe({
            next: () => {
                alert('Asset created successfully');
                this.router.navigate(['/dashboard/accounting/fixed-assets']);
            },
            error: (err) => {
                alert('Error creating asset');
                console.error(err);
            }
        });
    }
}
