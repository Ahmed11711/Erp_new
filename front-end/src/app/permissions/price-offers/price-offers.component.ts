import { BreakpointObserver } from '@angular/cdk/layout';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import Swal from 'sweetalert2';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { OfferService } from '../services/offer.service';
import { OfferQuotationExportService } from '../services/offer-quotation-export.service';

@Component({
  selector: 'app-price-offers',
  templateUrl: './price-offers.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './price-offers.component.css']
})
export class PriceOffersComponent implements OnInit, OnDestroy {

  offers: any[] = [];

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15, 50, 100];

  quoteSearch = '';
  companySearch = '';
  /** فلتر ثابت من صفحة عملاء الشركات */
  filterCompanyId: number | null = null;
  filterCompanyLabel = '';
  loading = false;
  exportingListPdf = false;
  downloadingOfferId: number | null = null;

  isMobileView = false;
  mobileExpandedIds = new Set<number>();
  private readonly destroy$ = new Subject<void>();

  constructor(
    private offerService: OfferService,
    private quotationExport: OfferQuotationExportService,
    private breakpointObserver: BreakpointObserver,
    private route: ActivatedRoute,
    private router: Router,
    public rbac: RbacService,
  ) {}

  ngOnInit(): void {
    this.breakpointObserver
      .observe(['(max-width: 767.98px)'])
      .pipe(takeUntil(this.destroy$))
      .subscribe((state) => {
        this.isMobileView = state.matches;
        if (!state.matches) {
          this.mobileExpandedIds = new Set();
        }
      });

    this.route.queryParamMap.pipe(takeUntil(this.destroy$)).subscribe((params) => {
      const companyId = Number(params.get('company_id') || 0);
      this.filterCompanyId = companyId > 0 ? companyId : null;
      this.filterCompanyLabel = (params.get('company_name') || '').trim();
      if (this.filterCompanyLabel && !this.companySearch) {
        this.companySearch = this.filterCompanyLabel;
      }
      this.page = 0;
      this.getOffers();
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  getOffers() {
    this.loading = true;
    this.offerService.getOffers(this.pageSize, this.page + 1, {
      quote: this.quoteSearch,
      company_name: this.filterCompanyId ? '' : this.companySearch,
      customer_company_id: this.filterCompanyId || undefined,
    }).subscribe({
      next: (res: any) => {
        this.offers = res.data || [];
        this.length = res.total || 0;
        this.pageSize = res.per_page || this.pageSize;
        if (this.filterCompanyId && !this.filterCompanyLabel) {
          const name = this.offers?.[0]?.customer_company?.name;
          if (name) {
            this.filterCompanyLabel = name;
            this.companySearch = name;
          }
        }
        this.loading = false;
      },
      error: () => {
        this.offers = [];
        this.length = 0;
        this.loading = false;
      },
    });
  }

  search() {
    this.page = 0;
    this.getOffers();
  }

  clearSearch() {
    this.quoteSearch = '';
    this.companySearch = '';
    this.page = 0;
    if (this.filterCompanyId) {
      this.filterCompanyId = null;
      this.filterCompanyLabel = '';
      this.router.navigate(['/dashboard/permissions/priceoffer'], { queryParams: {} });
      return;
    }
    this.getOffers();
  }

  clearCompanyFilter(): void {
    this.clearSearch();
  }

  createOfferForCompany(offerType: 'offer1' | 'offer2'): void {
    if (!this.filterCompanyId) {
      return;
    }
    const path = offerType === 'offer2'
      ? '/dashboard/permissions/priceoffer2'
      : '/dashboard/permissions/priceoffer1';
    this.router.navigate([path], { queryParams: { company_id: this.filterCompanyId } });
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.getOffers();
  }

  trackById(_index: number, item: any): number {
    return item?.id;
  }

  toggleMobileCard(id: number): void {
    const next = new Set(this.mobileExpandedIds);
    if (next.has(id)) {
      next.delete(id);
    } else {
      next.add(id);
    }
    this.mobileExpandedIds = next;
  }

  isMobileCardExpanded(id: number): boolean {
    return this.mobileExpandedIds.has(id);
  }

  offerLabel(elm: any): string {
    return elm?.offer === 'offer1' ? 'عرض 1' : 'عرض 2';
  }

  detailsLink(elm: any): any[] {
    return elm?.offer === 'offer1'
      ? ['/dashboard/permissions/offer1', elm?.id]
      : ['/dashboard/permissions/offer2', elm?.id];
  }

  editLink(elm: any): string {
    return elm?.offer === 'offer1'
      ? '/dashboard/permissions/priceoffer1'
      : '/dashboard/permissions/priceoffer2';
  }

  downloadOfferPdf(elm: any): void {
    const id = Number(elm?.id);
    if (!id || this.downloadingOfferId) {
      return;
    }
    this.downloadingOfferId = id;
    this.quotationExport.downloadByOfferId(id)
      .catch(() => Swal.fire('خطأ', 'تعذر تحميل عرض السعر PDF', 'error'))
      .finally(() => {
        this.downloadingOfferId = null;
      });
  }

  exportListPdf(): void {
    if (!this.offers?.length || this.exportingListPdf) {
      Swal.fire({ icon: 'info', text: 'لا توجد عروض في الصفحة الحالية للتصدير.' });
      return;
    }
    this.exportingListPdf = true;
    this.quotationExport
      .downloadOffersListPdf(this.offers, `price-offers-page-${this.page + 1}`)
      .catch(() => Swal.fire('خطأ', 'تعذر تصدير قائمة العروض', 'error'))
      .finally(() => {
        this.exportingListPdf = false;
      });
  }
}
