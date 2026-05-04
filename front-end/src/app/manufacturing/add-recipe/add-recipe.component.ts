import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { AutocompleteComponent } from 'angular-ng-autocomplete';
import { CategoryService } from 'src/app/categories/services/category.service';
import {
  ManufacturingService,
  RecipeImportAction,
  RecipeImportConfirmResponse,
  RecipeImportPreviewResponse,
  RecipeImportRecipePreview,
  RecipeExtraCost,
  CostBreakdown,
} from '../services/manufacturing.service';
import { Router } from '@angular/router';
import { environment } from 'src/env/env';
import { forkJoin } from 'rxjs';

@Component({
  selector: 'app-add-recipe',
  templateUrl: './add-recipe.component.html',
  styleUrls: ['./add-recipe.component.css']
})
export class AddRecipeComponent implements OnInit{
  @ViewChild('productAuto') private productAuto?: AutocompleteComponent;
  @ViewChild('ingredientAuto') private ingredientAuto?: AutocompleteComponent;

  imgUrl!: string;
  /** صفوف فشل تحميل صورتها لعرض بديل */
  imageFailed = new Set<number>();

  constructor(private category:CategoryService , private manufacturingService:ManufacturingService , private route:Router){
    this.imgUrl = environment.imgUrl;
  }

  onImgError(id: number): void {
    this.imageFailed.add(id);
  }

  ngOnInit(): void {

  }

  products:any[]=[];
  catword = 'category_name';


  recipes:any[]=[];
  catword2 = 'category_name';

  tableData:any[]=[];

  /** بعد اختيار نوع المخزن يُعرض عمود المنتج حتى لو كانت القائمة فارغة */
  selectedWarehouse: string | null = null;
  loadingWarehouseProducts = false;

  /** إزالة وسوم تمييز البحث من المكتبة (<b>) حتى لا تظهر كنص في الجدول أو الحقل */
  private stripHighlightTags(value: string): string {
    return String(value ?? '').replace(/<\/?b>/gi, '');
  }

  /** نص المنتج/الصنف في حقل الإكمال بعد الاختيار (بدون HTML) */
  selectedCategoryLabel = (item: any): string => {
    if (!item || item.category_name == null) {
      return '';
    }
    return this.stripHighlightTags(String(item.category_name));
  };

  /** نسخة نظيفة من الصنف للاستخدام في المنطق والجدول */
  private normalizeCategoryItem(item: any): any {
    if (!item) {
      return item;
    }
    return {
      ...item,
      category_name: this.stripHighlightTags(String(item.category_name ?? '')),
    };
  }

  /** بحث في اسم الصنف أو المخزن — للمنتج النهائي ولمواد الوصفة */
  filterCategorySearch = (items: any[], query: string) => {
    const q = (query ?? '').trim().toLowerCase();
    if (!q) {
      return [...items];
    }
    return items.filter((item) => {
      const name = String(item.category_name ?? '').toLowerCase();
      const wh = String(item.measurement?.warehouse ?? '').toLowerCase();
      const code = String(item.item_code ?? '').toLowerCase();
      return name.includes(q) || wh.includes(q) || code.includes(q);
    });
  };

  private normalizeCategories(result: any): any[] {
    if (Array.isArray(result)) {
      return result;
    }
    if (result && Array.isArray(result.data)) {
      return result.data;
    }
    return [];
  }

  private prepareRecipeLine(elm: any): void {
    elm.quantity = 1;
    elm.total_price = elm.quantity * elm.category_price;
  }

  productType(event: Event) {
    const warehouse = (event.target as HTMLSelectElement).value;
    if (!warehouse) {
      return;
    }
    this.selectedWarehouse = warehouse;
    this.tableData = [];
    this.extraCosts = [];
    this.costBreakdown = null;
    this.imageFailed.clear();
    this.products = [];
    this.recipes = [];
    this.totalPrice = 0;
    this.loadingWarehouseProducts = true;

    this.category.getCatBywarehouse(warehouse).subscribe({
      next: (result: any) => {
        this.products = this.normalizeCategories(result);
        this.loadingWarehouseProducts = false;
        if (warehouse === 'مخزن منتج تحت التشغيل') {
          this.loadRecipesWip();
        } else if (warehouse === 'مخزن منتج تام') {
          this.loadRecipesFinished();
        }
      },
      error: () => {
        this.loadingWarehouseProducts = false;
        this.products = [];
      },
    });
  }

