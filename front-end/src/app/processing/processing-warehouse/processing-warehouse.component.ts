import { Component, OnInit } from '@angular/core';
import * as XLSX from 'xlsx';
import { ProcessingService } from '../services/processing.service';

interface WarehouseFilters {
  supplier_id: string;
  category_id: string;
  processing_order_id: string;
  date_from: string;
  date_to: string;
  q: string;
}

@Component({
  selector: 'app-processing-warehouse',
  templateUrl: './processing-warehouse.component.html',
  styleUrls: ['./processing-warehouse.component.css'],
})
export class ProcessingWarehouseComponent implements OnInit {
  warehouse: any = {};
  kpis: any = {};
  aging: any[] = [];
  vendors: any[] = [];
  items: any[] = [];
  rows: any[] = [];
  movements: any[] = [];

  options: { vendors: any[]; items: any[]; orders: any[] } = { vendors: [], items: [], orders: [] };
  filters: WarehouseFilters = {
    supplier_id: '',
    category_id: '',
    processing_order_id: '',
    date_from: '',
    date_to: '',
    q: '',
  };

  view: 'vendors' | 'items' | 'movements' = 'vendors';
  expandedVendors: Record<number, boolean> = {};
  loading = true;
  loadingMovements = false;

  readonly agingLabels: Record<string, string> = {
    '0-30': 'أقل من شهر',
    '31-60': 'من 31 إلى 60 يوم',
    '61-90': 'من 61 إلى 90 يوم',
    '90+': 'أكثر من 90 يوم',
  };

  constructor(private api: ProcessingService) {}

  ngOnInit(): void {
    this.api.warehouseFilters().subscribe({
      next: (o) => (this.options = o || { vendors: [], items: [], orders: [] }),
    });
    this.load();
  }

  load(): void {
    this.loading = true;
    this.api.warehouseOverview(this.activeFilters()).subscribe({
      next: (res) => {
        this.warehouse = res?.warehouse || {};
        this.kpis = res?.kpis || {};
        this.aging = res?.aging || [];
        this.vendors = res?.vendors || [];
        this.items = res?.items || [];
        this.rows = res?.rows || [];
        this.loading = false;
      },
      error: () => (this.loading = false),
    });

    if (this.view === 'movements') {
      this.loadMovements();
    }
  }

  loadMovements(): void {
    this.loadingMovements = true;
    this.api.warehouseMovements(this.activeFilters()).subscribe({
      next: (rows) => {
        this.movements = rows || [];
        this.loadingMovements = false;
      },
      error: () => (this.loadingMovements = false),
    });
  }

  switchView(view: 'vendors' | 'items' | 'movements'): void {
    this.view = view;
    if (view === 'movements' && !this.movements.length) {
      this.loadMovements();
    }
  }

  resetFilters(): void {
    this.filters = {
      supplier_id: '',
      category_id: '',
      processing_order_id: '',
      date_from: '',
      date_to: '',
      q: '',
    };
    this.movements = [];
    this.load();
  }

  toggleVendor(supplierId: number): void {
    this.expandedVendors[supplierId] = !this.expandedVendors[supplierId];
  }

  agingLabel(bucket: string): string {
    return this.agingLabels[bucket] || bucket;
  }

  exportExcel(): void {
    const book = XLSX.utils.book_new();

    XLSX.utils.book_append_sheet(
      book,
      XLSX.utils.json_to_sheet(
        this.rows.map((r) => ({
          'المعالج': r.supplier_name,
          'أمر التشغيل': r.order_number,
          'كود الصنف': r.item_code || '',
          'الصنف': r.category_name,
          'الوحدة': r.unit || '',
          'المخزن المصدر': r.source_stock_name || '',
          'مصروف': r.dispatched_qty,
          'مستلم': r.received_good_qty,
          'هالك': r.received_damaged_qty,
          'مرفوض': r.received_rejected_qty,
          'الرصيد لدى المعالج': r.qty_at_vendor,
          'تكلفة الوحدة': r.unit_cost,
          'القيمة': r.value_at_vendor,
          'تاريخ أول صرف': r.first_dispatch_date || '',
          'العمر (يوم)': r.age_days,
          'الشريحة': this.agingLabel(r.aging_bucket),
        })),
      ),
      'الأرصدة',
    );

    XLSX.utils.book_append_sheet(
      book,
      XLSX.utils.json_to_sheet(
        this.vendors.map((v) => ({
          'المعالج': v.supplier_name,
          'عدد الأصناف': v.items_count,
          'عدد الأوامر': v.orders_count,
          'إجمالي الكمية': v.quantity,
          'إجمالي القيمة': v.value,
          'أقدم رصيد (يوم)': v.oldest_age_days,
          'سطور متأخرة': v.overdue_lines,
        })),
      ),
      'حسب المعالج',
    );

    if (this.movements.length) {
      XLSX.utils.book_append_sheet(
        book,
        XLSX.utils.json_to_sheet(
          this.movements.map((m) => ({
            'التاريخ': m.movement_date || '',
            'الحركة': m.direction === 'in' ? 'دخول (صرف للمعالج)' : 'خروج (استلام)',
            'المستند': m.document_number,
            'أمر التشغيل': m.order_number,
            'المعالج': m.supplier_name,
            'الصنف': m.category_name,
            'الكمية': m.quantity,
            'تكلفة الوحدة': m.unit_cost,
            'الإجمالي': m.total_cost,
          })),
        ),
        'حركة المخزن',
      );
    }

    XLSX.writeFile(book, `مخزن-التجهيز-${new Date().toISOString().slice(0, 10)}.xlsx`);
  }

  print(): void {
    window.print();
  }

  private activeFilters(): Record<string, string> {
    const params: Record<string, string> = {};
    Object.entries(this.filters).forEach(([key, value]) => {
      if (value !== null && value !== undefined && `${value}`.trim() !== '') {
        params[key] = `${value}`;
      }
    });

    return params;
  }
}
