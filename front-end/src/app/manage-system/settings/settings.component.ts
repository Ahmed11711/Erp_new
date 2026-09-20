import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { MatSnackBar } from '@angular/material/snack-bar';
import { forkJoin } from 'rxjs';
import { environment } from 'src/env/env';
import { RbacService } from 'src/app/core/rbac/rbac.service';

export interface ShopifyUnmatchedDraft {
    shopify_variant_id: number | null;
    shopify_product_id: number | null;
    shopify_name: string;
    expected_erp_name: string;
    product_title: string;
    variant_label: string;
    sku: string | null;
    price: number;
    order_count: number;
    include: boolean;
    name: string;
    category_price: number;
    sell_total_price: number;
    stock_id: number | null;
    measurement_id: number | null;
}

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
    shopifyOrderDays = 0;
    /** نطاق مزامنة الطلبات (يُفضّل على عدد الأيام عند تعبئة التاريخين). */
    shopifyOrderDateFrom: Date | null = new Date();
    shopifyOrderDateTo: Date | null = new Date();

    /** مزامنة ربط متغيرات المنتجات (نفس منطق التاريخ أو الأيام). */
    shopifyProductDays = 30;
    shopifyProductDateFrom: Date | null = null;
    shopifyProductDateTo: Date | null = null;

    /** تفاصيل آخر مزامنة منتجات (من الـ API). */
    shopifyProductSyncDetailRows: any[] = [];
    shopifyProductSyncStats: { created: number; updated: number; total: number; truncated: boolean } | null = null;

    /** استيراد الطلبات تلقائياً عبر Webhooks (false = يدوي فقط). */
    shopifyAutoImportOrders = true;
    shopifyIntegrationSettingsLoading = false;
    shopifyIntegrationSettingsSaving = false;

    /** معاينة سحب الطلبات — منتجات غير مربوطة */
    shopifyOrderPreviewToken: string | null = null;
    shopifyOrderPreviewStats: { orders_fetched: number; unmatched_count: number } | null = null;
    shopifyUnmatchedProducts: ShopifyUnmatchedDraft[] = [];
    shopifyOrderPreviewLoading = false;
    shopifyLookupLoading = false;
    stocks: any[] = [];
    measurements: any[] = [];
    defaultStockId: number | null = null;
    defaultMeasurementId: number | null = null;
    shopifyPreviewShopDomain: string | null = null;

    constructor(
        private http: HttpClient,
        private snackBar: MatSnackBar,
        public rbac: RbacService,
    ) { }

    ngOnInit(): void {
        this.loadData();
        this.loadShopifyIntegrationSettings();
        this.loadShopifyCategoryLookups();
    }

    loadShopifyCategoryLookups(): void {
        this.shopifyLookupLoading = true;
        forkJoin({
            stocks: this.http.get<any>(environment.Url + '/stocks'),
            measurements: this.http.get<any>(environment.Url + '/measurements'),
        }).subscribe({
            next: ({ stocks, measurements }) => {
                this.shopifyLookupLoading = false;
                this.stocks = Array.isArray(stocks) ? stocks : (stocks?.data ?? []);
                this.measurements = Array.isArray(measurements) ? measurements : (measurements?.data ?? []);
                const finished = this.stocks.find((s: any) =>
                    String(s?.name ?? s?.stock_name ?? '').includes('تام')
                );
                this.defaultStockId = finished?.id ?? (this.stocks[0]?.id ?? null);
                this.defaultMeasurementId = this.measurements[0]?.id ?? null;
                this.shopifyUnmatchedProducts.forEach((row) => {
                    if (!row.stock_id) {
                        row.stock_id = this.defaultStockId;
                    }
                    if (!row.measurement_id) {
                        row.measurement_id = this.defaultMeasurementId;
                    }
                });
            },
            error: () => {
                this.shopifyLookupLoading = false;
            },
        });
    }

    loadData() {
        this.loading = true;

        this.http.get(environment.Url + '/accounting/settings').subscribe((res: any) => {
            this.settings = res;
            this.initSettingsKeys();
            this.loading = false;
        }, () => { this.loading = false; });
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

    loadShopifyIntegrationSettings(): void {
        this.shopifyIntegrationSettingsLoading = true;
        this.http.get(environment.Url + '/shopify/integration-settings').subscribe({
            next: (res: any) => {
                this.shopifyIntegrationSettingsLoading = false;
                this.shopifyAutoImportOrders = res?.auto_import_orders !== false;
            },
            error: () => {
                this.shopifyIntegrationSettingsLoading = false;
            }
        });
    }

    onShopifyAutoImportOrdersChange(enabled: boolean): void {
        const previous = this.shopifyAutoImportOrders;
        this.shopifyAutoImportOrders = enabled;
        this.shopifyIntegrationSettingsSaving = true;
        this.http.post(environment.Url + '/shopify/integration-settings', {
            auto_import_orders: enabled,
        }).subscribe({
            next: (res: any) => {
                this.shopifyIntegrationSettingsSaving = false;
                this.shopifyAutoImportOrders = res?.auto_import_orders !== false;
                const msg = res?.message || (enabled
                    ? 'تم تفعيل الاستيراد التلقائي للطلبات.'
                    : 'تم إيقاف الاستيراد التلقائي؛ استخدم «Pull Orders» للمزامنة اليدوية.');
                this.snackBar.open(msg, 'إغلاق', { duration: 5000 });
            },
            error: (err) => {
                this.shopifyIntegrationSettingsSaving = false;
                this.shopifyAutoImportOrders = previous;
                const msg = err.error?.message || err.message || 'فشل حفظ الإعداد';
                this.snackBar.open(msg, 'إغلاق', { duration: 6000 });
            }
        });
    }

    syncShopifyProductMappings(): void {
        const hasOne = !!(this.shopifyProductDateFrom || this.shopifyProductDateTo);
        const hasBoth = !!(this.shopifyProductDateFrom && this.shopifyProductDateTo);
        if (hasOne && !hasBoth) {
            this.snackBar.open('املأ تاريخ البداية والنهاية للمنتجات معاً، أو امسح التاريخين لاستخدام عدد الأيام.', 'إغلاق', { duration: 5000 });
            return;
        }

        const useRange = hasBoth;
        let days = 0;
        if (! useRange) {
            days = Math.max(0, Math.min(3650, Number(this.shopifyProductDays) || 0));
        }

        const confirmMsg = useRange
            ? `مزامنة أصناف المتجر (المتغيرات) لمنتجات أُنشئت في Shopify بين ${this.formatYmd(this.shopifyProductDateFrom!)} و ${this.formatYmd(this.shopifyProductDateTo!)}. المتابعة؟`
            : days === 0
                ? 'سيتم جلب جميع منتجات Shopify عبر الـ API وقد يستغرق وقتاً. المتابعة؟'
                : `مزامنة منتجات أُنشئت خلال آخر ${days} يوماً. المتابعة؟`;

        if (!confirm(confirmMsg)) {
            return;
        }

        const body = useRange
            ? {
                date_from: this.formatYmd(this.shopifyProductDateFrom!),
                date_to: this.formatYmd(this.shopifyProductDateTo!),
            }
            : { days };

        this.shopifySyncing = true;
        this.shopifyProductSyncDetailRows = [];
        this.shopifyProductSyncStats = null;
        this.http.post(environment.Url + '/shopify/sync-product-mappings', body).subscribe({
            next: (res: any) => {
                this.shopifySyncing = false;

                if (res?.sync_overlap_warning && res?.sync_overlap_message) {
                    this.snackBar.open(String(res.sync_overlap_message), 'إغلاق', { duration: 10000 });
                }

                const n = res?.synced_variants ?? '?';
                const pages = res?.pages_fetched ?? '?';
                const created = res?.variants_created ?? 0;
                const updated = res?.variants_updated ?? 0;
                this.shopifyProductSyncDetailRows = Array.isArray(res?.sync_detail_rows) ? res.sync_detail_rows : [];
                this.shopifyProductSyncStats = {
                    created,
                    updated,
                    total: typeof res?.synced_variants === 'number' ? res.synced_variants : this.shopifyProductSyncDetailRows.length,
                    truncated: !!res?.sync_detail_truncated,
                };
                this.snackBar.open(
                    `تمت مزامنة المنتجات: ${n} متغير (${pages} صفحة API) — جديد ${created}، تحديث ${updated}`,
                    'إغلاق',
                    { duration: 7000 }
                );
            },
            error: (err) => {
                this.shopifySyncing = false;
                this.shopifyProductSyncDetailRows = [];
                this.shopifyProductSyncStats = null;
                const msg = err.error?.message || err.message || 'فشل المزامنة';
                this.snackBar.open(msg, 'إغلاق', { duration: 6000 });
            }
        });
    }

    syncShopifyOrders(): void {
        const body = this.buildShopifyOrderSyncBody();
        if (!body) {
            return;
        }

        this.shopifyOrderPreviewLoading = true;
        this.clearShopifyOrderPreview();

        this.http.post(environment.Url + '/shopify/preview-order-sync', body).subscribe({
            next: (res: any) => {
                this.shopifyOrderPreviewLoading = false;

                if (res?.sync_overlap_warning && res?.sync_overlap_message) {
                    this.snackBar.open(String(res.sync_overlap_message), 'إغلاق', { duration: 10000 });
                }

                this.shopifyOrderPreviewToken = res?.preview_token ?? null;
                this.shopifyPreviewShopDomain = res?.shop_domain ?? null;
                this.shopifyOrderPreviewStats = {
                    orders_fetched: res?.orders_fetched ?? 0,
                    unmatched_count: res?.unmatched_count ?? 0,
                };

                const unmatched = Array.isArray(res?.unmatched_products) ? res.unmatched_products : [];
                if (unmatched.length > 0) {
                    this.shopifyUnmatchedProducts = unmatched.map((row: any) => this.toUnmatchedDraft(row));
                    this.snackBar.open(
                        `تم جلب ${res?.orders_fetched ?? 0} طلب — ${unmatched.length} منتج غير مربوط بصنف ERP`,
                        'إغلاق',
                        { duration: 8000 }
                    );
                    return;
                }

                this.runShopifyOrderImport(this.shopifyOrderPreviewToken);
            },
            error: (err) => {
                this.shopifyOrderPreviewLoading = false;
                const m = err.error?.message || err.message || 'فشل معاينة الطلبات';
                this.snackBar.open(m, 'إغلاق', { duration: 7000 });
            },
        });
    }

    skipUnmatchedAndImportOrders(): void {
        if (!this.shopifyOrderPreviewToken) {
            this.snackBar.open('لا توجد جلسة معاينة — أعد Pull Orders', 'إغلاق', { duration: 5000 });
            return;
        }
        this.runShopifyOrderImport(this.shopifyOrderPreviewToken);
    }

    createSelectedUnmatchedAndImportOrders(): void {
        if (!this.shopifyOrderPreviewToken) {
            this.snackBar.open('لا توجد جلسة معاينة — أعد Pull Orders', 'إغلاق', { duration: 5000 });
            return;
        }

        const selected = this.shopifyUnmatchedProducts.filter((r) => r.include && r.shopify_variant_id);
        if (selected.length === 0) {
            this.snackBar.open('حدّد منتجاً واحداً على الأقل للإضافة، أو استخدم «تخطي والمتابعة»', 'إغلاق', { duration: 5000 });
            return;
        }

        for (const row of selected) {
            if (!row.name?.trim()) {
                this.snackBar.open('أدخل اسم الصنف لكل منتج محدّد', 'إغلاق', { duration: 5000 });
                return;
            }
            if (!row.measurement_id) {
                this.snackBar.open('اختر وحدة القياس لكل منتج محدّد', 'إغلاق', { duration: 5000 });
                return;
            }
        }

        this.shopifyOrderSyncing = true;
        const payload = {
            shop_domain: this.shopifyPreviewShopDomain,
            products: selected.map((r) => ({
                shopify_variant_id: r.shopify_variant_id,
                shopify_product_id: r.shopify_product_id,
                name: r.name.trim(),
                price: r.sell_total_price,
                category_price: r.category_price,
                sku: r.sku,
                stock_id: r.stock_id,
                measurement_id: r.measurement_id,
            })),
        };

        this.http.post(environment.Url + '/shopify/create-unmatched-categories', payload).subscribe({
            next: (res: any) => {
                const createdCount = Array.isArray(res?.created) ? res.created.length : 0;
                if (createdCount === 0) {
                    this.shopifyOrderSyncing = false;
                    this.showShopifyCategoryCreateErrors(res, 'فشل إنشاء الأصناف');
                    return;
                }
                if (Array.isArray(res?.errors) && res.errors.length) {
                    const first = res.errors[0]?.error || 'فشل إنشاء بعض الأصناف';
                    this.snackBar.open(first, 'إغلاق', { duration: 7000 });
                } else {
                    this.snackBar.open(res?.message || `تم إنشاء/ربط ${createdCount} صنف`, 'إغلاق', { duration: 5000 });
                }
                this.runShopifyOrderImport(this.shopifyOrderPreviewToken!);
            },
            error: (err) => {
                this.shopifyOrderSyncing = false;
                this.showShopifyCategoryCreateErrors(err.error, err.error?.message || err.message || 'فشل إنشاء الأصناف');
            },
        });
    }

    private runShopifyOrderImport(previewToken: string | null): void {
        if (!previewToken) {
            this.snackBar.open('انتهت جلسة المعاينة — أعد Pull Orders', 'إغلاق', { duration: 5000 });
            return;
        }

        this.shopifyOrderSyncing = true;
        this.http.post(environment.Url + '/shopify/sync-orders', { preview_token: previewToken }).subscribe({
            next: (res: any) => {
                this.shopifyOrderSyncing = false;
                this.clearShopifyOrderPreview();

                const syncMsg = this.formatShopifyOrderSyncMessage(res);
                const failCount = Number(res?.failed ?? 0);
                this.snackBar.open(syncMsg, 'إغلاق', {
                    duration: failCount > 0 ? 12000 : 7000,
                });
            },
            error: (err) => {
                this.shopifyOrderSyncing = false;
                const m = err.error?.message || err.message || 'فشل مزامنة الطلبات';
                this.snackBar.open(m, 'إغلاق', { duration: 7000 });
            },
        });
    }

    private buildShopifyOrderSyncBody(): Record<string, unknown> | null {
        const hasOne = !!(this.shopifyOrderDateFrom || this.shopifyOrderDateTo);
        const hasBoth = !!(this.shopifyOrderDateFrom && this.shopifyOrderDateTo);
        if (hasOne && !hasBoth) {
            this.snackBar.open('املأ تاريخ البداية والنهاية معاً، أو امسح التاريخين لاستخدام عدد الأيام.', 'إغلاق', { duration: 5000 });
            return null;
        }

        const useRange = hasBoth;
        let days = 0;
        if (!useRange) {
            days = Math.max(0, Math.min(3650, Number(this.shopifyOrderDays) || 0));
        }

        const confirmMsg = useRange
            ? `Pull Orders Shopify الصادرة بين ${this.formatYmd(this.shopifyOrderDateFrom!)} و ${this.formatYmd(this.shopifyOrderDateTo!)}. الطلبات الموجودة ستُحدَّث ولن تُكرَّر. المتابعة؟`
            : days === 0
                ? 'سيتم جلب كل الطلبات من Shopify (قد يستغرق وقتاً طويلاً). الطلبات الموجودة ستُحدَّث. المتابعة؟'
                : `استيراد طلبات Shopify من آخر ${days} يوماً. الطلبات الموجودة ستُحدَّث. المتابعة؟`;

        if (!confirm(confirmMsg)) {
            return null;
        }

        return useRange
            ? {
                date_from: this.formatYmd(this.shopifyOrderDateFrom!),
                date_to: this.formatYmd(this.shopifyOrderDateTo!),
            }
            : { days };
    }

    private toUnmatchedDraft(row: any): ShopifyUnmatchedDraft {
        const sell = Number(row?.price ?? 0) || 0;
        const name = String(row?.expected_erp_name || row?.shopify_name || '').trim();

        return {
            shopify_variant_id: row?.shopify_variant_id != null ? Number(row.shopify_variant_id) : null,
            shopify_product_id: row?.shopify_product_id != null ? Number(row.shopify_product_id) : null,
            shopify_name: row?.shopify_name ?? '',
            expected_erp_name: row?.expected_erp_name ?? name,
            product_title: row?.product_title ?? '',
            variant_label: row?.variant_label ?? '',
            sku: row?.sku ?? null,
            price: sell,
            order_count: Number(row?.order_count ?? 1) || 1,
            include: row?.shopify_variant_id != null,
            name,
            category_price: sell,
            sell_total_price: sell,
            stock_id: this.defaultStockId,
            measurement_id: this.defaultMeasurementId,
        };
    }

    private clearShopifyOrderPreview(): void {
        this.shopifyOrderPreviewToken = null;
        this.shopifyOrderPreviewStats = null;
        this.shopifyUnmatchedProducts = [];
        this.shopifyPreviewShopDomain = null;
    }

    toggleAllUnmatchedInclude(checked: boolean): void {
        this.shopifyUnmatchedProducts.forEach((r) => {
            if (r.shopify_variant_id) {
                r.include = checked;
            }
        });
    }

    selectedUnmatchedCount(): number {
        return this.shopifyUnmatchedProducts.filter((r) => r.include && r.shopify_variant_id).length;
    }

    private formatYmd(d: Date): string {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    trackUnmatchedRow(_index: number, row: ShopifyUnmatchedDraft): string | number {
        return row.shopify_variant_id ?? _index;
    }

    trackShopifyVariantRow(_index: number, row: any): number {
        return row?.shopify_variant_id ?? _index;
    }

    shopifyProductActionLabel(action: string | undefined): string {
        return action === 'created' ? 'جديد' : 'تحديث';
    }

    private formatShopifyOrderSyncMessage(res: any): string {
        const imp = Number(res?.imported ?? 0);
        const skipped = Number(res?.skipped_existing ?? 0);
        const fail = Number(res?.failed ?? 0);
        const fetched = Number(res?.orders_fetched ?? imp + skipped + fail);
        const skippedPart = skipped > 0 ? `، موجود مسبقاً ${skipped} (بدون تعديل)` : '';

        if (imp > 0 && fail === 0) {
            return `تم استيراد ${imp} طلب جديد${skippedPart}`;
        }
        if (imp > 0 && fail > 0) {
            const hint = this.firstShopifySyncErrorHint(res);
            return hint
                ? `تم استيراد ${imp} طلب جديد${skippedPart}، تعذّر ${fail} — ${hint}`
                : `تم استيراد ${imp} طلب جديد${skippedPart}، تعذّر ${fail}`;
        }
        if (skipped > 0 && fail === 0) {
            return `لا طلبات جديدة — ${skipped} طلب موجود مسبقاً (لم يُعدَّل)`;
        }
        if (fail > 0) {
            const hint = this.firstShopifySyncErrorHint(res);
            return hint
                ? `تعذّر استيراد ${fail} من ${fetched} طلب — ${hint}`
                : `تعذّر استيراد ${fail} من ${fetched} طلب`;
        }
        return fetched > 0
            ? `لا طلبات جديدة في النطاق (${fetched} من Shopify)`
            : 'لا توجد طلبات في النطاق المحدد';
    }

    private showShopifyCategoryCreateErrors(body: any, fallback: string): void {
        const errors = Array.isArray(body?.errors) ? body.errors : [];
        const detail = errors[0]?.error || body?.message || fallback;
        this.snackBar.open(detail, 'إغلاق', { duration: 10000 });
    }

    private firstShopifySyncErrorHint(res: any): string {
        const errors = Array.isArray(res?.errors) ? res.errors : [];
        const raw = String(errors[0]?.message ?? '').trim();
        if (!raw) {
            return '';
        }
        if (raw.includes('no importable line items')) {
            return 'بعض الطلبات بلا بنود قابلة للاستيراد';
        }
        if (raw.includes('category_id') || raw.includes('categories')) {
            return 'تحقق من ربط المنتجات أو SHOPIFY_FALLBACK_CATEGORY_ID';
        }
        return raw.length > 100 ? `${raw.slice(0, 100)}…` : raw;
    }
}
