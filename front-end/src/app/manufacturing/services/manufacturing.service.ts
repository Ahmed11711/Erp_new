import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

export interface RecipeImportIngredientPreview {
  item_name: string;
  normalized_name: string;
  quantity: number;
  unit: string | null;
  unit_cost: number | null;
  color: string | null;
  item_exists: boolean;
  existing_item_id: number | null;
  existing_item_name: string | null;
  existing_item_price: number | null;
}

export interface RecipeImportRecipePreview {
  recipe_name: string;
  normalized_name: string;
  exists: boolean;
  existing_recipe_id: number | null;
  existing_recipe_name: string | null;
  ingredients_count: number;
  ingredients: RecipeImportIngredientPreview[];
}

export interface RecipeImportPreviewResponse {
  import_token: string;
  expires_in_minutes: number;
  needs_missing_items_decision: boolean;
  needs_conflicts_decision: boolean;
  missing_items: string[];
  existing_recipes: string[];
  recipes: RecipeImportRecipePreview[];
  summary: {
    recipes_total: number;
    missing_items_total: number;
    existing_recipes_total: number;
  };
  message: string;
}

export type RecipeImportAction = 'replace' | 'create_new' | 'skip';

export interface RecipeImportConfirmPayload {
  import_token: string;
  create_missing_items: boolean;
  recipe_actions?: Record<string, RecipeImportAction>;
}

export interface RecipeImportConfirmResponse {
  message: string;
  result: {
    items_created: number;
    items_updated: number;
    recipes_created: number;
    recipes_updated: number;
    recipes_skipped: number;
    ingredients_upserted: number;
    products_created: number;
    products_linked: number;
    manufactures_created: number;
    manufactures_updated: number;
    committed_recipes: Array<{
      id: number;
      recipe_name: string;
      action: string;
      ingredients_count: number;
      product_item_id: number | null;
      product_item_name: string | null;
      product_warehouse: string | null;
      manufacture_id: number | null;
      total_cost: number | null;
    }>;
  };
}

export interface RecipeExtraCost {
  id: number;
  recipe_id: number;
  name: string;
  type: 'fixed' | 'percentage';
  value: number;
}

export interface CostBreakdown {
  materials_cost: string;
  fixed_costs: string;
  percentage_costs: string;
  final_cost: string;
  selling_price?: string;
  margin_percent?: string | null;
}

export interface RecipeDetail {
  recipe: any;
  breakdown: CostBreakdown;
}

@Injectable({
  providedIn: 'root'
})
export class ManufacturingService {

  constructor(private http:HttpClient) { }

  addRecipe(data:any){
    return this.http.post(`${environment.Url}/manufacture`,data);
  }

  /** Step 1 — upload the Excel and get a parsed preview + decisions needed. */
  previewRecipesImport(file: File, sheet?: string): Observable<RecipeImportPreviewResponse> {
    const form = new FormData();
    form.append('file', file);
    if (sheet) {
      form.append('sheet', sheet);
    }
    return this.http.post<RecipeImportPreviewResponse>(`${environment.Url}/recipes/import`, form);
  }

  /** Step 2 — persist the import using the user's decisions. */
  confirmRecipesImport(payload: RecipeImportConfirmPayload): Observable<RecipeImportConfirmResponse> {
    return this.http.post<RecipeImportConfirmResponse>(`${environment.Url}/recipes/import/confirm`, payload);
  }

  /** Cancel a pending import session (optional). */
  cancelRecipesImport(importToken: string) {
    return this.http.post(`${environment.Url}/recipes/import/cancel`, { import_token: importToken });
  }

  confirm(data:any){
    return this.http.post(`${environment.Url}/manufacture/confirm`,data);
  }

  getAllRecipes() {
    return this.http.get<any[]>(`${environment.Url}/manufacture`);
  }

  /**
   * @param warehouse اسم المخزن
   * @param scope manufacture_only = أصناف لها Manufacture في هذا المخزن؛ all_categories = كل الأصناف (لدمج WIP→تام)
   */
  manfuctureByWarhouse(
    warehouse: string,
    scope: 'manufacture_only' | 'all_categories' = 'manufacture_only'
  ) {
    let params = new HttpParams().set('warehouse', warehouse);
    if (scope === 'all_categories') {
      params = params.set('scope', 'all_categories');
    }
    return this.http.get(`${environment.Url}/manufacture/manfucture_by_warhouse`, { params });
  }

