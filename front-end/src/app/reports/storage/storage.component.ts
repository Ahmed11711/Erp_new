import { Component, OnInit } from '@angular/core';
import { CategoryService } from 'src/app/categories/services/category.service';

@Component({
  selector: 'app-storage',
  templateUrl: './storage.component.html',
  styleUrls: ['./storage.component.css']
})
export class StorageComponent implements OnInit {
  data: any[] = [];
  filteredData: any[] = [];
  dateFrom: string | null = null;
  dateTo: string | null = null;
  warehouse = 'مخزن منتج تام';
  searchTerm = '';
  loading = false;
  loadError = false;
  length = 0;
  page = 0;
  pageSize = 15;
  pageSizeOptions = [15, 50, 100];

  warehouses = [
    { value: 'مخزن مواد خام', label: 'مخزن المواد الخام' },
    { value: 'مخزن منتج تحت التشغيل', label: 'مخزن منتج التشغيل' },
    { value: 'مخزن منتج تام', label: 'مخزن المنتج التام' },
    { value: 'مخزن صيانة', label: 'مخزن الصيانة' },
    { value: 'مخزن تالف', label: 'مخزن التالف' }
  ];

  constructor(private categoryService: CategoryService) {}

  ngOnInit(): void {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    this.dateFrom = `${y}-${m}-01`;
    this.dateTo = `${y}-${m}-${d}`;
    this.load();
  }

  load(): void {
    this.loading = true;
    this.loadError = false;
    const params: any = {
      warehouse: this.warehouse,
      date_from: this.dateFrom,
      date_to: this.dateTo
    };
    this.categoryService.warehouseDetails(this.pageSize, this.page + 1, params).subscribe({
      next: (res: any) => {
        this.data = res?.data || [];
        this.length = res?.total || 0;
        this.applyFilter();
        this.loading = false;
      },
      error: () => {
        this.data = [];
        this.filteredData = [];
        this.loadError = true;
        this.loading = false;
      }
    });
  }

  onPageChange(event: any): void {
    this.page = event.pageIndex;
    this.pageSize = event.pageSize;
    this.load();
  }

  onSearchChange(): void {
    this.applyFilter();
  }

  applyFilter(): void {
    const term = (this.searchTerm || '').trim().toLowerCase();
    if (!term) {
      this.filteredData = [...this.data];
    } else {
      this.filteredData = this.data.filter((r: any) =>
        (r.category_name || '').toLowerCase().includes(term) ||
        (r.type || '').toLowerCase().includes(term)
      );
    }
  }

  formatDate(d: string): string {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString('ar-EG');
  }
}
