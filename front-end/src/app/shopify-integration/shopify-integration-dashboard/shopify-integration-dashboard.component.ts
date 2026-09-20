import { Component, OnDestroy, OnInit } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Subscription, of, timer } from 'rxjs';
import { finalize, switchMap } from 'rxjs/operators';
import { environment } from 'src/env/env';
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
  productSearch = '';
  orderSearch = '';

  /** خيارات عدد العناصر في الصفحة (طلبات / منتجات) */
  readonly pageSizeOptions = [25, 50, 100, 250, 500, 1000];
  ordersPageSize = 25;
  productsPageSize = 25;

  readonly imgUrl = environment.imgUrl;

  lightboxUrl: string | null = null;

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
    this.api.getOrders(page, this.ordersPageSize, this.orderSearch).subscribe({
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

  searchOrders(): void {
    this.loadOrders(1, { silent: false });
  }

  clearOrderSearch(): void {
    this.orderSearch = '';
    this.loadOrders(1, { silent: false });
  }

  loadProducts(page: number, opts?: { silent?: boolean }): void {
    const silent = !!opts?.silent;
    if (!silent) {
      this.loadingProducts = true;
    }
    this.api.getProducts(page, this.productsPageSize, this.productSearch).subscribe({
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

  searchProducts(): void {
    this.loadProducts(1, { silent: false });
  }

  clearProductSearch(): void {
    this.productSearch = '';
    this.loadProducts(1, { silent: false });
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

  isDirty(id: number): boolean {
    return this.dirtyProductIds.has(id);
  }

  productThumb(p: any): string | null {
    if (p?.image_url_1) {
      return String(p.image_url_1);
    }
    if (p?.image_url_2) {
      return String(p.image_url_2);
    }
    const catImg = p?.category?.category_image;
    if (catImg && catImg !== 'no-image.png') {
      return `${this.imgUrl}${catImg}`;
    }
    return null;
  }

  productGallery(p: any): string[] {
    const urls: string[] = [];
    if (p?.image_url_1) {
      urls.push(String(p.image_url_1));
    }
    if (p?.image_url_2 && p.image_url_2 !== p.image_url_1) {
      urls.push(String(p.image_url_2));
    }
    return urls;
  }

  openLightbox(url: string | null | undefined): void {
    if (!url) {
      return;
    }
    this.lightboxUrl = url;
  }

  closeLightbox(): void {
    this.lightboxUrl = null;
  }

  stockClass(qty: number): string {
    if (qty > 5) {
      return 'stock-ok';
    }
    if (qty > 0) {
      return 'stock-low';
    }
    return 'stock-out';
  }

  onOrdersPage(e: any): void {
    const nextSize = Number(e?.pageSize) || this.ordersPageSize;
    const sizeChanged = nextSize !== this.ordersPageSize;
    this.ordersPageSize = nextSize;
    this.loadOrders(sizeChanged ? 1 : (e?.pageIndex ?? 0) + 1, { silent: false });
  }

  onProductsPage(e: any): void {
    const nextSize = Number(e?.pageSize) || this.productsPageSize;
    const sizeChanged = nextSize !== this.productsPageSize;
    this.productsPageSize = nextSize;
    this.loadProducts(sizeChanged ? 1 : (e?.pageIndex ?? 0) + 1, { silent: false });
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
    if (s.includes('paid') || s.includes('fulfill') || s === 'تم التحصيل') {
      return 'badge-ok';
    }
    if (s.includes('pending') || s.includes('unpaid') || s.includes('partial') || s === 'طلب جديد') {
      return 'badge-warn';
    }
    if (s.includes('cancel') || s.includes('fail') || s.includes('void')) {
      return 'badge-bad';
    }
    if (!s || s === '—' || s === 'unfulfilled') {
      return 'badge-neutral';
    }
    return 'badge-neutral';
  }

  fulfillmentLabel(status: string | null | undefined): string {
    const s = String(status || '').trim();
    if (!s) {
      return 'unfulfilled';
    }
    return s;
  }

  money(value: unknown): string {
    const n = Number(value);
    if (!Number.isFinite(n)) {
      return '—';
    }
    return Number.isInteger(n) ? String(n) : n.toFixed(2);
  }

  orderAddress(o: any): string {
    return [o?.governorate, o?.city, o?.address].filter((x) => !!String(x || '').trim()).join(' — ');
  }

  orderLines(o: any): any[] {
    return Array.isArray(o?.order_products) ? o.order_products : [];
  }

  orderLineName(line: any): string {
    return (
      String(line?.special_details || '').trim() ||
      String(line?.category?.category_name || '').trim() ||
      'صنف'
    );
  }

  orderLineImage(line: any): string | null {
    const img = line?.category?.category_image;
    if (!img) {
      return null;
    }
    if (String(img).startsWith('http')) {
      return String(img);
    }
    return `${this.imgUrl}${img}`;
  }

  openOrderDetails(orderId: number): void {
    if (!orderId) {
      return;
    }
    window.open(`/dashboard/shipping/orderdetails/${orderId}`, '_blank');
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