  confirmed(){
    return this.http.get(`${environment.Url}/manufacture/confirmed`)
  }

  done(id:any){
    return this.http.get(`${environment.Url}/manufacture/done/${id}`)
  }

  // ──────────────────────────────────────────────────────────
  // Recipe detail + cost breakdown
  // ──────────────────────────────────────────────────────────

  getRecipeDetail(recipeId: number): Observable<RecipeDetail> {
    return this.http.get<RecipeDetail>(`${environment.Url}/recipes/${recipeId}`);
  }

  getRecipeBreakdown(recipeId: number): Observable<CostBreakdown> {
    return this.http.get<CostBreakdown>(`${environment.Url}/recipes/${recipeId}/breakdown`);
  }

  // ──────────────────────────────────────────────────────────
  // Recipe extra costs CRUD
  // ──────────────────────────────────────────────────────────

  getExtraCosts(recipeId: number): Observable<{ extra_costs: RecipeExtraCost[]; breakdown: CostBreakdown }> {
    return this.http.get<{ extra_costs: RecipeExtraCost[]; breakdown: CostBreakdown }>(
      `${environment.Url}/recipes/${recipeId}/extra-costs`
    );
  }

  addExtraCost(recipeId: number, data: { name: string; type: string; value: number }): Observable<{ extra_cost: RecipeExtraCost; breakdown: CostBreakdown }> {
    return this.http.post<{ extra_cost: RecipeExtraCost; breakdown: CostBreakdown }>(
      `${environment.Url}/recipes/${recipeId}/extra-costs`, data
    );
  }

  updateExtraCost(recipeId: number, extraCostId: number, data: Partial<{ name: string; type: string; value: number }>): Observable<{ extra_cost: RecipeExtraCost; breakdown: CostBreakdown }> {
    return this.http.put<{ extra_cost: RecipeExtraCost; breakdown: CostBreakdown }>(
      `${environment.Url}/recipes/${recipeId}/extra-costs/${extraCostId}`, data
    );
  }

  deleteExtraCost(recipeId: number, extraCostId: number): Observable<{ message: string; breakdown: CostBreakdown }> {
    return this.http.delete<{ message: string; breakdown: CostBreakdown }>(
      `${environment.Url}/recipes/${recipeId}/extra-costs/${extraCostId}`
    );
  }

  // ──────────────────────────────────────────────────────────
  // Stock check + recipe execution
  // ──────────────────────────────────────────────────────────

  checkRecipeStock(recipeId: number, batchQty = 1): Observable<{ sufficient: boolean; shortages: any[] }> {
    return this.http.get<{ sufficient: boolean; shortages: any[] }>(
      `${environment.Url}/recipes/${recipeId}/check-stock`, { params: { batch_qty: batchQty.toString() } }
    );
  }

  executeRecipe(recipeId: number, finishedItemId: number, batchQty = 1): Observable<any> {
    return this.http.post(`${environment.Url}/recipes/${recipeId}/execute`, {
      finished_item_id: finishedItemId,
      batch_qty: batchQty,
    });
  }

  getRecipeMovements(recipeId: number): Observable<{ movements: any[] }> {
    return this.http.get<{ movements: any[] }>(`${environment.Url}/recipes/${recipeId}/movements`);
  }

  // ──────────────────────────────────────────────────────────
  // Recipe CRUD
  // ──────────────────────────────────────────────────────────

  listRecipes(): Observable<any[]> {
    return this.http.get<any[]>(`${environment.Url}/recipes`);
  }

  createRecipe(data: any): Observable<any> {
    return this.http.post(`${environment.Url}/recipes`, data);
  }

  updateRecipe(recipeId: number, data: any): Observable<any> {
    return this.http.put(`${environment.Url}/recipes/${recipeId}`, data);
  }

  deleteRecipe(recipeId: number): Observable<any> {
    return this.http.delete(`${environment.Url}/recipes/${recipeId}`);
  }

  bulkDeleteRecipes(ids: number[]): Observable<any> {
    return this.http.post(`${environment.Url}/recipes/bulk-delete`, { ids });
  }
}
