import { Component, OnInit } from '@angular/core';
import {
  InventoryImportService,
  StockCountPreviewResponse,
  StockCountPreviewRow,
} from '../services/inventory-import.service';

type ImportStep = 'upload' | 'preview' | 'confirmed';
type TabType = 'stock-count' | 'items' | 'opening' | 'adjustment';

@Component({
  selector: 'app-inventory-import',
  templateUrl: './inventory-import.component.html',
  styleUrls: ['./inventory-import.component.css'],
})
export class InventoryImportComponent implements OnInit {
  activeTab: TabType = 'stock-count';

  // Stock count workflow
  step: ImportStep = 'upload';
  stockCountFile: File | null = null;
  loadingPreview = false;
  loadingConfirm = false;
  previewData: StockCountPreviewResponse | null = null;
  importToken = '';
  confirmResult: any = null;
  stockCountError = '';

  // Preview filters
  filterType: 'all' | 'matched' | 'new' | 'adjusted' | 'warnings' = 'all';
  searchTerm = '';

  // Legacy sections
  itemsFile: File | null = null;
  openingFile: File | null = null;
  adjustmentFile: File | null = null;
  createMissingItems = false;

  loadingItems = false;
  loadingOpening = false;
  loadingAdjustment = false;

  messageItems = '';
  messageOpening = '';
  messageAdjustment = '';
  errorItems = '';
  errorOpening = '';
  errorAdjustment = '';

  // History
  historyList: any[] = [];
  loadingHistory = false;

  constructor(private importApi: InventoryImportService) {}

  ngOnInit(): void {}

  setTab(tab: TabType): void {
    this.activeTab = tab;
  }

  // ─── Stock Count Workflow ─────────────────────────────────

  onStockCountFile(ev: Event): void {
    const input = ev.target as HTMLInputElement;
    this.stockCountFile = input.files?.[0] ?? null;
    this.stockCountError = '';
  }

  uploadStockCount(): void {
    if (!this.stockCountFile) return;
    this.loadingPreview = true;
    this.stockCountError = '';
    this.previewData = null;

    this.importApi.stockCountPreview(this.stockCountFile).subscribe({
      next: (res) => {
        this.loadingPreview = false;
        this.previewData = res;
        this.importToken = res.import_token;
        this.step = 'preview';
      },
      error: (err) => {
        this.loadingPreview = false;
        this.stockCountError = err?.error?.message || 'فشل تحليل الملف';
      },
    });
  }

  confirmImport(): void {
    if (!this.importToken) return;
    this.loadingConfirm = true;
    this.stockCountError = '';

    this.importApi.stockCountConfirm(this.importToken).subscribe({
      next: (res) => {
        this.loadingConfirm = false;
        this.confirmResult = res;
        this.step = 'confirmed';
      },
      error: (err) => {
        this.loadingConfirm = false;
        this.stockCountError = err?.error?.message || 'فشل تطبيق الاستيراد';
      },
    });
  }

  cancelImport(): void {
    if (!this.importToken) return;
    this.importApi.stockCountCancel(this.importToken).subscribe({
      next: () => {
        this.resetStockCount();
      },
      error: () => {
        this.resetStockCount();
      },
    });
  }

  resetStockCount(): void {
    this.step = 'upload';
    this.stockCountFile = null;
    this.previewData = null;
    this.importToken = '';
    this.confirmResult = null;
    this.stockCountError = '';
    this.filterType = 'all';
    this.searchTerm = '';
  }

  get filteredRows(): StockCountPreviewRow[] {
    if (!this.previewData?.rows) return [];
    let rows = this.previewData.rows;

    if (this.filterType === 'matched') {
      rows = rows.filter((r) => !r.is_new_product);
    } else if (this.filterType === 'new') {
      rows = rows.filter((r) => r.is_new_product);
    } else if (this.filterType === 'adjusted') {
      rows = rows.filter(
        (r) => r.quantity_difference !== null && Math.abs(r.quantity_difference) > 0.001
      );
    } else if (this.filterType === 'warnings') {
      rows = rows.filter((r) => r.warnings && r.warnings.length > 0);
    }

    if (this.searchTerm.trim()) {
      const term = this.searchTerm.trim().toLowerCase();
      rows = rows.filter(
        (r) =>
          (r.product_name || '').toLowerCase().includes(term) ||
          (r.matched_name || '').toLowerCase().includes(term)
      );
    }

    return rows;
  }

