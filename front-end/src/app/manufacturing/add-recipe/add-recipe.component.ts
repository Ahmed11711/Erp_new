import { Component, OnDestroy, OnInit, ViewChild } from '@angular/core';
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
import { Router, ActivatedRoute, ParamMap } from '@angular/router';
import { environment } from 'src/env/env';
import { forkJoin, Subscription } from 'rxjs';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { RBAC_ROUTE } from 'src/app/guards/rbac-route-data';

@Component({
  selector: 'app-add-recipe',
  templateUrl: './add-recipe.component.html',
  styleUrls: ['./add-recipe.component.css']
})
export class AddRecipeComponent implements OnInit, OnDestroy {
  @ViewChild('productAuto') private productAuto?: AutocompleteComponent;
  @ViewChild('ingredientAuto') private ingredientAuto?: AutocompleteComponent;

  imgUrl!: string;
  /** صفوف فشل تحميل صورتها لعرض بديل */
  imageFailed = new Set<number>();
  private routeParamsSub?: Subscription;
  private routeIdSub?: Subscription;
  private activeWarehouse: string | null = null;
  private pendingRoutePreselect: { productId: number; productName?: string } | null = null;
  private pendingEditRecipe: any = null;
  private pendingDuplicateRecipe: any = null;

  editingRecipeId: number | null = null;
  /** نسخ وصفة قائمة لمنتج نهائي آخر (إنشاء وصفة جديدة بمكونات منسوخة). */
  duplicateMode = false;
  loadingRecipe = false;
  savingRecipe = false;
  bomLocked = false;
  recipeName = '';
  recipeDescription = '';

  constructor(
    private category: CategoryService,
    private manufacturingService: ManufacturingService,
    private route: Router,
    private activatedRoute: ActivatedRoute,
    private rbac: RbacService,
  ) {
    this.imgUrl = environment.imgUrl;
  }

  onImgError(id: number): void {
    this.imageFailed.add(id);
  }

  ngOnInit(): void {
    this.routeIdSub = this.activatedRoute.paramMap.subscribe((params) => {
      const id = Number(params.get('id') ?? 0);
      if (id > 0) {
        this.beginEditRecipe(id);
      }
    });
    this.routeParamsSub = this.activatedRoute.queryParamMap.subscribe((params) => {
      const duplicateFrom = Number(params.get('duplicateFrom') ?? 0);
      if (duplicateFrom > 0) {
        this.beginDuplicateRecipe(duplicateFrom);
        return;
      }
      this.handleRouteParams(params);
    });
  }

  ngOnDestroy(): void {
    this.routeParamsSub?.unsubscribe();
    this.routeIdSub?.unsubscribe();
  }

  get isEditMode(): boolean {
    return this.editingRecipeId != null;
  }

  get isDuplicateMode(): boolean {
    return this.duplicateMode;
  }

  canWriteRecipe(): boolean {
    return this.rbac.canAny(RBAC_ROUTE.manufacturingWrite);
  }

  private beginEditRecipe(id: number): void {
    this.editingRecipeId = id;
    this.loadingRecipe = true;
    this.manufacturingService.getRecipeDetail(id).subscribe({
      next: (res) => {
        const recipe = res.recipe;
        this.bomLocked = Boolean(recipe?.bom_locked);
        this.recipeName = String(recipe?.recipe_name ?? '');
        this.recipeDescription = String(recipe?.description ?? '');
        this.costBreakdown = res.breakdown ?? null;
        const warehouse = String(recipe?.output_item?.warehouse ?? '').trim();
        if (!warehouse) {
          this.loadingRecipe = false;
          alert('الوصفة لا ترتبط بمنتج نهائي — لا يمكن تعديلها من هذه الشاشة.');
          this.route.navigate(['/dashboard/manufacturing/recipes']);
          return;
        }
        this.pendingEditRecipe = recipe;
        this.loadWarehouse(warehouse);
        this.loadingRecipe = false;
      },
      error: () => {
        this.loadingRecipe = false;
        alert('تعذر تحميل الوصفة');
        this.route.navigate(['/dashboard/manufacturing/recipes']);
      },
    });
  }

