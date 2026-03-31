import { Component, OnInit, ViewChild } from '@angular/core';
import { AutocompleteComponent } from 'angular-ng-autocomplete';
import { CategoryService } from 'src/app/categories/services/category.service';
import { ManufacturingService } from '../services/manufacturing.service';
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
      return name.includes(q) || wh.includes(q);
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
    this.imageFailed.clear();
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

  quantityChange(e:any , i:number){
    this.tableData.forEach((elm , index)=>{
      if (index===i) {
        elm.quantity = Number(e.target.value);
        elm.total_price = elm.quantity * elm.category_price
      }
    })
    this.calcTotalPrice();
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
    this.totalPrice = 0;
    this.tableData.forEach(elm=>{
      this.totalPrice += elm.total_price;
    })
    if (this.changedPrice !== 0) {
      this.totalPrice += this.changedPrice;
    }
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
  }
  // data for backend
  product_id!:number;
  confirmOrder(){
    if (this.product_id && this.tableData.length !==0) {
      const products = this.tableData.map(elm=>{
        return {id:elm.id , quantity:elm.quantity , total_price:elm.total_price}
      })
      const data = {product_id:this.product_id , total:this.totalPrice , products}
      this.manufacturingService.addRecipe(data).subscribe(result=>{
        if (result === "success") {
          this.route.navigate(['/dashboard/manufacturing/recipes']);
        }
      })
    }
  }
  //end
}
