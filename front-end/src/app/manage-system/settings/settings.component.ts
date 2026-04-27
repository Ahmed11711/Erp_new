import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { MatSnackBar } from '@angular/material/snack-bar';
import { forkJoin } from 'rxjs';
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
        customer_online_parent_account_id: null,
        supplier_general_parent_id: null,
        commitment_liability_parent_account_id: null,
        commitment_expense_parent_account_id: null,
    };

    loading = false;
    shopifySyncing = false;
    shopifyOrderSyncing = false;
    shopifyStatusLoading = false;
    /** عدد الأيام للخلف لاستيراد الطلبات من Shopify؛ 0 = كل الطلبات (بطيء إن كان التاريخ طويلاً). */
    shopifyOrderDays = 30;

    constructor(private http: HttpClient, private snackBar: MatSnackBar) { }

    ngOnInit(): void {
        this.loadData();
    }

    loadData() {
        this.loading = true;

        // Load existing settings
        this.http.get(environment.Url + '/accounting/settings').subscribe((res: any) => {
            this.settings = res;
            this.initSettingsKeys();
            this.loading = false;
        }, () => { this.loading = false; });

        // Load Supplier Types
        this.http.get(environment.Url + '/suppliers/getAllSupplierTypes').subscribe((res: any) => {
            this.supplierTypes = res;
            this.initSettingsKeys();
        });

        // Load Tree Accounts
        this.http.get(environment.Url + '/tree_accounts').subscribe((res: any) => {
            this.treeAccounts = res.data || res;
        });
    }

    initSettingsKeys() {
        if (!this.settings) this.settings = {};

        // Default keys
        if (!this.settings['customer_corporate_parent_account_id']) this.settings['customer_corporate_parent_account_id'] = null;
        if (!this.settings['customer_individual_parent_account_id']) this.settings['customer_individual_parent_account_id'] = null;
        if (!this.settings['customer_online_parent_account_id']) this.settings['customer_online_parent_account_id'] = null;
        if (!this.settings['supplier_general_parent_id']) this.settings['supplier_general_parent_id'] = null;
        if (!this.settings['commitment_liability_parent_account_id']) this.settings['commitment_liability_parent_account_id'] = null;
        if (!this.settings['commitment_expense_parent_account_id']) this.settings['commitment_expense_parent_account_id'] = null;

        // Supplier Type keys
        this.supplierTypes.forEach(type => {
            const key = `supplier_type_${type.id}_parent_id`;
            if (!this.settings[key]) this.settings[key] = null;
        });
    }

    saveSettings() {
        this.loading = true;
        this.http.post(environment.Url + '/accounting/settings', this.settings).subscribe(
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
        if (!confirm('هل أنت متأكد من تحديث جميع العملاء/الموردين الحاليين للحساب الأب الجديد؟ سيتم تغيير رموز حساباتهم.')) return;

        this.loading = true;
        const body = {
            type: type,
            sub_type: subType,
            parent_id: parentId
        };

        console.log(body);

        this.http.post(environment.Url + '/accounting/settings/update-existing', body).subscribe(
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

    testShopifyConnection(): void {
        this.shopifyStatusLoading = true;
        this.http.get(environment.Url + '/shopify/status').subscribe({
            next: (res: any) => {
                this.shopifyStatusLoading = false;
                const name = res?.shop?.name || res?.shop?.domain || 'متصل';
                this.snackBar.open('Shopify: ' + name, 'إغلاق', { duration: 4000 });
            },
            error: (err) => {
                this.shopifyStatusLoading = false;
                const msg = err.error?.error || err.error?.message || err.message || 'فشل الاتصال';
                this.snackBar.open('Shopify: ' + msg, 'إغلاق', { duration: 6000 });
            }
        });
    }

    syncShopifyProductMappings(): void {
        if (!confirm('مزامنة متغيرات منتجات Shopify إلى جدول الربط المحلي؟ قد يستغرق وقتاً إذا كان الكتالوج كبيراً.')) {
            return;
        }
        this.shopifySyncing = true;
        this.http.post(environment.Url + '/shopify/sync-product-mappings', {}).subscribe({
            next: (res: any) => {
                this.shopifySyncing = false;
                const n = res?.synced_variants ?? '?';
                this.snackBar.open(`تمت المزامنة: ${n} متغير`, 'إغلاق', { duration: 5000 });
            },
            error: (err) => {
                this.shopifySyncing = false;
                const msg = err.error?.message || err.message || 'فشل المزامنة';
                this.snackBar.open(msg, 'إغلاق', { duration: 6000 });
            }
        });
    }

    syncShopifyOrders(): void {
        const days = Math.max(0, Math.min(3650, Number(this.shopifyOrderDays) || 0));
        const msg =
            days === 0
                ? 'سيتم جلب كل الطلبات من Shopify (قد يستغرق وقتاً طويلاً). المتابعة؟'
                : `استيراد طلبات Shopify من آخر ${days} يوماً (غير الموجودة محلياً). المتابعة؟`;
        if (!confirm(msg)) {
            return;
        }
        this.shopifyOrderSyncing = true;
        this.http.post(environment.Url + '/shopify/sync-orders', { days }).subscribe({
            next: (res: any) => {
                this.shopifyOrderSyncing = false;
                const imp = res?.imported ?? 0;
                const skip = res?.skipped_duplicates ?? 0;
                const fail = res?.failed ?? 0;
                this.snackBar.open(
                    `طلبات: مستورد ${imp}، متخطى ${skip}، فشل ${fail}`,
                    'إغلاق',
                    { duration: 7000 }
                );
            },
            error: (err) => {
                this.shopifyOrderSyncing = false;
                const m = err.error?.message || err.message || 'فشل مزامنة الطلبات';
                this.snackBar.open(m, 'إغلاق', { duration: 7000 });
            }
        });
    }
}