  /** نسخ وصفة قائمة: نحمّل مكوناتها وتكاليفها ثم نترك المنتج النهائي فارغاً ليختار المستخدم منتجاً آخر. */
  private beginDuplicateRecipe(id: number): void {
    if (this.duplicateMode || this.isEditMode) {
      return;
    }
    this.duplicateMode = true;
    this.loadingRecipe = true;
    this.manufacturingService.getRecipeDetail(id).subscribe({
      next: (res) => {
        const recipe = res.recipe;
        const warehouse = String(recipe?.output_item?.warehouse ?? '').trim();
        if (!warehouse) {
          this.loadingRecipe = false;
          this.duplicateMode = false;
          alert('الوصفة المصدر لا ترتبط بمنتج نهائي — لا يمكن تكرارها.');
          this.route.navigate(['/dashboard/manufacturing/recipes']);
          return;
        }
        this.recipeName = String(recipe?.recipe_name ?? '');
        this.recipeDescription = String(recipe?.description ?? '');
        this.pendingDuplicateRecipe = recipe;
        this.loadWarehouse(warehouse);
        this.loadingRecipe = false;
      },
      error: () => {
        this.loadingRecipe = false;
        this.duplicateMode = false;
        alert('تعذر تحميل الوصفة المراد تكرارها');
        this.route.navigate(['/dashboard/manufacturing/recipes']);
      },
    });
  }

  private applyDuplicateRecipeData(recipe: any): void {
    this.tableData = (recipe.ingredients ?? []).map((ing: any) => {
      const item = ing.item ?? {};
      const id = Number(item.id ?? ing.item_id ?? 0);
      const price = Number(ing.unit_cost ?? item.category_price ?? item.unit_price ?? 0);
      const qty = Number(ing.quantity ?? 1);
      return this.normalizeCategoryItem({
        ...item,
        id,
        category_price: price,
        quantity: qty,
        total_price: qty * price,
      });
    });

    this.extraCosts = (recipe.extra_costs ?? []).map((ec: any) => ({
      id: this.nextLocalExtraCostId--,
      recipe_id: 0,
      name: String(ec.name ?? ''),
      type: ec.type === 'percentage' ? 'percentage' : 'fixed',
      value: Number(ec.value),
    }));

    if (this.extraCosts.length > 0) {
      this.computeLocalCostBreakdown();
    } else {
      this.calcTotalPrice();
    }
  }

  private applyEditRecipeData(recipe: any): void {
    if (recipe.output_item) {
      const output = recipe.output_item;
      const price = Number(output.category_price ?? output.unit_price ?? 0);
      this.setSelectedProduct(
        this.normalizeCategoryItem({
          ...output,
          category_price: price,
        }),
      );
    }

    this.tableData = (recipe.ingredients ?? []).map((ing: any) => {
      const item = ing.item ?? {};
      const id = Number(item.id ?? ing.item_id ?? 0);
      const price = Number(ing.unit_cost ?? item.category_price ?? item.unit_price ?? 0);
      const qty = Number(ing.quantity ?? 1);
      return this.normalizeCategoryItem({
        ...item,
        id,
        category_price: price,
        quantity: qty,
        total_price: qty * price,
      });
    });

    this.extraCosts = (recipe.extra_costs ?? []).map((ec: any) => ({
      id: Number(ec.id),
      recipe_id: Number(ec.recipe_id ?? this.editingRecipeId ?? 0),
      name: String(ec.name ?? ''),
      type: ec.type === 'percentage' ? 'percentage' : 'fixed',
      value: Number(ec.value),
    }));

    if (this.extraCosts.length > 0) {
      this.computeLocalCostBreakdown();
    } else {
      this.calcTotalPrice();
    }
  }

  private handleRouteParams(params: ParamMap): void {
    const productId = Number(
      params.get('productId') ?? params.get('productid') ?? 0,
    );
    const warehouse = params.get('warehouse')?.trim() ?? '';
    const productName = params.get('productName')?.trim() || undefined;

    if (!warehouse) {
      return;
    }

    const preselect = productId > 0 ? { productId, productName } : null;

    if (warehouse === this.activeWarehouse && preselect) {
      this.applyRoutePreselect(preselect, warehouse);
      return;
    }

    this.pendingRoutePreselect = preselect;
    this.loadWarehouse(warehouse);
  }