  getMatchBadgeClass(type: string): string {
    switch (type) {
      case 'exact_sku':
      case 'exact_name':
        return 'badge-success';
      case 'fuzzy':
        return 'badge-warning';
      case 'new':
        return 'badge-danger';
      default:
        return 'badge-secondary';
    }
  }

  getMatchLabel(type: string): string {
    switch (type) {
      case 'exact_sku':
        return 'باركود';
      case 'exact_name':
        return 'اسم مطابق';
      case 'fuzzy':
        return 'ضبابي';
      case 'new':
        return 'جديد';
      default:
        return type;
    }
  }

  getDirectionLabel(dir: string | null): string {
    if (dir === 'in') return 'زيادة';
    if (dir === 'out') return 'عجز';
    return 'متطابق';
  }

  getDirectionClass(dir: string | null): string {
    if (dir === 'in') return 'text-success';
    if (dir === 'out') return 'text-danger';
    return 'text-muted';
  }

  trackByRowId(index: number, row: StockCountPreviewRow): number {
    return row.id;
  }

  // ─── Legacy Functions ─────────────────────────────────────

  onFile(which: 'items' | 'opening' | 'adjustment', ev: Event): void {
    const input = ev.target as HTMLInputElement;
    const f = input.files?.[0] ?? null;
    if (which === 'items') {
      this.itemsFile = f;
    } else if (which === 'opening') {
      this.openingFile = f;
    } else {
      this.adjustmentFile = f;
    }
  }

  uploadItems(): void {
    if (!this.itemsFile) return;
    this.loadingItems = true;
    this.messageItems = '';
    this.errorItems = '';
    this.importApi.importItems(this.itemsFile).subscribe({
      next: (res) => {
        this.loadingItems = false;
        this.messageItems = `تم: إنشاء ${res.created}، تحديث ${res.updated}`;
      },
      error: (err) => {
        this.loadingItems = false;
        this.errorItems = err?.error?.message || 'فشل الاستيراد';
      },
    });
  }

  uploadOpening(): void {
    if (!this.openingFile) return;
    this.loadingOpening = true;
    this.messageOpening = '';
    this.errorOpening = '';
    this.importApi
      .importOpeningBalances(this.openingFile, this.createMissingItems)
      .subscribe({
        next: (res) => {
          this.loadingOpening = false;
          this.messageOpening = `تمت معالجة ${res.processed_lines} سطراً`;
        },
        error: (err) => {
          this.loadingOpening = false;
          this.errorOpening = err?.error?.message || 'فشل الاستيراد';
        },
      });
  }

  uploadAdjustment(): void {
    if (!this.adjustmentFile) return;
    this.loadingAdjustment = true;
    this.messageAdjustment = '';
    this.errorAdjustment = '';
    this.importApi.importAdjustments(this.adjustmentFile).subscribe({
      next: (res) => {
        this.loadingAdjustment = false;
        this.messageAdjustment = `تمت معالجة ${res.processed_lines} سطراً (تسوية جرد)`;
      },
      error: (err) => {
        this.loadingAdjustment = false;
        this.errorAdjustment = err?.error?.message || 'فشل الاستيراد';
      },
    });
  }

  // ─── History ──────────────────────────────────────────────

  loadHistory(): void {
    this.loadingHistory = true;
    this.importApi.stockCountHistory().subscribe({
      next: (res) => {
        this.loadingHistory = false;
        this.historyList = res.imports || [];
      },
      error: () => {
        this.loadingHistory = false;
      },
    });
  }
}
