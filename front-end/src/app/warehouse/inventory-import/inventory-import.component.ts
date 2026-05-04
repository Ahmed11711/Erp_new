import { Component } from '@angular/core';
import { InventoryImportService } from '../services/inventory-import.service';

@Component({
  selector: 'app-inventory-import',
  templateUrl: './inventory-import.component.html',
  styleUrls: ['./inventory-import.component.css'],
})
export class InventoryImportComponent {
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

  constructor(private importApi: InventoryImportService) {}

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
    if (!this.itemsFile) {
      return;
    }
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
    if (!this.openingFile) {
      return;
    }
    this.loadingOpening = true;
    this.messageOpening = '';
    this.errorOpening = '';
    this.importApi.importOpeningBalances(this.openingFile, this.createMissingItems).subscribe({
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
    if (!this.adjustmentFile) {
      return;
    }
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
}
