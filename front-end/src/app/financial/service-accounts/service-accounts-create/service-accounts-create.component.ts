import { Component, Inject, OnInit } from '@angular/core';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { ServiceAccountsService } from '../../services/service-accounts.service';
import Swal from 'sweetalert2';

import { TreeAccountService } from '../../../accounting/services/tree-account.service';

@Component({
    selector: 'app-service-accounts-create',
    templateUrl: './service-accounts-create.component.html',
    styleUrls: ['./service-accounts-create.component.css']
})
export class ServiceAccountsCreateComponent implements OnInit {
    form!: FormGroup;
    treeAccounts: any[] = [];
    selectedFile: File | null = null;
    imgPreview: string | ArrayBuffer | null = null;

    constructor(
        private serviceAccountsService: ServiceAccountsService,
        private treeAccountService: TreeAccountService,
        public dialogRef: MatDialogRef<ServiceAccountsCreateComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any
    ) { }

    ngOnInit(): void {
        this.treeAccountService.getAll().subscribe(res => {
            if (res.data) {
                this.treeAccounts = Array.isArray(res.data) ? res.data : [res.data];
            } else if (Array.isArray(res)) {
                this.treeAccounts = res;
            }
        });

        this.form = new FormGroup({
            name: new FormControl(this.data ? this.data.name : '', [Validators.required]),
            account_number: new FormControl(this.data ? this.data.account_number : ''),
            description: new FormControl(this.data ? this.data.description : ''),
            other_info: new FormControl(this.data ? this.data.other_info : ''),
            account_id: new FormControl(this.data ? this.data.account_id : '', [Validators.required]),
            balance: new FormControl(this.data ? this.data.balance : 0),
        });

        if (this.data && this.data.img) {
            this.imgPreview = this.data.img; // Assuming full path or handle prefix
        }
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
        if (this.form.valid) {
            const formData = new FormData();
            Object.keys(this.form.controls).forEach(key => {
                formData.append(key, this.form.get(key)?.value);
            });

            if (this.selectedFile) {
                formData.append('img', this.selectedFile);
            }

            if (this.data) {
                this.serviceAccountsService.update(this.data.id, formData).subscribe(res => {
                    Swal.fire('تم التعديل بنجاح', '', 'success');
                    this.dialogRef.close(true);
                }, err => {
                    console.error(err);
                    const msg = err.error?.message || (err.error?.errors ? Object.values(err.error.errors).flat().join('<br>') : 'حدث خطأ غير معروف');
                    Swal.fire({
                        title: 'حدث خطأ',
                        html: msg,
                        icon: 'error'
                    });
                });
            } else {
                this.serviceAccountsService.store(formData).subscribe(res => {
                    Swal.fire('تم الحفظ بنجاح', '', 'success');
                    this.dialogRef.close(true);
                }, err => {
                    console.error(err);
                    const msg = err.error?.message || (err.error?.errors ? Object.values(err.error.errors).flat().join('<br>') : 'حدث خطأ غير معروف');
                    Swal.fire({
                        title: 'حدث خطأ',
                        html: msg,
                        icon: 'error'
                    });
                });
            }
        }
    }
}
