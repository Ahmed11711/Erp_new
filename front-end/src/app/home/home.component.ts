import { Component, OnInit } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
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

const SKIP_LOADING = new HttpHeaders({ 'X-Skip-Global-Loading': '1' });

interface SalesTopCategory {
  category_id: number;
  category_name: string;
  total_new: number;
  total_orders: number;
  net_sales: number | null;
}

interface SalesReportCard {
  title: string;
  hint: string;
  icon: string;
  route: string;
  tone: 'brand' | 'green' | 'blue' | 'amber' | 'slate' | 'rose';
}

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

  salesMonthLabel = '';
  salesLoading = false;
  salesInvoiceNet: number | null = null;
  salesInvoiceTotal: number | null = null;
  salesOrdersCount: number | null = null;
  salesAvgOrder: number | null = null;
  salesMissingInvoices: number | null = null;
  salesGrossProfit: number | null = null;
  salesMargin: number | null = null;
  salesTopCategories: SalesTopCategory[] = [];
  salesTopMax = 0;

  readonly salesReportCards: SalesReportCard[] = [
    {
      title: 'حركة المبيعات',
      hint: 'فواتير الفترة والقيود والفواتير الناقصة',
      icon: 'receipt_long',
      route: '/dashboard/shipping/sales-movement',
      tone: 'brand',
    },
    {
      title: 'مبيعات المنتجات',
      hint: 'قيمة وكميات كل منتج خلال الفترة',
      icon: 'sell',
      route: '/dashboard/reports/productsales',
      tone: 'green',
    },
    {
      title: 'تقارير الأصناف',
      hint: 'صافي المبيعات والمرتجع وهامش الربح',
      icon: 'category',
      route: '/dashboard/categoriesreports',
      tone: 'blue',
    },
    {
      title: 'طلبات الأصناف',
      hint: 'توزيع الطلبات حسب الحالة التشغيلية',
      icon: 'assignment',
      route: '/dashboard/categoriesstatusreports',
      tone: 'amber',
    },
    {
      title: 'ربحية الصنف',
      hint: 'التكلفة ومجمل الربح وهامش الربحية',
      icon: 'trending_up',
      route: '/dashboard/reports/category',
      tone: 'rose',
    },
    {
      title: 'أداء المنتجات',
      hint: 'مقارنة أداء المنتجات خلال الفترة',
      icon: 'insights',
      route: '/dashboard/reports/product-performance',
      tone: 'slate',
    },
    {
      title: 'تقرير الأوردرات',
      hint: 'تفاصيل الطلبات والحسابات المرتبطة',
      icon: 'summarize',
      route: '/dashboard/financial/report-order-new',
      tone: 'brand',
    },
  ];

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
    if (this.isAdmin) {
      this.loadSalesHub();
    }
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
  get isAdmin(): boolean {
    return this.rbac.can('system.rbac') || this.user == 'Admin';
  }

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
      this.manufacturingService.confirmedCount().pipe(
        catchError(() => of({ total: 0 }))
      ).subscribe((res) => {
        this.manufacturePipeline = Number(res?.total ?? 0);
      });
    }

    if (this.canPurchases) {
      this.invoiceService.search(1, 1, {}).pipe(
        catchError(() => of({ total: 0 }))
      ).subscribe((res: any) => this.purchasePipeline = Number(res?.total ?? 0));
    }
  }

  private monthRange(): { from: string; to: string } {
    const now = new Date();
    const m = ('0' + (now.getMonth() + 1)).slice(-2);
    const day = ('0' + now.getDate()).slice(-2);
    return {
      from: `${now.getFullYear()}-${m}-01`,
      to: `${now.getFullYear()}-${m}-${day}`,
    };
  }

  private loadSalesHub(): void {
    const { from, to } = this.monthRange();
    try {
      this.salesMonthLabel = new Intl.DateTimeFormat('ar-EG', { month: 'long', year: 'numeric' }).format(new Date());
    } catch {
      this.salesMonthLabel = `${from} — ${to}`;
    }
    this.salesLoading = true;

    const movementParams = new HttpParams({
      fromObject: {
        date_from: from,
        date_to: to,
        page: '1',
        itemsPerPage: '1',
        mode: 'order_date',
        summary_only: '1',
      },
    });
    this.http.get<any>(`${environment.Url}/orders/sales-movement`, {
      headers: SKIP_LOADING,
      params: movementParams,
    }).pipe(catchError(() => of(null))).subscribe((res) => {
      const summary = res?.summary ?? {};
      const net = Number(summary.invoice_net ?? 0);
      const total = Number(summary.invoice_total ?? 0);
      const count = Number(summary.orders_count ?? 0);
      this.salesInvoiceNet = net;
      this.salesInvoiceTotal = total;
      this.salesOrdersCount = count;
      this.salesAvgOrder = count > 0 ? net / count : 0;
      this.salesMissingInvoices = Number(summary.missing_invoice_count ?? 0);
      this.salesLoading = false;
    });

    const sellParams = new HttpParams({
      fromObject: { itemsPerPage: '5', page: '1', date_from: from, date_to: to, sort: 'total_new' },
    });
    this.http.get<any>(`${environment.Url}/reports/categoriesSellReports`, {
      headers: SKIP_LOADING,
      params: sellParams,
    }).pipe(catchError(() => of(null))).subscribe((res) => {
      const totals = res?.profitability_totals ?? {};
      this.salesGrossProfit = totals.gross_profit != null ? Number(totals.gross_profit) : null;
      this.salesMargin = totals.gross_margin_percent != null ? Number(totals.gross_margin_percent) : null;
      const rows = Array.isArray(res?.data) ? res.data : [];
      this.salesTopCategories = rows.slice(0, 5).map((row: any) => ({
        category_id: Number(row.category_id ?? 0),
        category_name: String(row.category_name ?? '—'),
        total_new: Number(row.total_new ?? 0),
        total_orders: Number(row.total_orders ?? 0),
        net_sales: row.net_sales != null ? Number(row.net_sales) : null,
      }));
      this.salesTopMax = Math.max(...this.salesTopCategories.map((c) => c.total_new), 0);
    });
  }

  topBarWidth(value: number): string {
    if (!this.salesTopMax || this.salesTopMax <= 0) {
      return '0%';
    }
    return `${Math.max(8, Math.round((value / this.salesTopMax) * 100))}%`;
  }
}
