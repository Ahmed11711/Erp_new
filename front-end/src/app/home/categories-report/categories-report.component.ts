import { Component, OnDestroy, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from 'src/app/auth/auth.service';
import { CategoryService } from 'src/app/categories/services/category.service';
import { ProductionService } from 'src/app/categories/services/production.service';
import { ExcelService } from 'src/app/excel.service';
import { PdfService } from 'src/app/pdf.service';
import { environment } from 'src/env/env';

type SortField =
  | 'total_new'
  | 'total_quantity_new'
  | 'total_orders'
  | 'total_postpone'
  | 'total_quantity_return'
  | 'category_name';

@Component({
  selector: 'app-categories-report',
  templateUrl: './categories-report.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './categories-report.component.css']
})
export class CategoriesReportComponent implements OnInit, OnDestroy {
  user!: string;
  productionData: any[] = [];
  url!: string;

  // summary totals
  totalOrders = 0;
  totalNewOrders = 0;
  totalReturnedOrders = 0;
  totalPriceNewOrders = 0;
  totalPriceReturnedOrders = 0;
  profitabilityTotals: any = null;

  // ui state
  imgUrl = environment.imgUrl;
  showFilters = true;
  expanded = new Set<number>();
  imageFailed = new Set<number>();
  sortField: SortField = 'total_new';

  soldCategories: any[] = [];
  length = 50;
  pageSize = 50;
  page = 0;
  pageSizeOptions = [50, 100, 1000];
  param: any = {};
  dateFrom = '';
  dateTo = '';
  loading = false;
  searchText = '';
  private searchDebounce?: ReturnType<typeof setTimeout>;

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
    this.categoriesSellReports();
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
    this.categoriesSellReports();
  }

  export(status: string) {
    const fileName = this.dateFrom == this.dateTo
      ? `Report_${this.dateFrom}`
      : `Report_${this.dateFrom}_to_${this.dateTo}`;
    const element = document.getElementById('capture');
    this.pdfService.generatePdf(element, status, fileName);
  }

  exportTableToExcel() {
    const fileName = this.dateFrom == this.dateTo
      ? `Report_${this.dateFrom}`
      : `Report_${this.dateFrom}_to_${this.dateTo}`;
    const tableElement: any = document.getElementById('capture');
    this.excelService.generateExcel(fileName, tableElement, 1);
  }

  formatDate(date: Date): string {
    const y = date.getFullYear();
    const m = ('0' + (date.getMonth() + 1)).slice(-2);
    const d = ('0' + date.getDate()).slice(-2);
    return `${y}-${m}-${d}`;
  }

  categoriesSellReports() {
    this.loading = true;
    this.param['date_from'] = this.dateFrom;
    this.param['date_to'] = this.dateTo;
    this.param['sort'] = this.sortField;
    this.categoryService.categoriesSellReports(this.pageSize, this.page + 1, this.param).subscribe({
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

  sort(e: SortField) {
    this.sortField = e;
    this.param['sort'] = e;
    this.categoriesSellReports();
  }

  onSortChange() {
    this.param['sort'] = this.sortField;
    this.page = 0;
    this.categoriesSellReports();
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.categoriesSellReports();
  }

  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateFrom = target.value;
    this.page = 0;
    this.categoriesSellReports();
  }

  onDateToChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateTo = target.value;
    this.page = 0;
    this.categoriesSellReports();
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
      this.categoriesSellReports();
    }, 400);
  }

  ngOnDestroy(): void {
    clearTimeout(this.searchDebounce);
  }
}
