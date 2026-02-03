import { Component, OnInit } from '@angular/core';
import { ServiceAccountsService } from '../../services/service-accounts.service';
import { MatDialog } from '@angular/material/dialog';
import { ServiceAccountsCreateComponent } from '../service-accounts-create/service-accounts-create.component';
import { ServiceAccountsTransferComponent } from '../service-accounts-transfer/service-accounts-transfer.component';

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
}
