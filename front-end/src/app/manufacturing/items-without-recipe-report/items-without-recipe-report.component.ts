import { Component, OnInit } from '@angular/core';
import { ManufacturingService, ItemWithoutRecipeRow } from '../services/manufacturing.service';

@Component({
  selector: 'app-items-without-recipe-report',
  templateUrl: './items-without-recipe-report.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './items-without-recipe-report.component.css'],
})
export class ItemsWithoutRecipeReportComponent implements OnInit {
  loading = false;
  loadError = false;
  showFilters = true;

  warehouse = '';
  productType = '';
  searchTerm = '';

  rows: ItemWithoutRecipeRow[] = [];
  totals = { items_count: 0 };

  readonly warehouseOptions = [
    { value: '', label: 'كل المخازن' },
    { value: 'مخزن منتج تام', label: 'مخزن منتج تام' },
    { value: 'مخزن منتج تحت التشغيل', label: 'مخزن منتج تحت التشغيل' },
  ];

  readonly productTypeOptions = [
    { value: '', label: 'كل الأنواع' },
    { value: 'finished', label: 'منتج تام' },
    { value: 'semi_finished', label: 'تحت التشغيل' },
  ];

  constructor(private manufacturingService: ManufacturingService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.loadError = false;

    this.manufacturingService
      .getItemsWithoutRecipes({
        warehouse: this.warehouse || undefined,
        product_type: this.productType || undefined,
        search: this.searchTerm.trim() || undefined,
      })
      .subscribe({
        next: (res) => {
          this.rows = res?.data || [];
          this.totals = res?.totals || { items_count: this.rows.length };
          this.loading = false;
        },
        error: () => {
          this.rows = [];
          this.totals = { items_count: 0 };
          this.loadError = true;
          this.loading = false;
        },
      });
  }

  toggleFilters(): void {
    this.showFilters = !this.showFilters;
  }

  onFilterChange(): void {
    this.load();
  }

  trackById(_index: number, row: ItemWithoutRecipeRow): number {
    return row.id;
  }
}
