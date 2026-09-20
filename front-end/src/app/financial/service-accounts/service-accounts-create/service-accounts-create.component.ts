import { Component, Inject, OnInit } from '@angular/core';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { ServiceAccountsService } from '../../services/service-accounts.service';
import { TreeAccountService } from '../../../accounting/services/tree-account.service';
import Swal from 'sweetalert2';

interface AccountOption {
    id: number;
    label: string;
}

@Component({
    selector: 'app-service-accounts-create',
    templateUrl: './service-accounts-create.component.html',
    styleUrls: ['./service-accounts-create.component.css']
})
export class ServiceAccountsCreateComponent implements OnInit {
    form!: FormGroup;
    treeAccounts: any[] = [];
    accountOptions: AccountOption[] = [];
    filteredLinkedAccounts: AccountOption[] = [];
    filteredCounterAccounts: AccountOption[] = [];
    linkedAccountCtrl = new FormControl<string | AccountOption>('', { nonNullable: true });
    counterAccountCtrl = new FormControl<string | AccountOption>('', { nonNullable: true });
    readonly accountAutocompleteCap = 400;

    selectedFile: File | null = null;
    imgPreview: string | ArrayBuffer | null = null;

    constructor(
        private serviceAccountsService: ServiceAccountsService,
        private treeAccountService: TreeAccountService,
        public dialogRef: MatDialogRef<ServiceAccountsCreateComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any
    ) { }

    get isEditMode(): boolean {
        return !!this.data;
    }

    get showCounterAccount(): boolean {
        return !this.isEditMode && this.openingBalance > 0;
    }

    get openingBalance(): number {
        return Number(this.form?.get('balance')?.value) || 0;
    }

    displayAccountOption = (value: string | AccountOption | null): string => {
        if (!value) return '';
        return typeof value === 'string' ? value : value.label;
    };

    ngOnInit(): void {
        const accountId = this.data ? (this.data.account_id ?? this.data.account?.id) : '';
        this.form = new FormGroup({
            name: new FormControl(this.data ? this.data.name : '', [Validators.required]),
            account_number: new FormControl(this.data ? this.data.account_number : ''),
            description: new FormControl(this.data ? this.data.description : ''),
            other_info: new FormControl(this.data ? this.data.other_info : ''),
            account_id: new FormControl(accountId, [Validators.required]),
            balance: new FormControl({ value: this.data ? this.data.balance : 0, disabled: this.isEditMode }),
            counter_account_id: new FormControl(null),
        });

        this.form.get('balance')?.valueChanges.subscribe(() => this.updateCounterValidators());

        this.linkedAccountCtrl.valueChanges.subscribe((v) => {
            this.applyLinkedFilter(typeof v === 'string' ? v : '');
        });
        this.counterAccountCtrl.valueChanges.subscribe((v) => {
            this.applyCounterFilter(typeof v === 'string' ? v : '');
        });

        this.treeAccountService.getAll().subscribe(res => {
            if (res.data) {
                this.treeAccounts = Array.isArray(res.data) ? res.data : [res.data];
            } else if (Array.isArray(res)) {
                this.treeAccounts = res;
            }
            this.buildAccountOptions();
            this.syncLinkedAutocompleteFromForm();
        });

        if (this.data && this.data.img) {
            this.imgPreview = this.data.img;
        }

        this.updateCounterValidators();
    }

    private buildAccountOptions(): void {
        this.accountOptions = this.treeAccounts
            .filter((a) => a?.id != null && a?.name)
            .map((a) => {
                const code = a.code != null && String(a.code) !== '' ? String(a.code) : '';
                return {
                    id: Number(a.id),
                    label: code ? `${a.name} — ${code}` : String(a.name)
                };
            })
            .sort((a, b) => a.label.localeCompare(b.label, 'ar'));
        this.applyLinkedFilter('');
        this.applyCounterFilter('');
    }

    private filterAccountOptions(term: string): AccountOption[] {
        const raw = String(term ?? '').trim();
        const q = raw.toLowerCase();
        let list = this.accountOptions;
        if (q) {
            list = list.filter((a) => {
                if (String(a.id).includes(raw)) return true;
                return a.label.toLowerCase().includes(q) || a.label.includes(raw);
            });
        }
        return list.slice(0, this.accountAutocompleteCap);
    }

    private applyLinkedFilter(term: string): void {
        this.filteredLinkedAccounts = this.filterAccountOptions(term);
    }

    private applyCounterFilter(term: string): void {
        this.filteredCounterAccounts = this.filterAccountOptions(term);
    }

    onLinkedAccountFocus(): void {
        const v = this.linkedAccountCtrl.value;
        this.applyLinkedFilter(typeof v === 'string' ? v : '');
    }

    onCounterAccountFocus(): void {
        const v = this.counterAccountCtrl.value;
        this.applyCounterFilter(typeof v === 'string' ? v : '');
    }

    onLinkedAccountSelected(event: MatAutocompleteSelectedEvent): void {
        const acc = event.option.value as AccountOption;
        if (!acc?.id) return;
        this.form.patchValue({ account_id: acc.id });
        this.form.get('account_id')?.markAsTouched();
        this.linkedAccountCtrl.setValue(acc, { emitEvent: false });
        this.applyLinkedFilter('');
    }