  /** مواد خام فقط */
  private loadRecipesWip(): void {
    this.category.getCatBywarehouse('مخزن مواد خام').subscribe({
      next: (result: any) => {
        const rows = this.normalizeCategories(result);
        rows.forEach((elm) => this.prepareRecipeLine(elm));
        this.recipes = rows;
      },
      error: () => {
        this.recipes = [];
      },
    });
  }

  /** مواد خام + تحت التشغيل (بدون تعارض ترتيب الطلبات) */
  private loadRecipesFinished(): void {
    forkJoin({
      raw: this.category.getCatBywarehouse('مخزن مواد خام'),
      wip: this.category.getCatBywarehouse('مخزن منتج تحت التشغيل'),
    }).subscribe({
      next: ({ raw, wip }) => {
        const a = this.normalizeCategories(raw);
        const b = this.normalizeCategories(wip);
        a.forEach((elm) => this.prepareRecipeLine(elm));
        b.forEach((elm) => this.prepareRecipeLine(elm));
        this.recipes = [...a, ...b];
      },
      error: () => {
        this.recipes = [];
      },
    });
  }

  productChange(event: any) {
    this.product_id = event.id;
    this.tableData = [];
    this.extraCosts = [];
    this.costBreakdown = null;
    this.imageFailed.clear();
    this.calcTotalPrice();
  }

  onProductSelected(item: any) {
    if (!item) {
      return;
    }
    const clean = this.normalizeCategoryItem(item);
    this.productChange(clean);
    queueMicrotask(() => this.syncAutocompleteInput(this.productAuto, clean.category_name));
  }

  onIngredientSelected(item: any) {
    if (!item) {
      return;
    }
    const clean = this.normalizeCategoryItem(item);
    this.recipesChange(clean);
    queueMicrotask(() => this.syncAutocompleteInput(this.ingredientAuto, clean.category_name));
  }

  /** المكتبة تضع في الحقل نصاً يحتوي وسوم <b>؛ نستبدله بالاسم الصافي */
  private syncAutocompleteInput(ac: AutocompleteComponent | undefined, plainLabel: string): void {
    if (ac) {
      ac.query = plainLabel;
    }
  }

  recipesChange(event:any) {
    const foundElement = this.tableData.find(elm => elm.id === event.id);
    if (!foundElement) {
      this.tableData.push(event);
    }
    this.calcTotalPrice();
  }

  /** تحديث الكمية والإجمالي (سلوك قريب من Excel مع إدخال مباشر) */
  onQuantityModelChange(elm: any): void {
    let q = Number(elm.quantity);
    if (!Number.isFinite(q) || q < 1) {
      q = 1;
      elm.quantity = 1;
    } else {
      elm.quantity = q;
    }
    elm.total_price = elm.quantity * elm.category_price;
    this.calcTotalPrice();
  }

  /** Tab / Enter للانتقال بين خلايا الكمية مثل جدول Excel */
  onRecipeQtyKeydown(e: KeyboardEvent, rowIndex: number): void {
    const list = Array.from(
      document.querySelectorAll<HTMLInputElement>('.recipe-table--sheet .recipe-qty-input')
    );
    if (list.length === 0) {
      return;
    }

    if (e.key === 'Enter') {
      e.preventDefault();
      const next = list[rowIndex + 1];
      if (next) {
        next.focus();
        next.select();
      }
      return;
    }

    if (e.key === 'Tab') {
      const target = e.target as HTMLInputElement | null;
      const idx = list.indexOf(target as HTMLInputElement);
      if (idx === -1) {
        return;
      }
      const delta = e.shiftKey ? -1 : 1;
      const nextIdx = idx + delta;
      if (nextIdx >= 0 && nextIdx < list.length) {
        e.preventDefault();
        list[nextIdx].focus();
        list[nextIdx].select();
      }
    }
  }

  removeRow(index: number): void {
    const row = this.tableData[index];
    if (row?.id != null) {
      this.imageFailed.delete(row.id);
    }
    this.tableData.splice(index, 1);
    this.calcTotalPrice();
  }

  totalPrice:number=0;
  calcTotalPrice(){
    if (this.extraCosts.length > 0) {
      this.computeLocalCostBreakdown();
      return;
    }
    this.totalPrice = 0;
    this.tableData.forEach(elm=>{
      this.totalPrice += elm.total_price;
    })
    if (this.changedPrice !== 0) {
      this.totalPrice += this.changedPrice;
    }
    this.costBreakdown = null;
  }

