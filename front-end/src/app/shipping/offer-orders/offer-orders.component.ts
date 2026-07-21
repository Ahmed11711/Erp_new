import { BreakpointObserver } from '@angular/cdk/layout';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { PageEvent } from '@angular/material/paginator';
import { Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from 'src/app/auth/auth.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { environment } from 'src/env/env';
import { OrderService } from '../services/order.service';
import { OrderInvoicePrintService } from '../services/order-invoice-print.service';
import { OfferQuotationExportService } from 'src/app/permissions/services/offer-quotation-export.service';
import { canShowCollectOrderMenu } from '../utils/order-collect-eligibility.utils';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-offer-orders',
  templateUrl: './offer-orders.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './offer-orders.component.css'],
})
export class OfferOrdersComponent implements OnInit, OnDestroy {
  orders: any[] = [];
  loading = false;
  user = '';

  length = 0;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15, 50, 100];

  customerName = '';
  orderNumber = '';
  offerId = '';

  isMobileView = false;
  mobileExpandedIds = new Set<number>();

  selectedOrders: any[] = [];
  allOrdersSelected = false;
  printSelectedLoading = false;
  downloadingInvoiceId: number | null = null;
  downloadingOfferId: number | null = null;
  exportingListPdf = false;

  private searchTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly destroy$ = new Subject<void>();

  constructor(
    private http: HttpClient,
    private order: OrderService,
    private orderInvoicePrint: OrderInvoicePrintService,
    private quotationExport: OfferQuotationExportService,
    private authService: AuthService,
    public rbac: RbacService,
    private breakpointObserver: BreakpointObserver,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.user = this.authService.getUser();

    this.breakpointObserver
      .observe(['(max-width: 767.98px)'])
      .pipe(takeUntil(this.destroy$))
      .subscribe((state) => {
        this.isMobileView = state.matches;
        if (!state.matches) {
          this.mobileExpandedIds = new Set();
        }
      });

    this.load();
  }

  ngOnDestroy(): void {
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }
    this.destroy$.next();
    this.destroy$.complete();
  }

  onFilterInput(): void {
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }
    this.searchTimer = setTimeout(() => {
      this.page = 0;
      this.load();
    }, 350);
  }

  clearFilters(): void {
    this.customerName = '';
    this.orderNumber = '';
    this.offerId = '';
    this.page = 0;
    this.load();
  }

  handlePage(event: PageEvent): void {
    this.page = event.pageIndex;
    this.pageSize = event.pageSize;
    this.load();
  }

  load(): void {
    this.loading = true;
    let params = new HttpParams()
      .set('itemsPerPage', String(this.pageSize))
      .set('page', String(this.page + 1))
      .set('from_offer', '1');

    const name = this.customerName.trim();
    const orderNo = this.orderNumber.trim();
    const offerNo = this.offerId.trim();

    if (name) {
      params = params.set('customer_name', name);
    }
    if (orderNo) {
      params = params.set('order_number', orderNo);
    }
    if (offerNo) {
      params = params.set('offer_id', offerNo);
    }

    this.http.get<any>(`${environment.Url}/orders/search`, { params }).subscribe({
      next: (res) => {
        this.orders = res?.data || [];
        this.length = res?.total || 0;
        this.pageSize = res?.per_page || this.pageSize;
        this.loading = false;
        this.syncSelectAllState();
      },
      error: () => {
        this.orders = [];
        this.length = 0;
        this.loading = false;
        this.selectedOrders = [];
        this.allOrdersSelected = false;
      },
    });
  }

  isOrderSelected(item: any): boolean {
    return this.selectedOrders.some((o) => o?.id === item?.id);
  }

  toggleSelectAllPrint(event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;
    this.allOrdersSelected = checked;
    this.selectedOrders = checked ? [...(this.orders || [])] : [];
  }

  selectOrder(event: Event, item: any): void {
    const checked = (event.target as HTMLInputElement).checked;
    if (checked) {
      if (!this.isOrderSelected(item)) {
        this.selectedOrders = [...this.selectedOrders, item];
      }
    } else {
      this.selectedOrders = this.selectedOrders.filter((o) => o?.id !== item?.id);
    }
    this.syncSelectAllState();
  }

  private syncSelectAllState(): void {
    const visible = this.orders || [];
    const visibleIds = new Set(visible.map((o) => o?.id));
    this.selectedOrders = this.selectedOrders.filter((o) => visibleIds.has(o?.id));
    this.allOrdersSelected =
      visible.length > 0 && visible.every((item) => this.isOrderSelected(item));
  }

  printSelectedOrders(): void {
    if (!this.selectedOrders.length) {
      Swal.fire({ icon: 'info', text: 'اختر طلبًا واحدًا على الأقل للطباعة.' });
      return;
    }

    const orderIds = this.selectedOrders.map((item) => Number(item.id)).filter((id) => id > 0);
    if (!orderIds.length) {
      Swal.fire({ icon: 'info', text: 'اختر طلبًا واحدًا على الأقل للطباعة.' });
      return;
    }

    this.printSelectedLoading = true;
    this.order.printOrders(orderIds).subscribe({
      next: (res) => {
        this.printSelectedLoading = false;
        const opened = this.orderInvoicePrint.openPrintWindow(res.orders || [], {
          showInvoiceDate: res.show_invoice_date !== false,
          size: 'A4',
        });
        if (!opened) {
          Swal.fire({
            icon: 'warning',
            text: 'تعذر فتح نافذة الطباعة. تأكد من السماح بالنوافذ المنبثقة.',
          });
        }
      },
      error: (err) => {
        this.printSelectedLoading = false;
        Swal.fire({
          icon: 'error',
          text: err?.error?.message || 'تعذر تحضير الفواتير للطباعة',
        });
      },
    });
  }

  printOneOrder(item: any): void {
    const id = Number(item?.id);
    if (!id) {
      return;
    }
    this.printSelectedLoading = true;
    this.order.printOrders([id]).subscribe({
      next: (res) => {
        this.printSelectedLoading = false;
        const opened = this.orderInvoicePrint.openPrintWindow(res.orders || [], {
          showInvoiceDate: res.show_invoice_date !== false,
          size: 'A4',
        });
        if (!opened) {
          Swal.fire({
            icon: 'warning',
            text: 'تعذر فتح نافذة الطباعة. تأكد من السماح بالنوافذ المنبثقة.',
          });
        }
      },
      error: (err) => {
        this.printSelectedLoading = false;
        Swal.fire({
          icon: 'error',
          text: err?.error?.message || 'تعذر تحضير الفاتورة للطباعة',
        });
      },
    });
  }

  /** Download source quotation PDF directly. */
  downloadOfferPdf(item: any): void {
    const offerId = Number(item?.offer_id);
    if (!offerId || this.downloadingOfferId) {
      return;
    }
    this.downloadingOfferId = offerId;
    this.quotationExport
      .downloadByOfferId(offerId)
      .catch(() => Swal.fire('خطأ', 'تعذر تحميل عرض السعر PDF', 'error'))
      .finally(() => {
        this.downloadingOfferId = null;
      });
  }

  /** Download order invoice as PDF file. */
  downloadInvoicePdf(item: any): void {
    const id = Number(item?.id);
    if (!id || this.downloadingInvoiceId) {
      return;
    }
    this.downloadingInvoiceId = id;
    this.order.printOrders([id]).subscribe({
      next: (res) => {
        this.orderInvoicePrint
          .downloadPdf(res.orders || [], {
            showInvoiceDate: res.show_invoice_date !== false,
            size: 'A4',
          })
          .catch(() => Swal.fire('خطأ', 'تعذر تحميل فاتورة PDF', 'error'))
          .finally(() => {
            this.downloadingInvoiceId = null;
          });
      },
      error: (err) => {
        this.downloadingInvoiceId = null;
        Swal.fire({
          icon: 'error',
          text: err?.error?.message || 'تعذر تحضير الفاتورة',
        });
      },
    });
  }

  downloadSelectedInvoicesPdf(): void {
    if (!this.selectedOrders.length) {
      Swal.fire({ icon: 'info', text: 'اختر طلبًا واحدًا على الأقل.' });
      return;
    }
    const orderIds = this.selectedOrders.map((item) => Number(item.id)).filter((id) => id > 0);
    if (!orderIds.length) {
      return;
    }
    this.printSelectedLoading = true;
    this.order.printOrders(orderIds).subscribe({
      next: (res) => {
        this.orderInvoicePrint
          .downloadPdf(res.orders || [], {
            showInvoiceDate: res.show_invoice_date !== false,
            size: 'A4',
          })
          .catch(() => Swal.fire('خطأ', 'تعذر تحميل فواتير PDF', 'error'))
          .finally(() => {
            this.printSelectedLoading = false;
          });
      },
      error: (err) => {
        this.printSelectedLoading = false;
        Swal.fire({
          icon: 'error',
          text: err?.error?.message || 'تعذر تحضير الفواتير',
        });
      },
    });
  }

  exportListPdf(): void {
    if (!this.orders?.length || this.exportingListPdf) {
      Swal.fire({ icon: 'info', text: 'لا توجد طلبات في الصفحة الحالية للتصدير.' });
      return;
    }
    this.exportingListPdf = true;
    this.quotationExport
      .downloadOfferOrdersListPdf(this.orders, `offer-orders-page-${this.page + 1}`)
      .catch(() => Swal.fire('خطأ', 'تعذر تصدير قائمة الطلبات', 'error'))
      .finally(() => {
        this.exportingListPdf = false;
      });
  }

  /** Open source quotation detail page. */
  openOfferQuotation(item: any): void {
    const offerId = Number(item?.offer_id);
    if (!offerId) {
      return;
    }
    this.http.get<any>(`${environment.Url}/offer/${offerId}`).subscribe({
      next: (res) => {
        const path = res?.offer === 'offer2' ? 'offer2' : 'offer1';
        this.router.navigate(['/dashboard/permissions', path, offerId]);
      },
      error: (err) => {
        Swal.fire('خطأ', err?.error?.message || 'تعذر فتح عرض السعر', 'error');
      },
    });
  }

  trackById(_index: number, item: any): number {
    return item?.id;
  }

  toggleMobileOrderCard(id: number): void {
    const next = new Set(this.mobileExpandedIds);
    if (next.has(id)) {
      next.delete(id);
    } else {
      next.add(id);
    }
    this.mobileExpandedIds = next;
  }

  isMobileOrderExpanded(id: number): boolean {
    return this.mobileExpandedIds.has(id);
  }

  getStatusColor(status: string): string {
    switch (status) {
      case 'طلب جديد':
      case 'جديد':
        return 'new';
      case 'طلب مؤكد':
        return 'confirmed';
      case 'تم شحن':
        return 'shipped';
      case 'شحن جزئي':
        return 'partshipped';
      case 'تم الاستلام':
        return 'received';
      case 'مؤجل':
        return 'postponed';
      case 'رفض استلام':
        return 'refuse';
      case 'ملغي':
        return 'canceled';
      case 'تم التحصيل':
        return 'collected';
      case 'تم التسليم':
        return 'delivered';
      case 'تم الصيانة':
        return 'fixed';
      case 'أرشيف':
        return 'archived';
      default:
        return '';
    }
  }

  canConfirmMenu(): boolean {
    return ['Admin', 'Customer Service', 'Shipping Management', 'Operation Management', 'Finance and operations management'].includes(this.user)
      || this.rbac.can('orders.convert_from_offer');
  }

  canConfirm(item: any): boolean {
    return ['جديد', 'طلب جديد', 'تم الصيانة'].includes(item?.order_status);
  }

  canShipMenu(): boolean {
    return [
      'Admin',
      'Operation Management',
      'Finance and operations management',
      'Operation Specialist',
      'Logistics Specialist',
      'Shipping Management',
    ].includes(this.user) || this.rbac.canAny(['orders.change_status', 'orders.assign_driver', 'orders.convert_from_offer']);
  }

  canShip(item: any): boolean {
    return ['طلب جديد', 'طلب مؤكد', 'شحن جزئي', 'مؤجل'].includes(item?.order_status);
  }

  canDeliverMenu(): boolean {
    return this.canShipMenu();
  }

  canDeliver(item: any): boolean {
    return ['تم شحن', 'شحن جزئي'].includes(item?.order_status);
  }

  canCollectMenu(): boolean {
    return [
      'Admin',
      'Operation Management',
      'Finance and operations management',
      'Operation Specialist',
      'Logistics Specialist',
    ].includes(this.user) || this.rbac.canAny(['orders.change_status', 'orders.convert_from_offer']);
  }

  canCollect(item: any): boolean {
    // ذمة العميل — يظهر التحصيل بعد الشحن/التسليم حتى بدون شركة شحن
    const status = item?.order_status ?? '';
    if (!['تم شحن', 'تم التسليم'].includes(status)) {
      return false;
    }
    if (canShowCollectOrderMenu(item)) {
      return true;
    }
    return (parseFloat(item?.net_total) || 0) > 0.009;
  }

  canPartCollectMenu(): boolean {
    return ['Admin', 'Operation Management', 'Finance and operations management'].includes(this.user)
      || this.rbac.can('orders.convert_from_offer');
  }

  canPartCollect(item: any): boolean {
    return ['طلب جديد', 'طلب مؤكد', 'شحن جزئي'].includes(item?.order_status);
  }

  canEdit(item: any): boolean {
    if (!this.rbac.can('orders.edit') && this.user !== 'Admin') {
      return false;
    }
    return ['طلب جديد', 'طلب مؤكد', 'شحن جزئي'].includes(item?.order_status)
      && item?.order_status !== 'تم شحن';
  }

  hasShippingCompany(item: any): boolean {
    return !!(item?.order_details?.shipping_company_id || item?.order_details?.shipping_provider_id);
  }

  confirmDelivery(item: any): void {
    const noteHint = this.hasShippingCompany(item)
      ? 'إن وُجدت شركة شحن يمكن نقل الذمة لاحقاً — هنا الذمة الأساسية على العميل.'
      : 'الذمة على عميل الشركة — بدون نقل لمندوب أو شركة شحن.';

    Swal.fire({
      title: 'تأكيد التسليم',
      html: `<div style="text-align:right;direction:rtl">${noteHint}</div>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'نعم، تم التسليم',
      cancelButtonText: 'إلغاء',
      input: 'text',
      inputPlaceholder: 'ملاحظة (اختياري)',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.order.deliverOrder(item.id, { note: result.value || '' }).subscribe({
        next: (res: any) => {
          if (res.message === 'success') {
            Swal.fire('تم', 'تم تأكيد التسليم — الذمة على العميل', 'success');
            this.load();
          }
        },
        error: (err) => {
          Swal.fire('خطأ', err?.error?.message || 'فشل تأكيد التسليم', 'error');
        },
      });
    });
  }

  cancelOrder(item: any): void {
    Swal.fire({
      title: 'إلغاء الطلب؟',
      input: 'text',
      inputPlaceholder: 'سبب الإلغاء',
      showCancelButton: true,
      confirmButtonText: 'إلغاء الطلب',
      cancelButtonText: 'رجوع',
      inputValidator: (v) => (!v ? 'أدخل السبب' : undefined),
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      // طلبات العروض غالباً بدون مقدمة — إلغاء بملاحظة فقط (مبلغ 0)
      this.order.chngeStatus(item.id, 'cancel', result.value || '', 0, 0, 0).subscribe({
        next: () => {
          Swal.fire('تم', 'تم إلغاء الطلب', 'success');
          this.load();
        },
        error: (err) => {
          Swal.fire('خطأ', err?.error?.message || 'فشل الإلغاء', 'error');
        },
      });
    });
  }

  canCancel(item: any): boolean {
    return ['طلب جديد', 'طلب مؤكد', 'مؤجل'].includes(item?.order_status);
  }
}
