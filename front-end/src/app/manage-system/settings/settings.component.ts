import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { MatSnackBar } from '@angular/material/snack-bar';
import { environment } from 'src/env/env';

@Component({
    selector: 'app-settings',
    templateUrl: './settings.component.html',
    styleUrls: ['./settings.component.css']
})
export class SettingsComponent implements OnInit {

    supplierTypes: any[] = [];
    treeAccounts: any[] = [];

    settings: any = {
        customer_corporate_parent_account_id: null,
        customer_individual_parent_account_id: null,
        supplier_general_parent_id: null,
    };

    loading = false;

    constructor(private http: HttpClient, private snackBar: MatSnackBar) { }

    ngOnInit(): void {
        this.loadData();
    }

    loadData() {
        this.loading = true;

        // Load existing settings
        this.http.get(environment.Url + '/settings').subscribe((res: any) => {
            this.settings = res;
            // Ensure all keys exist in object for binding
            this.initSettingsKeys();
        });

        // Load Supplier Types
        this.http.get(environment.Url + '/suppliers/getAllSupplierTypes').subscribe((res: any) => {
            this.supplierTypes = res;
            this.initSettingsKeys();
        });

        // Load Tree Accounts (Leaf nodes or allowable parents)
        // Assuming we want to allow selecting any liability/asset account 
        // Ideally should filter by type, but for now fetching all or a subset
        this.http.get(environment.Url + '/tree_accounts').subscribe((res: any) => {
            // Adjust based on actual API response structure for tree_accounts
            this.treeAccounts = res.data || res;
        });
    }

    initSettingsKeys() {
        if (!this.settings) this.settings = {};

        // Default keys
        if (!this.settings['customer_corporate_parent_account_id']) this.settings['customer_corporate_parent_account_id'] = null;
        if (!this.settings['customer_individual_parent_account_id']) this.settings['customer_individual_parent_account_id'] = null;
        if (!this.settings['supplier_general_parent_id']) this.settings['supplier_general_parent_id'] = null;

        // Supplier Type keys
        this.supplierTypes.forEach(type => {
            const key = `supplier_type_${type.id}_parent_id`;
            if (!this.settings[key]) this.settings[key] = null;
        });
    }

    saveSettings() {
        this.loading = true;
        this.http.post(environment.Url + '/settings', this.settings).subscribe(
            () => {
                this.snackBar.open('Settings saved successfully', 'Close', { duration: 3000 });
                this.loading = false;
            },
            err => {
                this.snackBar.open('Error saving settings', 'Close', { duration: 3000 });
                this.loading = false;
            }
        );
    }

    updateExisting(type: string, subType: any, parentId: any) {
        if (!confirm('Are you sure you want to update all existing entities of this type to the new parent account? This will change their account codes.')) return;

        this.loading = true;
        const body = {
            type: type,
            sub_type: subType,
            parent_id: parentId
        };

        console.log(body);

        this.http.post(environment.Url + '/settings/update-existing', body).subscribe(
            (res: any) => {
                this.snackBar.open(res.message, 'Close', { duration: 3000 });
                this.loading = false;
            },
            err => {
                console.error(err);
                this.snackBar.open('Error updating entities: ' + (err.error?.message || err.message), 'Close', { duration: 3000 });
                this.loading = false;
            }
        );
    }
}