  changedPrice:number=0;
  showChangedPrice:boolean=false;
  priceType(e:any){
    if (e.target.value === 'متغير') {
      this.showChangedPrice= true;
    } else {
      this.changedPrice=0;
      this.showChangedPrice= false;
    }
    this.calcTotalPrice();
  }
  // data for backend
  product_id!:number;
  confirmOrder(){
    if (this.product_id && this.tableData.length !==0) {
      const products = this.tableData.map(elm=>{
        return {id:elm.id , quantity:elm.quantity , total_price:elm.total_price}
      })
      const extra_costs = this.extraCosts.map((ec) => ({
        name: ec.name,
        type: ec.type,
        value: +ec.value,
      }));
      const data: Record<string, unknown> = {
        product_id: this.product_id,
        total: this.totalPrice,
        products,
      };
      if (extra_costs.length > 0) {
        data.extra_costs = extra_costs;
      }
      this.manufacturingService.addRecipe(data as any).subscribe({
        next: (result) => {
          if (result === 'success') {
            this.route.navigate(['/dashboard/manufacturing/recipes']);
          }
        },
        error: (err: { error?: { message?: string } }) => {
          const msg = err?.error?.message ?? 'تعذر حفظ الوصفة';
          alert(msg);
        },
      });
    }
  }
  //end

  // ==========================================================================
  // Excel Import flow
  // ==========================================================================

  /** عرض/إخفاء لوحة الاستيراد (تحتوي زر رفع الملف ومؤشرات الحالة). */
  importPanelOpen = false;

  /** حالة الرفع والمعالجة على الخادم. */
  importUploading = false;

  /** حالة التنفيذ النهائي (خطوة 2). */
  importConfirming = false;

  /** استجابة المعاينة (Step 1) من الـ API. */
  importPreview: RecipeImportPreviewResponse | null = null;

  /** قرار كل وصفة مكررة: replace | create_new | skip */
  recipeActionsMap: Record<string, RecipeImportAction> = {};

  /** السماح بإنشاء الأصناف الناقصة تلقائيًا. */
  allowCreateMissingItems = false;

  /** رسالة خطأ عامة لعرضها داخل اللوحة. */
  importError: string | null = null;

  /** رسالة نجاح بعد التأكيد. */
  importSuccess: string | null = null;

  /** تفتح لوحة الاستيراد وتفتح نافذة اختيار الملف. */
  openImportPanel(picker: HTMLInputElement): void {
    this.importPanelOpen = true;
    this.resetImportState(/* keepPanel */ true);
    picker.value = '';
    picker.click();
  }

  /** إغلاق لوحة الاستيراد — مع محاولة إلغاء الجلسة على الخادم إن وُجدت. */
  closeImportPanel(): void {
    if (this.importPreview?.import_token) {
      this.manufacturingService.cancelRecipesImport(this.importPreview.import_token).subscribe({
        next: () => undefined,
        error: () => undefined,
      });
    }
    this.resetImportState();
    this.importPanelOpen = false;
  }

  private resetImportState(keepPanel = false): void {
    this.importPreview = null;
    this.recipeActionsMap = {};
    this.allowCreateMissingItems = false;
    this.importError = null;
    this.importSuccess = null;
    this.importUploading = false;
    this.importConfirming = false;
    if (!keepPanel) {
      this.importPanelOpen = false;
    }
  }

  /** ينفَّذ عند اختيار الملف من <input type="file"/>. */
  onImportFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files && input.files[0];
    if (!file) {
      return;
    }

    this.importError = null;
    this.importSuccess = null;
    this.importPreview = null;
    this.recipeActionsMap = {};
    this.allowCreateMissingItems = false;
    this.importUploading = true;

