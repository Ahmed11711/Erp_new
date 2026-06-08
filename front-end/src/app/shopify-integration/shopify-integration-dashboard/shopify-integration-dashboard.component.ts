import { Component, OnDestroy, OnInit } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Subscription, of, timer } from 'rxjs';
import { finalize, switchMap } from 'rxjs/operators';
import { ShopifyIntegrationService } from '../shopify-integration.service';

@Component({
  selector: 'app-shopify-integration-dashboard',
  templateUrl: './shopify-integration-dashboard.component.html',
  styleUrls: ['./shopify-integration-dashboard.component.css'],
})
export class ShopifyIntegrationDashboardComponent implements OnInit, OnDestroy {
  orders: any[] = [];
  products: any[] = [];
  failedJobs: any[] = [];

  ordersMeta: any = null;
  productsMeta: any = null;
  failedMeta: any = null;

  loadingOrders = false;
  loadingProducts = false;
  loadingFailed = false;

  lastSyncedAt: Date | null = null;
  syncState: 'idle' | 'ok' | 'error' = 'idle';

  private poll?: Subscription;

  selectedTab = 0;

  productDrafts: Record<number, { name: string; sku: string; price: number; quantity: number }> = {};
  /** صفوف المنتجات التي يحررها المستخدم — لا تُستبدل من السيرفر أثناء التحديث الصامت. */
  private dirtyProductIds = new Set<number>();

  savingProductId: number | null = null;

  /** فلتر اختياري: آخر N يوم؛ فارغ = جلب كل المنتجات من Shopify. */
  productSyncDays: number | null = null;
  syncingFromShopify = false;

  displayedOrderColumns = [
    'id',
    'reference_number',
    'customer_name',
    'net_total',
    'financial',
    'fulfillment',
    'shipping',
    'order_status',
  ];

  displayedProductColumns = ['id', 'name', 'sku', 'price', 'quantity', 'actions'];
  displayedFailedColumns = ['id', 'queue', 'failed_at', 'preview'];

  constructor(
    private api: ShopifyIntegrationService,
    private snack: MatSnackBar,
  ) {}

  ngOnInit(): void {
    this.refreshAll();
    this.poll = timer(45000, 45000)
      .pipe(
        switchMap(() => {
          this.pollRefreshSilent();
          return of(null);
        }),
      )
      .subscribe();
  }

  ngOnDestroy(): void {
    this.poll?.unsubscribe();
  }

  /** تحديث يدوي كامل مع مؤشر التحميل في كل تبويب. */
  refreshAll(): void {
    const op = (this.ordersMeta?.current_page as number) || 1;
    const pp = (this.productsMeta?.current_page as number) || 1;
    const fp = (this.failedMeta?.current_page as number) || 1;
    this.loadOrders(op, { silent: false });
    this.loadProducts(pp, { silent: false });
    this.loadFailed(fp, { silent: false });
  }

  /** تحديث دوري: بدون سبينر عام وبدون إخفاء جدول المنتجات؛ الطلبات فقط + المهام الفاشلة؛ المنتجات إن كان التبويب مفتوحاً. */
  private pollRefreshSilent(): void {
    const op = (this.ordersMeta?.current_page as number) || 1;
    const pp = (this.productsMeta?.current_page as number) || 1;
    const fp = (this.failedMeta?.current_page as number) || 1;
    this.loadOrders(op, { silent: true });
    if (this.selectedTab === 1) {
      this.loadProducts(pp, { silent: true });
    }
    this.loadFailed(fp, { silent: true });
    this.lastSyncedAt = new Date();
  }

  onTabChange(index: number): void {
    this.selectedTab = index;
    if (index === 1) {
      const pp = (this.productsMeta?.current_page as number) || 1;
      this.loadProducts(pp, { silent: true });
    }
  }

  loadOrders(page: number, opts?: { silent?: boolean }): void {
    const silent = !!opts?.silent;
    if (!silent) {
      this.loadingOrders = true;
    }
    this.api.getOrders(page, 25).subscribe({
      next: (res) => {
        this.orders = res.data ?? [];
        this.ordersMeta = res;
        if (!silent) {
          this.loadingOrders = false;
        }
        this.touchSyncOk();
      },
      error: () => {
        if (!silent) {
          this.loadingOrders = false;
        }
        this.syncState = 'error';
      },
    });
  }

  loadProducts(page: number, opts?: { silent?: boolean }): void {
    const silent = !!opts?.silent;
    if (!silent) {
      this.loadingProducts = true;
    }
    this.api.getProducts(page, 25).subscribe({
      next: (res) => {
        this.products = res.data ?? [];
        this.productsMeta = res;
        this.mergeProductDraftsFromServer();
        if (!silent) {
          this.loadingProducts = false;
        }
        this.touchSyncOk();
      },
      error: () => {
        if (!silent) {
          this.loadingProducts = false;
        }
        this.syncState = 'error';
      },
    });
  }

