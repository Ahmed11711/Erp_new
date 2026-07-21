import { Component, OnInit } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { map } from 'rxjs/operators';
import { Observable, of } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { environment } from 'src/env/env';

import { AuthService } from '../auth/auth.service';
import { RbacService } from '../core/rbac/rbac.service';
import { RBAC_ROUTE } from '../guards/rbac-route-data';
import { ApproveService } from '../approvals/services/approve.service';
import { CollectionCompanyService } from '../shipping/services/collection-company.service';
import { ManufacturingService } from '../manufacturing/services/manufacturing.service';
import { InvoiceService } from '../purchases/service/invoice.service';
import { NotificationService } from '../notification/service/notification.service';

@Component({
  selector: 'app-home',
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent implements OnInit {
  readonly RBAC = RBAC_ROUTE;

  user!: string;
  userName = '';
  greeting = '';
  todayLabel = '';

  // KPI values (null = still loading / unavailable)
  pendingApprovals: number | null = null;
  todayOrders: number | null = null;
  lateConfirmedOrders: number | null = null;
  todayCollected: number | null = null;

  // Operational pipeline (stepper) counts
  collectPipeline: number | null = null;
  shipPipeline: number | null = null;
  manufacturePipeline: number | null = null;
  purchasePipeline: number | null = null;

  constructor(
    private authService: AuthService,
    public rbac: RbacService,
    private http: HttpClient,
    private approveService: ApproveService,
    private collectionService: CollectionCompanyService,
    private manufacturingService: ManufacturingService,
    private invoiceService: InvoiceService,
    private notificationService: NotificationService
  ) { }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    this.userName = this.authService.userName() || '';
    this.buildGreeting();
    this.loadKpis();
  }

  // ─────────── permission gates ───────────
  get canOrders(): boolean { return this.rbac.can('orders.view'); }
  get canApprovals(): boolean { return this.rbac.canAny([...RBAC_ROUTE.approvals]); }
  get canFinance(): boolean { return this.rbac.canAny([...RBAC_ROUTE.financeTeam]); }
  get canShippingAccounts(): boolean {
    return this.rbac.canAny([...RBAC_ROUTE.shippingAccountsReport]);
  }
  get canCollections(): boolean { return this.canFinance || this.canShippingAccounts; }
  get canManufacture(): boolean { return this.rbac.can('manufacturing.view'); }
  get canPurchases(): boolean { return this.rbac.can('purchases.view'); }

  private buildGreeting(): void {
    const now = new Date();
    const hour = now.getHours();
    this.greeting = hour >= 5 && hour < 12 ? 'صباح الخير' : hour >= 12 && hour < 17 ? 'مساء الخير' : 'مساء الخير';
    try {
      this.todayLabel = new Intl.DateTimeFormat('ar-EG', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
      }).format(now);
    } catch {
      this.todayLabel = now.toLocaleDateString();
    }
  }

  private todayStr(): string {
    const d = new Date();
    const m = ('0' + (d.getMonth() + 1)).slice(-2);
    const day = ('0' + d.getDate()).slice(-2);
    return `${d.getFullYear()}-${m}-${day}`;
  }

  private ordersCount(params: Record<string, string>): Observable<number> {
    const httpParams = new HttpParams({ fromObject: { itemsPerPage: '1', page: '1', ...params } });
    return this.http.get<any>(`${environment.Url}/orders/search`, { params: httpParams }).pipe(
      map(res => Number(res?.total ?? 0)),
      catchError(() => of(0))
    );
  }

  private loadKpis(): void {
    const today = this.todayStr();

    if (this.canApprovals) {
      this.approveService.getApprovals(1, 1, { status: 'pending' }).pipe(
        catchError(() => of({ total: 0 }))
      ).subscribe((res: any) => this.pendingApprovals = Number(res?.total ?? 0));
    }

    if (this.canOrders) {
      this.ordersCount({ order_date: today }).subscribe(c => this.todayOrders = c);
      this.ordersCount({ order_status: 'تم شحن' }).subscribe(c => this.shipPipeline = c);

      this.notificationService.getById().pipe(
        catchError(() => of({ confirmedOrdersCount: 0 }))
      ).subscribe((res: any) => this.lateConfirmedOrders = Number(res?.confirmedOrdersCount ?? 0));
    }

    if (this.canCollections) {
      this.collectionService.accountsReport({ date_from: today, date_to: today }).pipe(
        catchError(() => of({ summary: {} }))
      ).subscribe((res: any) => {
        const summary = res?.summary ?? {};
        this.todayCollected = Number(summary.total_collected ?? 0);
        this.collectPipeline = Number(summary.total_orders_pending ?? 0);
      });
    }

    if (this.canManufacture) {
      this.manufacturingService.confirmed().pipe(
        catchError(() => of([]))
      ).subscribe((res: any) => {
        this.manufacturePipeline = Array.isArray(res) ? res.length : Number(res?.total ?? 0);
      });
    }

    if (this.canPurchases) {
      this.invoiceService.search(1, 1, {}).pipe(
        catchError(() => of({ total: 0 }))
      ).subscribe((res: any) => this.purchasePipeline = Number(res?.total ?? 0));
    }
  }
}