    this.manufacturingService.previewRecipesImport(file).subscribe({
      next: (res) => {
        this.importUploading = false;
        this.importPreview = res;
        this.allowCreateMissingItems = res.missing_items.length === 0;
        this.recipeActionsMap = {};
        res.recipes.forEach((r) => {
          if (r.exists) {
            this.recipeActionsMap[r.normalized_name] = 'replace';
          }
        });
        input.value = '';
      },
      error: (err: HttpErrorResponse) => {
        this.importUploading = false;
        input.value = '';
        this.importError = this.extractErrorMessage(err, 'تعذّر رفع الملف.');
      },
    });
  }

  setRecipeAction(recipe: RecipeImportRecipePreview, action: RecipeImportAction): void {
    this.recipeActionsMap[recipe.normalized_name] = action;
  }

  /** هل يملك المستخدم ما يكفي من قرارات للمتابعة؟ */
  canConfirmImport(): boolean {
    if (!this.importPreview) {
      return false;
    }
    if (this.importPreview.missing_items.length > 0 && !this.allowCreateMissingItems) {
      return false;
    }
    if (this.importPreview.recipes.some((r) => r.exists && !this.recipeActionsMap[r.normalized_name])) {
      return false;
    }
    return true;
  }

  confirmImport(): void {
    if (!this.importPreview || !this.canConfirmImport()) {
      return;
    }

    this.importConfirming = true;
    this.importError = null;

    this.manufacturingService
      .confirmRecipesImport({
        import_token: this.importPreview.import_token,
        create_missing_items: this.allowCreateMissingItems,
        recipe_actions: this.recipeActionsMap,
      })
      .subscribe({
        next: (res: RecipeImportConfirmResponse) => {
          this.importConfirming = false;
          this.importSuccess = this.buildSuccessMessage(res);
          this.importPreview = null;
          this.recipeActionsMap = {};
        },
        error: (err: HttpErrorResponse) => {
          this.importConfirming = false;
          this.importError = this.extractErrorMessage(err, 'تعذّر تنفيذ الاستيراد.');
        },
      });
  }

  private buildSuccessMessage(res: RecipeImportConfirmResponse): string {
    const r = res.result;
    const parts = [
      res.message,
      `تم إنشاء ${r.recipes_created} وصفة`,
      `تم تحديث ${r.recipes_updated} وصفة`,
      `أُنشئ ${r.items_created} صنف خام (مخزن مواد خام)`,
      `أُنشئ ${r.products_created} منتج تام (مخزن منتج تام)`,
    ];
    if (r.products_linked > 0) {
      parts.push(`تم ربط ${r.products_linked} منتج موجود بوصفته`);
    }
    if (r.manufactures_created > 0 || r.manufactures_updated > 0) {
      const added = r.manufactures_created > 0 ? `أُضيفت ${r.manufactures_created} وصفة تصنيع` : '';
      const updated = r.manufactures_updated > 0 ? `حُدِّثت ${r.manufactures_updated} وصفة تصنيع` : '';
      parts.push([added, updated].filter((x) => x).join(' و '));
    }
    parts.push(`تم حفظ ${r.ingredients_upserted} مكوِّن`);
    if (r.recipes_skipped > 0) {
      parts.push(`تم تخطّي ${r.recipes_skipped} وصفة`);
    }
    return parts.join(' · ');
  }

  /** فتح صفحة الأصناف لعرض ما تم استيراده. */
  openItemsPage(): void {
    this.route.navigate(['/dashboard/categories/all_categories']);
  }

  /** إعادة فتح صفحة وصفات التصنيع (تفرض تحميلاً جديداً). */
  reloadRecipesList(): void {
    this.route.navigateByUrl('/dashboard/manufacturing/recipes', { skipLocationChange: true }).then(() => {
      this.route.navigate(['/dashboard/manufacturing/recipes']);
    });
  }

  private extractErrorMessage(err: HttpErrorResponse, fallback: string): string {
    const body = err?.error;
    if (body?.errors && typeof body.errors === 'object') {
      const firstKey = Object.keys(body.errors)[0];
      const msgs = firstKey ? body.errors[firstKey] : null;
      if (Array.isArray(msgs) && msgs.length > 0) {
        return String(msgs[0]);
      }
    }
    if (body?.message) {
      const msg = String(body.message);
      const tech = body?.error != null && String(body.error).trim() !== '' ? String(body.error) : '';
      // Live API returns generic Arabic text + technical detail in `error` (e.g. missing php-zip).
      if (tech && tech !== msg && !msg.includes(tech)) {
        return `${msg} (${tech})`;
      }
      return msg;
    }
    return fallback;
  }

  // ==========================================================================
  // Dynamic Extra Costs (Task 3)
  // ==========================================================================

  extraCosts: RecipeExtraCost[] = [];
  costBreakdown: CostBreakdown | null = null;

  /** صفوف مؤقتة قبل حفظ الوصفة — لا يوجد طلب لـ /recipes/0/extra-costs */
  private nextLocalExtraCostId = -1;

  newExtraCostName = '';
  newExtraCostType: 'fixed' | 'percentage' = 'fixed';
  newExtraCostValue: number | null = null;
  editingExtraCostId: number | null = null;
  editExtraCostName = '';
  editExtraCostType: 'fixed' | 'percentage' = 'fixed';
  editExtraCostValue: number | null = null;

  /** Load extra costs from an existing recipe (after creation or for editing). */
  loadExtraCosts(recipeId: number): void {
    this.manufacturingService.getExtraCosts(recipeId).subscribe({
      next: (res) => {
        this.extraCosts = res.extra_costs;
        this.costBreakdown = res.breakdown;
      },
    });
  }

  /** إضافة تكلفة إضافية قبل حفظ الوصفة — تخزين محلي فقط (يُرسل مع تأكيد الوصفة). */
  addExtraCost(): void {
    const name = this.newExtraCostName?.trim();
    const val = this.newExtraCostValue;
    if (!name || val == null || Number(val) < 0) {
      return;
    }
    const row: RecipeExtraCost = {
      id: this.nextLocalExtraCostId--,
      recipe_id: 0,
      name,
      type: this.newExtraCostType,
      value: Number(val),
    };
    this.extraCosts.push(row);
    this.newExtraCostName = '';
    this.newExtraCostValue = null;
    this.newExtraCostType = 'fixed';
    this.computeLocalCostBreakdown();
  }

  /** نفس منطق CostCalculationService: مواد + ثابت + نسبة من تكلفة المواد + تكلفة متغيرة إن وُجدت */
  private computeLocalCostBreakdown(): void {
    const materials = this.tableData.reduce((s, e) => s + (+e.total_price || 0), 0);
    let fixed = 0;
    let pctSum = 0;
    for (const ec of this.extraCosts) {
      if (ec.type === 'fixed') {
        fixed += +ec.value;
      } else {
        pctSum += (materials * (+ec.value)) / 100;
      }
    }
    const variable = Number(this.changedPrice) || 0;
    const finalCost = materials + fixed + pctSum + variable;
    this.costBreakdown = {
      materials_cost: String(materials),
      fixed_costs: String(fixed),
      percentage_costs: String(pctSum),
      final_cost: String(finalCost),
      margin_percent: null,
    };
    this.recalcTotalWithExtras();
  }

  startEditExtraCost(ec: RecipeExtraCost): void {
    this.editingExtraCostId = ec.id;
    this.editExtraCostName = ec.name;
    this.editExtraCostType = ec.type;
    this.editExtraCostValue = +ec.value;
  }

  cancelEditExtraCost(): void {
    this.editingExtraCostId = null;
  }

  saveEditExtraCost(extraCostId: number): void {
    if (this.editExtraCostValue == null || this.editExtraCostValue < 0) {
      return;
    }
    if (extraCostId < 0) {
      const idx = this.extraCosts.findIndex((e) => e.id === extraCostId);
      if (idx >= 0) {
        const name = this.editExtraCostName?.trim() || this.extraCosts[idx].name;
        this.extraCosts[idx] = {
          ...this.extraCosts[idx],
          name,
          type: this.editExtraCostType,
          value: Number(this.editExtraCostValue),
        };
      }
      this.editingExtraCostId = null;
      this.computeLocalCostBreakdown();
      return;
    }
  }

  deleteExtraCost(extraCostId: number): void {
    if (extraCostId < 0) {
      this.extraCosts = this.extraCosts.filter((e) => e.id !== extraCostId);
      if (this.extraCosts.length === 0) {
        this.costBreakdown = null;
        this.calcTotalPrice();
      } else {
        this.computeLocalCostBreakdown();
      }
    }
  }

  /** Recalculate the on-screen total, incorporating extra costs from the breakdown. */
  private recalcTotalWithExtras(): void {
    let base = 0;
    this.tableData.forEach((elm) => {
      base += elm.total_price;
    });
    if (this.changedPrice !== 0) {
      base += this.changedPrice;
    }
    if (this.costBreakdown) {
      base = parseFloat(this.costBreakdown.final_cost) || base;
    }
    this.totalPrice = base;
  }
}