  loadFailed(page: number, opts?: { silent?: boolean }): void {
    const silent = !!opts?.silent;
    if (!silent) {
      this.loadingFailed = true;
    }
    this.api.getFailedJobs(page, 20).subscribe({
      next: (res) => {
        this.failedJobs = res.data ?? [];
        this.failedMeta = res;
        if (!silent) {
          this.loadingFailed = false;
        }
      },
      error: () => {
        if (!silent) {
          this.loadingFailed = false;
        }
      },
    });
  }

  private mergeProductDraftsFromServer(): void {
    const nextIds = new Set<number>();
    this.products.forEach((p: any) => {
      nextIds.add(p.id);
      if (!this.dirtyProductIds.has(p.id)) {
        this.productDrafts[p.id] = {
          name: p.name ?? '',
          sku: p.sku ?? '',
          price: Number(p.price ?? 0),
          quantity: Number(p.quantity ?? 0),
        };
      } else if (!this.productDrafts[p.id]) {
        this.productDrafts[p.id] = {
          name: p.name ?? '',
          sku: p.sku ?? '',
          price: Number(p.price ?? 0),
          quantity: Number(p.quantity ?? 0),
        };
      }
    });
    Object.keys(this.productDrafts).forEach((k) => {
      const id = Number(k);
      if (!nextIds.has(id)) {
        delete this.productDrafts[id];
        this.dirtyProductIds.delete(id);
      }
    });
  }

  markProductDirty(id: number): void {
    this.dirtyProductIds.add(id);
  }

  onOrdersPage(e: any): void {
    this.loadOrders((e?.pageIndex ?? 0) + 1, { silent: false });
  }

  onProductsPage(e: any): void {
    this.loadProducts((e?.pageIndex ?? 0) + 1, { silent: false });
  }

  onFailedPage(e: any): void {
    this.loadFailed((e?.pageIndex ?? 0) + 1, { silent: false });
  }

  saveProduct(row: any): void {
    const d = this.productDrafts[row.id];
    if (!d) {
      this.snack.open('لا توجد بيانات للحفظ في هذا الصف.', 'حسناً', { duration: 3500 });
      return;
    }
    this.savingProductId = row.id;
    this.api
      .updateProduct(row.id, d)
      .pipe(
        finalize(() => {
          this.savingProductId = null;
        }),
      )
      .subscribe({
        next: () => {
          this.dirtyProductIds.delete(row.id);
          this.snack.open('تم الحفظ وتمت إضافة المزامنة مع Shopify إلى الطابور.', 'حسناً', { duration: 4500 });
          this.loadProducts((this.productsMeta?.current_page as number) || 1, { silent: true });
        },
        error: (err) => {
          const msg =
            err?.error?.message ||
            err?.error?.error ||
            (typeof err?.error === 'string' ? err.error : null) ||
            (err?.status === 0 ? 'تعذر الاتصال بالخادم.' : `فشل الحفظ (HTTP ${err?.status ?? '؟'}).`);
          this.snack.open(msg, 'إغلاق', { duration: 7000 });
        },
      });
  }

  badgeClass(status: string | null | undefined): string {
    const s = (status || '').toLowerCase();
    if (s.includes('paid') || s.includes('fulfil')) {
      return 'badge-ok';
    }
    if (s.includes('pending') || s.includes('unpaid')) {
      return 'badge-warn';
    }
    if (s.includes('cancel') || s.includes('fail')) {
      return 'badge-bad';
    }
    return 'badge-neutral';
  }

  private touchSyncOk(): void {
    this.lastSyncedAt = new Date();
    this.syncState = 'ok';
  }

  pullProductsFromShopify(): void {
    if (this.syncingFromShopify) {
      return;
    }
    const d = this.productSyncDays;
    const body =
      d != null && !Number.isNaN(Number(d)) && Number(d) > 0 ? { days: Math.min(3650, Math.floor(Number(d))) } : undefined;

    this.syncingFromShopify = true;
    this.api
      .syncProductMappings(body)
      .pipe(finalize(() => (this.syncingFromShopify = false)))
      .subscribe({
        next: (res) => {
          const n = res?.synced_variants ?? res?.variants_created;
          const extra =
            res?.variants_created != null && res?.variants_updated != null
              ? ` (جديد: ${res.variants_created}، تحديث: ${res.variants_updated})`
              : '';
          this.snack.open(
            res?.message
              ? `${res.message}${typeof n === 'number' ? ` — ${n} متغير.${extra}` : ''}`
              : `تم جلب المنتجات من Shopify.${typeof n === 'number' ? ` ${n} متغير.${extra}` : ''}`,
            'حسناً',
            { duration: 6000 },
          );
          this.loadProducts((this.productsMeta?.current_page as number) || 1, { silent: false });
          this.touchSyncOk();
        },
        error: (err) => {
          const msg =
            err?.error?.message ||
            (typeof err?.error === 'string' ? err.error : null) ||
            (err?.status === 0 ? 'تعذر الاتصال بالخادم.' : `فشل الجلب (HTTP ${err?.status ?? '؟'}).`);
          this.snack.open(msg, 'إغلاق', { duration: 8000 });
          this.syncState = 'error';
        },
      });
  }
}
