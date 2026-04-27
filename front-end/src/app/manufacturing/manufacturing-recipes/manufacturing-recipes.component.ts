import { Component, OnInit } from '@angular/core';
import {
  ManufacturingService,
  RecipeExtraCost,
  CostBreakdown,
} from '../services/manufacturing.service';

@Component({
  selector: 'app-manufacturing-recipes',
  templateUrl: './manufacturing-recipes.component.html',
  styleUrls: ['./manufacturing-recipes.component.css'],
})
export class ManufacturingRecipesComponent implements OnInit {
  products: any[] = [];
  catword = 'category_name';
  tableData: any[] = [];

  /** Recipe BOM list from /manufacture */
  allBomData: any[] = [];

  /** Recipe models from /recipes API with ingredients, extra costs, breakdown */
  recipes: any[] = [];
  expandedRecipeId: number | null = null;
  expandedDetail: any = null;
  expandedBreakdown: CostBreakdown | null = null;
  loadingDetail = false;

  /** Bulk selection */
  selectedIds = new Set<number>();
  bulkDeleting = false;

  /** Delete confirmation */
  confirmDeleteId: number | null = null;

  constructor(private manufacturingService: ManufacturingService) {}

  ngOnInit(): void {
    this.loadRecipes();
    this.loadBomData();
  }

  loadRecipes(): void {
    this.manufacturingService.listRecipes().subscribe({
      next: (data) => {
        this.recipes = data;
      },
    });
  }

  loadBomData(): void {
    this.manufacturingService.getAllRecipes().subscribe({
      next: (result: any) => {
        this.allBomData = result;
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

  // ──────────────────────────────────────────────────────────
  // Detail / expand
  // ──────────────────────────────────────────────────────────

  toggleDetail(recipeId: number): void {
    if (this.expandedRecipeId === recipeId) {
      this.expandedRecipeId = null;
      this.expandedDetail = null;
      this.expandedBreakdown = null;
      return;
    }

    this.expandedRecipeId = recipeId;
    this.loadingDetail = true;
    this.expandedDetail = null;
    this.expandedBreakdown = null;

    this.manufacturingService.getRecipeDetail(recipeId).subscribe({
      next: (res) => {
        this.expandedDetail = res.recipe;
        this.expandedBreakdown = res.breakdown;
        this.loadingDetail = false;
      },
      error: () => {
        this.loadingDetail = false;
      },
    });
  }

  // ──────────────────────────────────────────────────────────
  // Delete
  // ──────────────────────────────────────────────────────────

  askDelete(recipeId: number): void {
    this.confirmDeleteId = recipeId;
  }

  cancelDelete(): void {
    this.confirmDeleteId = null;
  }

  confirmDelete(): void {
    if (this.confirmDeleteId == null) return;
    const id = this.confirmDeleteId;
    this.confirmDeleteId = null;

    this.manufacturingService.deleteRecipe(id).subscribe({
      next: () => {
        this.recipes = this.recipes.filter((r) => r.id !== id);
        if (this.expandedRecipeId === id) {
          this.expandedRecipeId = null;
          this.expandedDetail = null;
          this.expandedBreakdown = null;
        }
        this.selectedIds.delete(id);
        this.loadBomData();
      },
    });
  }

  // ──────────────────────────────────────────────────────────
  // Bulk delete
  // ──────────────────────────────────────────────────────────

  toggleSelect(id: number): void {
    if (this.selectedIds.has(id)) {
      this.selectedIds.delete(id);
    } else {
      this.selectedIds.add(id);
    }
  }

  toggleSelectAll(): void {
    if (this.selectedIds.size === this.recipes.length) {
      this.selectedIds.clear();
    } else {
      this.recipes.forEach((r) => this.selectedIds.add(r.id));
    }
  }

  bulkDelete(): void {
    if (this.selectedIds.size === 0) return;
    this.bulkDeleting = true;

    this.manufacturingService
      .bulkDeleteRecipes(Array.from(this.selectedIds))
      .subscribe({
        next: () => {
          this.bulkDeleting = false;
          this.selectedIds.clear();
          this.expandedRecipeId = null;
          this.loadRecipes();
          this.loadBomData();
        },
        error: () => {
          this.bulkDeleting = false;
        },
      });
  }
}
