import { Component, OnInit } from '@angular/core';
import { ServiceAccountsService } from '../../services/service-accounts.service';
import { MatDialog } from '@angular/material/dialog';
import { ServiceAccountsCreateComponent } from '../service-accounts-create/service-accounts-create.component';
import { ServiceAccountsTransferComponent } from '../service-accounts-transfer/service-accounts-transfer.component';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-service-accounts-list',
    templateUrl: './service-accounts-list.component.html',
    styleUrls: ['./service-accounts-list.component.css']
})
export class ServiceAccountsListComponent implements OnInit {
    accounts: any[] = [];
    displayedColumns: string[] = ['img', 'name', 'account_number', 'balance', 'description', 'actions'];

    constructor(private serviceAccountsService: ServiceAccountsService, private dialog: MatDialog) { }

    ngOnInit(): void {
        this.getAccounts();
    }

    getAccounts() {
        this.serviceAccountsService.index().subscribe((res: any) => {
            this.accounts = res;
        });
    }

    openCreateDialog(account: any = null) {
        const dialogRef = this.dialog.open(ServiceAccountsCreateComponent, {
            width: '600px',
            data: account
        });

        dialogRef.afterClosed().subscribe(result => {
            if (result) {
                this.getAccounts();
            }
        });
    }

    openTransferDialog() {
        const dialogRef = this.dialog.open(ServiceAccountsTransferComponent, {
            width: '600px'
        });

        dialogRef.afterClosed().subscribe(result => {
            if (result) {
                this.getAccounts();
            }
        });
    }

    deleteAccount(account: any): void {
        if (!account?.id) return;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: `حذف الحساب الخدمي «${account.name}»؟`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (!result.isConfirmed) return;

            this.serviceAccountsService.delete(account.id).subscribe({
                next: (res: any) => {
                    Swal.fire('تم الحذف', res?.message || 'تم حذف الحساب بنجاح', 'success');
                    this.getAccounts();
                },
                error: (err) => {
                    const msg = err.error?.message || 'تعذر حذف الحساب';
                    Swal.fire('خطأ', msg, 'error');
                }
            });
        });
    }
}
