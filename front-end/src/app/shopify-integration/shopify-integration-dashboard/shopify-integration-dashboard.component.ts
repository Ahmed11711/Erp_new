import { Component, OnDestroy, OnInit } from '@angular/core';
import { Subscription, of, timer } from 'rxjs';
import { switchMap } from 'rxjs/operators';
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

  constructor(private api: ShopifyIntegrationService) {}

  ngOnInit(): void {
    this.refreshAll();
    this.poll = timer(0, 12000)
      .pipe(
        switchMap(() => {
          this.refreshAll();
          return of(null);
        })
      )
      .subscribe();
  }

  ngOnDestroy(): void {
    this.poll?.unsubscribe();
  }

  refreshAll(): void {
    this.loadOrders(1);
    this.loadProducts(1);
    this.loadFailed(1);
  }

  onTabChange(index: number): void {
    this.selectedTab = index;
  }

  loadOrders(page: number): void {
    this.loadingOrders = true;
    this.api.getOrders(page, 25).subscribe({
      next: (res) => {
        this.orders = res.data ?? [];
        this.ordersMeta = res;
        this.loadingOrders = false;
        this.touchSyncOk();
      },
      error: () => {
        this.loadingOrders = false;
        this.syncState = 'error';
      },
    });
  }

  loadProducts(page: number): void {
    this.loadingProducts = true;
    this.api.getProducts(page, 25).subscribe({
      next: (res) => {
        this.products = res.data ?? [];
        this.productsMeta = res;
        this.products.forEach((p: any) => {
          this.productDrafts[p.id] = {
            name: p.name ?? '',
            sku: p.sku ?? '',
            price: Number(p.price ?? 0),
            quantity: Number(p.quantity ?? 0),
          };
        });
        this.loadingProducts = false;
        this.touchSyncOk();
      },
      error: () => {
        this.loadingProducts = false;
        this.syncState = 'error';
      },
    });
  }

  loadFailed(page: number): void {
    this.loadingFailed = true;
    this.api.getFailedJobs(page, 20).subscribe({
      next: (res) => {
        this.failedJobs = res.data ?? [];
        this.failedMeta = res;
        this.loadingFailed = false;
      },
      error: () => {
        this.loadingFailed = false;
      },
    });
  }

  onOrdersPage(e: any): void {
    this.loadOrders((e?.pageIndex ?? 0) + 1);
  }

  onProductsPage(e: any): void {
    this.loadProducts((e?.pageIndex ?? 0) + 1);
  }

  onFailedPage(e: any): void {
    this.loadFailed((e?.pageIndex ?? 0) + 1);
  }

  saveProduct(row: any): void {
    const d = this.productDrafts[row.id];
    if (!d) {
      return;
    }
    this.api.updateProduct(row.id, d).subscribe({
      next: () => this.loadProducts((this.productsMeta?.current_page as number) || 1),
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
}