  products:any[]=[];
  catword = 'category_name';


  recipes:any[]=[];
  catword2 = 'category_name';

  tableData:any[]=[];

  /** بعد اختيار نوع المخزن يُعرض عمود المنتج حتى لو كانت القائمة فارغة */
  selectedWarehouse: string | null = null;
  loadingWarehouseProducts = false;
  /** المنتج النهائي المختار (من التقرير أو من الإكمال) */
  selectedProduct: any | null = null;

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

  /** مخزن الصنف الفعلي (prefer category.warehouse over measurement.warehouse — وحدة القياس قد تكون مسجّلة تحت مخزن خام بينما الصنف تحت التشغيل). */
  warehouseLabel(item: { warehouse?: string; measurement?: { warehouse?: string } } | null | undefined): string {
    if (!item) {
      return '';
    }
    const w = item.warehouse || item.measurement?.warehouse;
    return w != null && String(w).trim() !== '' ? String(w).trim() : '';
  }

  /** بحث في اسم الصنف أو المخزن — للمنتج النهائي ولمواد الوصفة */
  filterCategorySearch = (items: any[], query: string) => {
    const q = (query ?? '').trim().toLowerCase();
    if (!q) {
      return [...items];
    }
    return items.filter((item) => {
      const name = String(item.category_name ?? '').toLowerCase();
      const wh = String(item.warehouse ?? item.measurement?.warehouse ?? '').toLowerCase();
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

  onWarehouseSelected(warehouse: string | null): void {
    if (!warehouse || warehouse === this.activeWarehouse) {
      return;
    }
    this.pendingRoutePreselect = null;
    this.loadWarehouse(warehouse);
  }

  /** تحميل أصناف المخزن واختيارياً تحديد منتج (مثلاً من تقرير أصناف بدون وصفة). */
  loadWarehouse(warehouse: string): void {
    this.activeWarehouse = warehouse;
    this.selectedWarehouse = warehouse;
    this.tableData = [];
    this.extraCosts = [];
    this.costBreakdown = null;
    this.imageFailed.clear();
    this.products = [];
    this.recipes = [];
    this.totalPrice = 0;
    this.selectedProduct = null;
    this.product_id = 0;
    this.loadingWarehouseProducts = true;

    const preselect = this.pendingRoutePreselect;
    this.pendingRoutePreselect = null;

    if (preselect && preselect.productId > 0) {
      this.applyRoutePreselect(preselect, warehouse);
    }

    this.category.getCatBywarehouse(warehouse).subscribe({
      next: (result: any) => {
        this.products = this.normalizeCategories(result);
        if (warehouse === 'مخزن منتج تحت التشغيل') {
          this.loadRecipesWip();
        } else if (warehouse === 'مخزن منتج تام') {
          this.loadRecipesFinished();
        }
        this.loadingWarehouseProducts = false;
        if (this.pendingEditRecipe) {
          this.applyEditRecipeData(this.pendingEditRecipe);
          this.pendingEditRecipe = null;
        }
        if (this.pendingDuplicateRecipe) {
          this.applyDuplicateRecipeData(this.pendingDuplicateRecipe);
          this.pendingDuplicateRecipe = null;
        }
      },
      error: () => {
        this.loadingWarehouseProducts = false;
        this.products = [];
      },
    });
  }

  private applyRoutePreselect(
    preselect: { productId: number; productName?: string },
    warehouse: string,
  ): void {
    this.manufacturingService.getRecipeProduct(preselect.productId).subscribe({
      next: (item) => {
        this.setSelectedProduct(this.normalizeCategoryItem(item));
      },
      error: () => {
        const fallback = this.products.find(
          (p) => Number(p.id) === preselect.productId,
        );
        if (fallback) {
          this.setSelectedProduct(this.normalizeCategoryItem(fallback));
          return;
        }
        if (preselect.productName) {
          this.setSelectedProduct(
            this.normalizeCategoryItem({
              id: preselect.productId,
              category_name: preselect.productName,
              warehouse,
            }),
          );
        }
      },
    });
  }

  setSelectedProduct(item: any): void {
    const clean = this.normalizeCategoryItem(item);
    const id = Number(clean?.id ?? 0);
    if (!id) {
      return;
    }
    this.selectedProduct = clean;
    this.product_id = id;
    // في وضع التكرار نحافظ على المكونات المنسوخة بعد اختيار المنتج الجديد.
    if (!this.duplicateMode) {
      this.tableData = [];
      this.extraCosts = [];
      this.costBreakdown = null;
      this.imageFailed.clear();
    }
    this.calcTotalPrice();
  }

  clearSelectedProduct(): void {
    this.selectedProduct = null;
    this.product_id = 0;
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
    this.setSelectedProduct(event);
  }

  onProductSelected(item: any) {
    if (!item) {
      return;
    }
    const clean = this.normalizeCategoryItem(item);
    this.setSelectedProduct(clean);
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
    if (this.bomLocked) {
      return;
    }
    const foundElement = this.tableData.find(elm => elm.id === event.id);
    if (!foundElement) {
      this.tableData.push(event);
    }
    this.calcTotalPrice();
  }

  /** خيارات الاستبدال — أصناف غير المنتج النهائي وغير الموجودة في صفوف أخرى */
  getReplaceOptionsForRow(rowIndex: number): any[] {
    if (rowIndex < 0 || rowIndex >= this.tableData.length) {
      return [];
    }
    const currentId = Number(this.tableData[rowIndex]?.id ?? 0);
    return this.recipes.filter((item) => {
      const id = Number(item.id ?? 0);
      if (!id || id === currentId) {
        return false;
      }
      return !this.tableData.some((elm, idx) => idx !== rowIndex && Number(elm.id) === id);
    });
  }

  onInlineRowItemSelected(rowIndex: number, item: any): void {
    if (!item) {
      return;
    }
    this.replaceRowItem(rowIndex, item);
  }

  private replaceRowItem(rowIndex: number, item: any): void {
    if (this.bomLocked) {
      return;
    }
    const clean = this.normalizeCategoryItem(item);
    const newId = Number(clean?.id ?? 0);
    if (!newId) {
      return;
    }

    const duplicateIndex = this.tableData.findIndex(
      (elm, idx) => idx !== rowIndex && Number(elm.id) === newId,
    );
    if (duplicateIndex >= 0) {
      alert('هذا الصنف موجود بالفعل في الوصفة.');
      return;
    }

    const prevRow = this.tableData[rowIndex];
    const prevQty = Number(prevRow?.quantity ?? 1);
    const qty = Number.isFinite(prevQty) && prevQty > 0 ? prevQty : 1;

    this.prepareRecipeLine(clean);
    clean.quantity = qty;
    clean.total_price = qty * Number(clean.category_price ?? 0);

    if (prevRow?.id != null) {
      this.imageFailed.delete(prevRow.id);
    }

    this.tableData[rowIndex] = clean;
    this.calcTotalPrice();
  }

  /** تحديث الكمية والإجمالي (سلوك قريب من Excel مع إدخال مباشر) */
  onQuantityModelChange(elm: any): void {
    let q = Number(elm.quantity);
    if (!Number.isFinite(q) || q <= 0) {
      q = 1;
      elm.quantity = 1;
    } else {
      elm.quantity = q;
    }
    elm.total_price = elm.quantity * Number(elm.category_price ?? 0);
    this.calcTotalPrice();
  }

  onUnitCostModelChange(elm: any): void {
    let cost = Number(elm.category_price);
    if (!Number.isFinite(cost) || cost < 0) {
      cost = 0;
      elm.category_price = 0;
    } else {
      elm.category_price = cost;
    }
    const qty = Number(elm.quantity ?? 1);
    elm.total_price = qty * elm.category_price;
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
    if (this.bomLocked) {
      return;
    }
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
    if (this.savingRecipe) {
      return;
    }
    if (!this.product_id && !this.isEditMode) {
      return;
    }
    if (!this.isEditMode && this.tableData.length === 0) {
      return;
    }

    if (this.isEditMode && this.editingRecipeId) {
      this.saveEditedRecipe();
      return;
    }

    if (this.product_id && this.tableData.length !==0) {
      const products = this.tableData.map(elm=>{
        return {id:elm.id , quantity:elm.quantity , total_price:elm.total_price}
      })
      const extra_costs = this.extraCosts
        .map((ec) => ({
          name: String(ec.name ?? '').trim(),
          type: ec.type === 'percentage' ? 'percentage' : 'fixed',
          value: Number(ec.value),
        }))
        .filter((row) => row.name.length > 0 && Number.isFinite(row.value) && row.value >= 0);
      const data: Record<string, unknown> = {
        product_id: this.product_id,
        total: this.totalPrice,
        products,
      };
      if (extra_costs.length > 0) {
        data.extra_costs = extra_costs;
      }
      this.manufacturingService.addRecipe(data as any).subscribe({
        next: () => {
          this.route.navigate(['/dashboard/manufacturing/recipes']);
        },
        error: (err: { error?: { message?: string } }) => {
          const msg = err?.error?.message ?? 'تعذر حفظ الوصفة';
          alert(msg);
        },
      });
    }
  }

  private saveEditedRecipe(): void {
    if (!this.editingRecipeId) {
      return;
    }

    const recipeId = this.editingRecipeId;
    const payload: Record<string, unknown> = {
      recipe_name: this.recipeName.trim() || undefined,
      description: this.recipeDescription.trim() || null,
    };

    if (!this.bomLocked) {
      if (!this.product_id || this.tableData.length === 0) {
        alert('يجب اختيار المنتج النهائي وإضافة مكونات للوصفة.');
        return;
      }

      const extra_costs = this.extraCosts
        .map((ec) => ({
          name: String(ec.name ?? '').trim(),
          type: ec.type === 'percentage' ? 'percentage' : 'fixed',
          value: Number(ec.value),
        }))
        .filter((row) => row.name.length > 0 && Number.isFinite(row.value) && row.value >= 0);

      payload.output_item_id = this.product_id;
      payload.ingredients = this.tableData.map((elm) => ({
        item_id: elm.id,
        quantity: Number(elm.quantity),
        unit_cost: Number(elm.category_price ?? elm.unit_price ?? 0),
      }));
      payload.extra_costs = extra_costs;
    }

    this.savingRecipe = true;
    this.manufacturingService.updateRecipe(recipeId, payload).subscribe({
      next: () => {
        this.savingRecipe = false;
        this.route.navigate(['/dashboard/manufacturing/recipes']);
      },
      error: (err: { error?: { message?: string } }) => {
        this.savingRecipe = false;
        const msg = err?.error?.message ?? 'تعذر حفظ التعديلات';
        alert(msg);
      },
    });
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

  /** الإجراء الجماعي المُفعّل حالياً (للتلوين). */
  bulkActionActive: RecipeImportAction | null = null;

  /** ملخص تغييرات الألوان على الأصناف. */
  itemsColorSummary: { updated: number; created: number; details: Array<{ name: string; color: string | null; action: string }> } = {
    updated: 0, created: 0, details: [],
  };

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
    this.bulkActionActive = null;
    this.itemsColorSummary = { updated: 0, created: 0, details: [] };
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
        this.bulkActionActive = 'replace';
        res.recipes.forEach((r) => {
          if (r.exists) {
            this.recipeActionsMap[r.normalized_name] = 'replace';
          }
        });
        this.computeItemsColorSummary(res);
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
    this.bulkActionActive = null;
  }

  setAllRecipeActions(action: RecipeImportAction): void {
    if (!this.importPreview) return;
    this.importPreview.recipes.forEach((r) => {
      if (r.exists) {
        this.recipeActionsMap[r.normalized_name] = action;
      }
    });
    this.bulkActionActive = action;
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

  private computeItemsColorSummary(res: RecipeImportPreviewResponse): void {
    const seen = new Set<string>();
    const details: Array<{ name: string; color: string | null; action: string }> = [];
    let created = 0;

    for (const recipe of res.recipes) {
      for (const ing of recipe.ingredients) {
        const key = (ing.normalized_name || ing.item_name) + '|' + (ing.color || '');
        if (seen.has(key)) continue;
        seen.add(key);

        if (ing.item_exists) {
          continue;
        }

        details.push({ name: ing.item_name, color: null, action: 'create' });
        created++;
      }
    }

    this.itemsColorSummary = { updated: 0, created, details };
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
    if (this.bomLocked) {
      return;
    }
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
    if (this.bomLocked) {
      return;
    }
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
