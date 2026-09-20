import { Component, HostListener, OnDestroy, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from 'src/app/auth/auth.service';
import { CategoryService } from 'src/app/categories/services/category.service';
import { ProductionService } from 'src/app/categories/services/production.service';
import { ExcelService } from 'src/app/excel.service';
import { PdfService } from 'src/app/pdf.service';
import { environment } from 'src/env/env';

type SortField =
  | 'total_quantity_new'
  | 'total_orders'
  | 'total_new'
  | 'total_quantity_return'
  | 'total_postpone'
  | 'category_name'
  | 'classification_name'
  | 'item_code';

@Component({
  selector: 'app-categories-status-report',
  templateUrl: './categories-status-report.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './categories-status-report.component.css']
})
export class CategoriesStatusReportComponent implements OnInit, OnDestroy {
  user!: string;
  productionData: any[] = [];
  url!: string;

  totalOrders = 0;
  totalNewOrders = 0;
  totalReturnedOrders = 0;
  totalPriceNewOrders = 0;
  totalPriceReturnedOrders = 0;
  profitabilityTotals: any = null;

  imgUrl = environment.imgUrl;
  showFilters = true;
  showStatusMenu = false;
  expanded = new Set<number>();
  imageFailed = new Set<number>();
  sortField: SortField = 'total_quantity_new';

  soldCategories: any[] = [];
  length = 0;
  pageSize = 50;
  page = 0;
  pageSizeOptions = [50, 100, 1000];
  param: any = {};
  dateFrom = '';
  dateTo = '';
  loading = false;
  searchText = '';
  private searchDebounce?: ReturnType<typeof setTimeout>;

  /** حالات الطلب المتاحة للفلتر (متعدد) */
  readonly statusOptions: string[] = [
    'طلب جديد',
    'طلب مؤكد',
    'شحن جزئي',
    'تسليم جزئي',
    'تم شحن',
    'تم التسليم',
    'تم الاستلام',
    'تم التحصيل',
    'مؤجل',
    'أرشيف',
    'تم الصيانة',
    'رفض استلام',
    'ملغي',
  ];

  /** افتراضي: الكل (بدون فلتر حالة) */
  selectedStatuses = new Set<string>();

  constructor(
    private categoryService: CategoryService,
    private production: ProductionService,
    private route: Router,
    private authService: AuthService,
    private pdfService: PdfService,
    private excelService: ExcelService
  ) {
    this.url = this.route.url;
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    this.dateFrom = this.formatDate(yesterday);
    this.dateTo = this.formatDate(yesterday);
  }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    this.getProductionLine();
    this.param['sort'] = this.sortField;
    this.loadReport();
  }

  @HostListener('document:click')
  onDocumentClick(): void {
    this.showStatusMenu = false;
  }

  get statusSummary(): string {
    if (this.selectedStatuses.size === 0) {
      return 'الكل';
    }
    if (this.selectedStatuses.size === 1) {
      return [...this.selectedStatuses][0];
    }
    return `${this.selectedStatuses.size} حالات`;
  }

  isStatusSelected(status: string): boolean {
    return this.selectedStatuses.has(status);
  }

  toggleStatusMenu(event: Event): void {
    event.stopPropagation();
    this.showStatusMenu = !this.showStatusMenu;
  }

  toggleStatus(status: string, event: Event): void {
    event.stopPropagation();
    if (this.selectedStatuses.has(status)) {
      this.selectedStatuses.delete(status);
    } else {
      this.selectedStatuses.add(status);
    }
    this.page = 0;
    this.loadReport();
  }

  clearStatuses(event: Event): void {
    event.stopPropagation();
    this.selectedStatuses.clear();
    this.page = 0;
    this.loadReport();
  }

  selectPrepStatuses(event: Event): void {
    event.stopPropagation();
    this.selectedStatuses = new Set(['طلب مؤكد', 'شحن جزئي', 'تم شحن']);
    this.page = 0;
    this.loadReport();
  }

  getProductionLine() {
    this.production.getProductions().subscribe((data: any) => {
      this.productionData = data.filter((item: any) => item.warehouse == 'مخزن منتج تام');
    });
  }

  selectProductionLine(e: Event) {
    const target = e.target as HTMLSelectElement;
    const value = target.value;
    if (value) {
      this.param['production_id'] = value;
    } else {
      delete this.param['production_id'];
    }
    this.page = 0;
    this.loadReport();
  }

  export(status: string) {
    const fileName = this.dateFrom == this.dateTo
      ? `ItemsStatus_${this.dateFrom}`
      : `ItemsStatus_${this.dateFrom}_to_${this.dateTo}`;
    const element = document.getElementById('capture-status-report');
    this.pdfService.generatePdf(element, status, fileName);
  }

  exportTableToExcel() {
    const fileName = this.dateFrom == this.dateTo
      ? `ItemsStatus_${this.dateFrom}`
      : `ItemsStatus_${this.dateFrom}_to_${this.dateTo}`;
    const tableElement: any = document.getElementById('capture-status-report');
    this.excelService.generateExcel(fileName, tableElement, 1);
  }

  formatDate(date: Date): string {
    const y = date.getFullYear();
    const m = ('0' + (date.getMonth() + 1)).slice(-2);
    const d = ('0' + date.getDate()).slice(-2);
    return `${y}-${m}-${d}`;
  }

  loadReport() {
    this.loading = true;
    this.param['date_from'] = this.dateFrom;
    this.param['date_to'] = this.dateTo;
    this.param['sort'] = this.sortField;

    if (this.selectedStatuses.size > 0) {
      this.param['order_statuses'] = [...this.selectedStatuses].join(',');
    } else {
      delete this.param['order_statuses'];
    }

    this.categoryService.categoriesStatusReports(this.pageSize, this.page + 1, this.param).subscribe({
      next: (res: any) => {
        this.soldCategories = res.data || [];
        this.profitabilityTotals = res.profitability_totals ?? null;
        this.totalOrders = this.soldCategories.reduce((sum, elm) => sum + Number(elm.total_orders || 0), 0);
        this.totalNewOrders = this.soldCategories.reduce((sum, elm) => sum + Number(elm.total_quantity_new || 0), 0);
        this.totalReturnedOrders = this.soldCategories.reduce((sum, elm) => sum + Number(elm.total_quantity_return || 0), 0);
        this.totalPriceNewOrders = this.soldCategories.reduce((sum, elm) => sum + Number(elm.total_new || 0), 0);
        this.totalPriceReturnedOrders = this.soldCategories.reduce((sum, elm) => sum + Number(elm.total_postpone || 0), 0);
        this.length = res.total;
        this.pageSize = res.per_page;
        this.expanded.clear();
        this.imageFailed.clear();
        this.loading = false;
      },
      error: () => {
        this.loading = false;
      }
    });
  }

  onSortChange() {
    this.param['sort'] = this.sortField;
    this.page = 0;
    this.loadReport();
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.loadReport();
  }

  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateFrom = target.value;
    this.page = 0;
    this.loadReport();
  }

  onDateToChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateTo = target.value;
    this.page = 0;
    this.loadReport();
  }

  toggleFilters() {
    this.showFilters = !this.showFilters;
  }

  toggleExpand(index: number) {
    if (this.expanded.has(index)) {
      this.expanded.delete(index);
    } else {
      this.expanded.add(index);
    }
  }

  onImgError(index: number) {
    this.imageFailed.add(index);
  }

  trackByIndex(index: number): number {
    return index;
  }

  onSearchChange(value: string): void {
    clearTimeout(this.searchDebounce);
    this.searchDebounce = setTimeout(() => {
      const q = (value ?? '').trim();
      if (q) {
        this.param['search'] = q;
      } else {
        delete this.param['search'];
      }
      this.page = 0;
      this.loadReport();
    }, 400);
  }

  ngOnDestroy(): void {
    clearTimeout(this.searchDebounce);
  }
}
