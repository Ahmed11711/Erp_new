import { Component, OnInit } from '@angular/core';
import { ManufacturingService } from '../services/manufacturing.service';

@Component({
  selector: 'app-manufacturing-additions',
  templateUrl: './manufacturing-additions.component.html',
  styleUrls: ['./manufacturing-additions.component.css']
})
export class ManufacturingAdditionsComponent implements OnInit {
  rows: Array<{ id: number; name: string; cost: number; unit?: string | null }> = [];
  loading = false;
  saving = false;
  error: string | null = null;

  showForm = false;
  editingId: number | null = null;
  name = '';
  cost: number | null = null;
  unit = '';

  constructor(private manufacturingService: ManufacturingService) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading = true;
    this.error = null;
    this.manufacturingService.listAdditions().subscribe({
      next: (rows) => {
        this.rows = Array.isArray(rows) ? rows : [];
        this.loading = false;
      },
      error: (err) => {
        this.rows = [];
        this.loading = false;
        this.error = err?.error?.message ?? 'تعذر تحميل الإضافات';
      },
    });
  }

  openCreate(): void {
    this.editingId = null;
    this.name = '';
    this.cost = null;
    this.unit = '';
    this.showForm = true;
  }

  startEdit(row: { id: number; name: string; cost: number; unit?: string | null }): void {
    this.editingId = row.id;
    this.name = row.name;
    this.cost = Number(row.cost);
    this.unit = row.unit ?? '';
    this.showForm = true;
  }

  cancelForm(): void {
    this.showForm = false;
    this.editingId = null;
  }

  save(): void {
    const name = this.name.trim();
    const cost = Number(this.cost);
    if (!name || !Number.isFinite(cost) || cost < 0) {
      this.error = 'أدخل اسم الإضافة والتكلفة';
      return;
    }
    this.saving = true;
    this.error = null;
    const payload = { name, cost, unit: this.unit.trim() || null };
    const req = this.editingId
      ? this.manufacturingService.updateAddition(this.editingId, payload)
      : this.manufacturingService.createAddition(payload);
    req.subscribe({
      next: () => {
        this.saving = false;
        this.showForm = false;
        this.load();
      },
      error: (err) => {
        this.saving = false;
        this.error = err?.error?.message ?? 'تعذر حفظ الإضافة';
      },
    });
  }

  remove(row: { id: number; name: string }): void {
    if (!confirm(`حذف الإضافة «${row.name}»؟`)) {
      return;
    }
    this.manufacturingService.deleteAddition(row.id).subscribe({
      next: () => this.load(),
      error: (err) => {
        this.error = err?.error?.message ?? 'تعذر الحذف';
      },
    });
  }
}
