import { Component, OnInit } from '@angular/core';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { MatDialogRef } from '@angular/material/dialog';
import { ServiceAccountsService } from '../../services/service-accounts.service';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-service-accounts-transfer',
    templateUrl: './service-accounts-transfer.component.html',
    styleUrls: ['./service-accounts-transfer.component.css']
})
export class ServiceAccountsTransferComponent implements OnInit {
    form!: FormGroup;
    accounts: any[] = [];

    constructor(
        private serviceAccountsService: ServiceAccountsService,
        public dialogRef: MatDialogRef<ServiceAccountsTransferComponent>
    ) { }

    ngOnInit(): void {
        this.serviceAccountsService.index().subscribe((res: any) => {
            this.accounts = res;
        });

        this.form = new FormGroup({
            from_account_id: new FormControl('', [Validators.required]),
            to_account_id: new FormControl('', [Validators.required]),
            amount: new FormControl('', [Validators.required, Validators.min(1)]),
            notes: new FormControl(''),
        });
    }

    submit() {
        if (this.form.valid) {
            if (this.form.value.from_account_id === this.form.value.to_account_id) {
                Swal.fire('تنبيه', 'لا يمكن التحويل لنفس الحساب', 'warning');
                return;
            }

            this.serviceAccountsService.transfer(this.form.value).subscribe(res => {
                Swal.fire('تم التحويل بنجاح', '', 'success');
                this.dialogRef.close(true);
            }, err => {
                Swal.fire('خطأ', err.error.message || 'حدث خطأ اثناء التحويل', 'error');
            });
        }
    }
}