    onCounterAccountSelected(event: MatAutocompleteSelectedEvent): void {
        const acc = event.option.value as AccountOption;
        if (!acc?.id) return;
        this.form.patchValue({ counter_account_id: acc.id });
        this.form.get('counter_account_id')?.markAsTouched();
        this.counterAccountCtrl.setValue(acc, { emitEvent: false });
        this.applyCounterFilter('');
    }

    onLinkedAccountBlur(): void {
        setTimeout(() => this.syncLinkedOnBlur(), 150);
    }

    onCounterAccountBlur(): void {
        setTimeout(() => this.syncCounterOnBlur(), 150);
    }

    private syncLinkedAutocompleteFromForm(): void {
        const id = this.form.get('account_id')?.value;
        if (!id) return;
        const opt = this.accountOptions.find((a) => a.id === Number(id));
        if (opt) {
            this.linkedAccountCtrl.setValue(opt, { emitEvent: false });
        }
    }

    private syncLinkedOnBlur(): void {
        const v = this.linkedAccountCtrl.value;
        if (v && typeof v === 'object') {
            this.form.patchValue({ account_id: v.id });
            return;
        }
        const str = typeof v === 'string' ? v.trim() : '';
        if (!str) {
            this.form.patchValue({ account_id: '' });
            return;
        }
        const exact = this.accountOptions.find((a) => a.label === str);
        if (exact) {
            this.form.patchValue({ account_id: exact.id });
            this.linkedAccountCtrl.setValue(exact, { emitEvent: false });
            return;
        }
        const partial = this.filterAccountOptions(str);
        if (partial.length === 1) {
            this.form.patchValue({ account_id: partial[0].id });
            this.linkedAccountCtrl.setValue(partial[0], { emitEvent: false });
        }
    }

    private syncCounterOnBlur(): void {
        const v = this.counterAccountCtrl.value;
        if (v && typeof v === 'object') {
            this.form.patchValue({ counter_account_id: v.id });
            return;
        }
        const str = typeof v === 'string' ? v.trim() : '';
        if (!str) {
            this.form.patchValue({ counter_account_id: null });
            return;
        }
        const exact = this.accountOptions.find((a) => a.label === str);
        if (exact) {
            this.form.patchValue({ counter_account_id: exact.id });
            this.counterAccountCtrl.setValue(exact, { emitEvent: false });
            return;
        }
        const partial = this.filterAccountOptions(str);
        if (partial.length === 1) {
            this.form.patchValue({ counter_account_id: partial[0].id });
            this.counterAccountCtrl.setValue(partial[0], { emitEvent: false });
        }
    }

    private updateCounterValidators(): void {
        const ctrl = this.form.get('counter_account_id');
        if (!ctrl) return;
        if (this.showCounterAccount) {
            ctrl.setValidators([Validators.required]);
        } else {
            ctrl.clearValidators();
            ctrl.setValue(null);
            this.counterAccountCtrl.setValue('', { emitEvent: false });
        }
        ctrl.updateValueAndValidity({ emitEvent: false });
    }

    canSubmit(): boolean {
        if (!this.form.get('name')?.valid || !this.form.get('account_id')?.value) {
            return false;
        }
        if (this.showCounterAccount && !this.form.get('counter_account_id')?.value) {
            return false;
        }
        return true;
    }

    onFileSelected(event: any) {
        this.selectedFile = event.target.files[0];
        if (this.selectedFile) {
            const reader = new FileReader();
            reader.onload = () => {
                this.imgPreview = reader.result;
            };
            reader.readAsDataURL(this.selectedFile);
        }
    }

    submit() {
        if (!this.canSubmit()) {
            return;
        }

        const formData = new FormData();
        const raw = this.form.getRawValue();
        const keys = ['name', 'account_number', 'description', 'other_info', 'account_id'];

        if (!this.isEditMode) {
            keys.push('balance');
            if (this.openingBalance > 0 && raw.counter_account_id) {
                keys.push('counter_account_id');
            }
        }

        keys.forEach((key) => {
            const val = raw[key];
            formData.append(key, val != null ? String(val) : '');
        });

        if (this.selectedFile) {
            formData.append('img', this.selectedFile);
        }

        if (this.data) {
            this.serviceAccountsService.update(this.data.id, formData).subscribe(() => {
                Swal.fire('تم التعديل بنجاح', '', 'success');
                this.dialogRef.close(true);
            }, err => {
                const msg = err.error?.message || (err.error?.errors ? Object.values(err.error.errors).flat().join('<br>') : 'حدث خطأ غير معروف');
                Swal.fire({ title: 'حدث خطأ', html: msg, icon: 'error' });
            });
        } else {
            this.serviceAccountsService.store(formData).subscribe(() => {
                Swal.fire('تم الحفظ بنجاح', '', 'success');
                this.dialogRef.close(true);
            }, err => {
                const msg = err.error?.message || (err.error?.errors ? Object.values(err.error.errors).flat().join('<br>') : 'حدث خطأ غير معروف');
                Swal.fire({ title: 'حدث خطأ', html: msg, icon: 'error' });
            });
        }
    }
}
