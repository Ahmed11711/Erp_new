import { Component, OnInit } from '@angular/core';
import { ManufacturingService, CostBreakdown } from '../services/manufacturing.service';

@Component({
  selector: 'app-manufacturing-recipes',
  templateUrl: './manufacturing-recipes.component.html',
  styleUrls: ['./manufacturing-recipes.component.css'],
})
export class ManufacturingRecipesComponent implements OnInit {
  /** Recipe models from /recipes API with ingredients, extra costs, breakdown */
  recipes: any[] = [];
  /** بحث في اسم الوصفة والوصف */
  recipeSearchQuery = '';
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
  }

  get filteredRecipes(): any[] {
    const q = this.recipeSearchQuery.trim().toLowerCase();
    if (!q) {
      return this.recipes;
    }
    return this.recipes.filter(
      (r) =>
        (r.recipe_name || '').toLowerCase().includes(q) ||
        (r.description || '').toLowerCase().includes(q)
    );
  }

  /** تحديد الكل في القائمة المفلترة (للقالب — لا يدعم arrow functions) */
  get isAllFilteredSelected(): boolean {
    const list = this.filteredRecipes;
    return (
      list.length > 0 && list.every((recipe) => this.selectedIds.has(recipe.id))
    );
  }

  loadRecipes(): void {
    this.manufacturingService.listRecipes().subscribe({
      next: (data) => {
        this.recipes = data;
      },
    });
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
    const list = this.filteredRecipes;
    const allSelected =
      list.length > 0 && list.every((r) => this.selectedIds.has(r.id));
    if (allSelected) {
      list.forEach((r) => this.selectedIds.delete(r.id));
    } else {
      list.forEach((r) => this.selectedIds.add(r.id));
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
        },
        error: () => {
          this.bulkDeleting = false;
        },
      });
  }
}
