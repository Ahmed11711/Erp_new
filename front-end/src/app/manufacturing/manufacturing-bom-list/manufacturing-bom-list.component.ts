import { Component, OnInit } from '@angular/core';
import { ManufacturingService } from '../services/manufacturing.service';

@Component({
  selector: 'app-manufacturing-bom-list',
  templateUrl: './manufacturing-bom-list.component.html',
  styleUrls: ['./manufacturing-bom-list.component.css'],
})
export class ManufacturingBomListComponent implements OnInit {
  products: any[] = [];
  catword = 'category_name';
  tableData: any[] = [];
  /** بحث نصي في صفوف الجدول */
  bomSearchQuery = '';

  constructor(private manufacturingService: ManufacturingService) {}

  ngOnInit(): void {
    this.loadBomData();
  }

  get filteredBomRows(): any[] {
    const q = this.bomSearchQuery.trim().toLowerCase();
    if (!q) {
      return this.tableData;
    }
    return this.tableData.filter((elm) => {
      const p = elm.product || {};
      const hay = [
        p.category_name,
        p.item_code,
        p.color,
        p.warehouse,
        p.initial_balance != null ? String(p.initial_balance) : '',
        p.minimum_quantity != null ? String(p.minimum_quantity) : '',
        elm.total != null ? String(elm.total) : '',
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
      return hay.includes(q);
    });
  }

  loadBomData(): void {
    this.manufacturingService.getAllRecipes().subscribe({
      next: (result: any) => {
        this.tableData = result;
        this.getProducts();
      },
    });
  }

  getProducts(): void {
    this.products = [];
    const seen = new Set<number>();
    this.tableData.forEach((elm) => {
      const p = elm.product;
      if (p?.id != null && !seen.has(p.id)) {
        seen.add(p.id);
        this.products.push(p);
      }
    });
  }

  productType(e: any): void {
    this.products = [];
    this.manufacturingService.getAllRecipes().subscribe((result: any) => {
      this.tableData = result.filter(
        (elm: any) => elm.product.warehouse === e.target.value
      );
      this.getProducts();
    });
  }

  productChange(event: { id?: number }): void {
    const productId = event?.id;
    if (productId == null) {
      return;
    }
    this.manufacturingService.getAllRecipes().subscribe((result) => {
      this.tableData = result.filter(
        (elm) => Number(elm.product_id) === Number(productId)
      );
    });
  }

  resetProductFilter(): void {
    this.loadBomData();
  }
}
