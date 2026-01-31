import { Component, Inject, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { VoucherService } from '../../services/voucher.service';

@Component({
    selector: 'app-voucher-dialog',
    templateUrl: './voucher-dialog.component.html',
    styleUrls: ['./voucher-dialog.component.css']
})
export class VoucherDialogComponent implements OnInit {
    form: FormGroup;
    loading = false;
    clients: any[] = [];
    suppliers: any[] = [];
    accounts: any[] = [];
    voucherType: 'client' | 'supplier' = 'client';

    allAccounts: any[] = [];
    filteredAccounts: any[] = [];

    constructor(
        private fb: FormBuilder,
        private dialogRef: MatDialogRef<VoucherDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private voucherService: VoucherService
    ) {
        this.voucherType = data.voucherType || 'client';
        this.form = this.fb.group({
            date: [new Date().toISOString().split('T')[0], Validators.required],
            type: ['receipt', Validators.required],
            voucher_type: [this.voucherType, Validators.required],
            payment_method: ['cash', Validators.required], // 'cash' or 'bank'
            account_id: [null, Validators.required],
            client_id: [null],
            supplier_id: [null],
            amount: [0, [Validators.required, Validators.min(0.01)]],
            notes: [''],
            reference_number: ['']
        });
    }

    ngOnInit(): void {
        this.loadData();
        if (this.voucherType === 'client') {
            this.form.get('client_id')?.setValidators(Validators.required);
        } else {
            this.form.get('supplier_id')?.setValidators(Validators.required);
        }

        this.form.get('payment_method')?.valueChanges.subscribe(val => {
            this.filterAccounts(val);
        });

        // Patch if editing
        if (this.data.voucher) {
            const v = this.data.voucher;
            // Determine payment method based on account type or just default?
            // Since we don't easily know if account_id is bank or safe without account details, 
            // we might need to rely on the account object if loaded, or guess.
            // For now, let's assume if it came from backend, we might know.
            // But wait, filterAccounts() depends on payment_method.
            // Let's try to infer or just set it if we can.

            // We need to set payment_method before account_id for the filter to work? 
            // Actually, we should patch excluding account_id/payment_method first, then deduce.

            this.form.patchValue({
                date: v.date.split('T')[0], // Ensure YYYY-MM-DD
                type: v.type,
                voucher_type: v.voucher_type,
                client_id: v.client_id,
                supplier_id: v.supplier_id,
                amount: v.amount,
                notes: v.notes,
                reference_number: v.reference_number
            });

            // If account is loaded, check its type.
            // For simplicty, try to find the account in `allAccounts` once loaded.
        }
    }

    loadData() {
        this.voucherService.getAccounts().subscribe(res => {
            this.allAccounts = Array.isArray(res) ? res : (res.data || []);

            // If editing, try to set the account and payment method correctly
            if (this.data.voucher) {
                const v = this.data.voucher;
                const acc = this.allAccounts.find((a: any) => a.id == v.account_id);
                if (acc) {
                    // Guess payment method
                    let method = 'cash';
                    if (acc.name.includes('بنك') || acc.name.includes('مصرف') || acc.detail_type === 'bank') {
                        method = 'bank';
                    }
                    this.form.patchValue({ payment_method: method });
                    this.filterAccounts(method);
                    this.form.patchValue({ account_id: v.account_id });
                } else {
                    // Just set it anyway
                    this.filterAccounts('');
                    this.form.patchValue({ account_id: v.account_id });
                }
            } else {
                this.filterAccounts(this.form.get('payment_method')?.value);
            }
        });

        if (this.voucherType === 'client') {
            this.voucherService.getClients().subscribe(res => {
                this.clients = Array.isArray(res) ? res : (res.data || []);
            });
        } else {
            this.voucherService.getSuppliers().subscribe(res => {
                this.suppliers = Array.isArray(res) ? res : (res.data || []);
            });
        }
    }

    filterAccounts(method: string) {
        if (!method) {
            this.filteredAccounts = this.allAccounts;
            return;
        }
        this.filteredAccounts = this.allAccounts.filter(acc => {
            if (acc.detail_type) return acc.detail_type === method;
            const name = acc.name.toLowerCase();
            if (method === 'cash') return name.includes('صندوق') || name.includes('cash') || name.includes('خزينة');
            else if (method === 'bank') return name.includes('بنك') || name.includes('bank') || name.includes('مصرف');
            return true;
        });

        const currentAccountId = this.form.get('account_id')?.value;
        if (currentAccountId && !this.filteredAccounts.find(a => a.id === currentAccountId)) {
            // If editing, we might want to keep it even if filter assumes otherwise? 
            // Better to add it to filtered list if it matches ID?
            if (this.data.voucher && this.data.voucher.account_id == currentAccountId) {
                const acc = this.allAccounts.find(a => a.id == currentAccountId);
                if (acc) this.filteredAccounts.push(acc);
            } else {
                this.form.patchValue({ account_id: null });
            }
        }
    }

    save() {
        if (this.form.invalid) {
            this.form.markAllAsTouched();
            return;
        }
        this.loading = true;
        const voucher = this.form.value;

        if (this.voucherType === 'client' && voucher.client_id) {
            const client = this.clients.find(c => c.id == voucher.client_id);
            if (client) voucher.client_or_supplier_name = client.name || client.company_name;
        } else if (voucher.supplier_id) {
            const supplier = this.suppliers.find(s => s.id == voucher.supplier_id);
            if (supplier) voucher.client_or_supplier_name = supplier.supplier_name;
        }

        let request: any;
        if (this.data.voucher && this.data.voucher.id) {
            request = this.voucherService.updateVoucher(this.data.voucher.id, voucher);
        } else {
            request = this.voucherService.createVoucher(voucher);
        }

        request.subscribe({
            next: (res: any) => {
                this.loading = false;
                this.dialogRef.close(true);
            },
            error: (err: any) => {
                this.loading = false;
                console.error('Error saving voucher:', err);
                let errorMessage = 'حدث خطأ أثناء الحفظ';
                if (err.status === 422 && err.error && err.error.errors) {
                    const errors = err.error.errors;
                    const messages: string[] = [];
                    for (const key in errors) {
                        if (errors.hasOwnProperty(key)) {
                            messages.push(errors[key].join(', '));
                        }
                    }
                    errorMessage = messages.join('\n');
                } else if (err.error && err.error.message) {
                    errorMessage = err.error.message;
                }
                alert(errorMessage);
            }
        });
    }

    toggleAdvance(event: any) {
        const isChecked = event.target.checked;
        const currentNotes = this.form.get('notes')?.value || '';
        const advanceText = ' (سلفة)';

        if (isChecked) {
            if (!currentNotes.includes(advanceText)) {
                this.form.patchValue({ notes: currentNotes + advanceText });
            }
        } else {
            this.form.patchValue({ notes: currentNotes.replace(advanceText, '') });
        }
    }

    close() {
        this.dialogRef.close();
    }
}
