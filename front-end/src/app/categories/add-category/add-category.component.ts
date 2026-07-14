import { Component, OnInit, ViewChild } from '@angular/core';
import { NgForm } from '@angular/forms';
import { MatSnackBar } from '@angular/material/snack-bar';
import { SnackBarComponent } from 'src/app/shared/snack-bar/snack-bar.component';
import { CategoryService } from '../services/category.service';
import { ProductionService } from '../services/production.service';
import { UnitsService } from '../services/units.service';
import { ItemClassificationService } from '../services/item-classification.service';
import { catchError, map, startWith } from 'rxjs/operators';
import { of } from 'rxjs';
import { AuthService } from 'src/app/auth/auth.service';
import { OrderService } from 'src/app/shipping/services/order.service';
import { AssetService } from 'src/app/financial/services/asset.service';
import { StockService } from 'src/app/warehouse/services/stock.service';
import { warehouseOptionsFromStocks } from 'src/app/shared/constants/warehouse-stock-rows';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { canSelectCategoryWarehouse } from 'src/app/shared/utils/category-warehouse-access';

@Component({
 selector: 'app-add-category',
 templateUrl: './add-category.component.html',
 styleUrls: ['./add-category.component.css']
})
export class AddCategoryComponent implements OnInit {
 user!: string;
 productionData: any;
 classificationsData: any;
 stockData: any = [];
 unitsData: any;
 warehouse: string = '';
 imgtext: string = "اختر صورة";
 errorMessage!: any;
 fileopend: boolean = false;

 @ViewChild('addCat', { static: false }) addCat!: NgForm;
 constructor(private _snackBar: MatSnackBar, private production: ProductionService, private units: UnitsService, private classifications: ItemClassificationService, private StockService: StockService,
  private category: CategoryService, private authService: AuthService, private orderService: OrderService, private rbac: RbacService) { }

 ngOnInit() {
  this.user = this.authService.getUser();
  this.category.allCategories().subscribe({
   next: (result: any) => { this.products = Array.isArray(result) ? result : []; },
   error: () => { this.products = []; },
  });
  this.getStockData();
  this.category.listRecipes().subscribe({
   next: (rows) => { this.recipesData = Array.isArray(rows) ? rows : []; },
   error: () => { this.recipesData = []; },
  });
 }

 getStockData() {
  this.StockService.list()
   .pipe(catchError(() => of({ data: { data: [] as any[] } })))
   .subscribe(res => {
    const stockList = this.StockService.parseListResponse(res);
    this.stockData = warehouseOptionsFromStocks(stockList);
   });
 }

 products: any[] = [];
 /** وصفات مسجلة مسبقاً (جدول recipes) */
 recipesData: Array<{ id: number; recipe_name: string; description?: string | null }> = [];
 catword: any = "category_name";
 category_name!: any;
 productChange(event) {

 }
 resetInp() {
  this.category_name = undefined;
 }

 openFileInput() {
  const fileInput = document.getElementById('fileInput');
  if (fileInput) {
   fileInput.click();
   this.fileopend = true;
  }
 }
 selectedFile: any;
 onFileChanged(event: any) {
  this.selectedFile = event.target.files[0];
  this.imgtext = this.selectedFile?.name || 'No image selected';
  console.log(this.selectedFile);
 }


 addCategory(data: any) {
  const formData = new FormData();

  // // Append the category data to FormData
  formData.append('category_name', this.category_name);
  formData.append('category_price', data.value.price);
  formData.append('initial_balance', data.value.inital_price);
  formData.append('minimum_quantity', data.value.min_quantity);
  formData.append('warehouse', data.value.warehouse);
  formData.append('measurement_id', data.value.unit);
  formData.append('production_id', data.value.production);
  const stockRow = this.stockData.find((elm: { name: string; id?: number }) => elm.name === data.value.warehouse);
  if (stockRow?.id) {
   formData.append('stock_id', String(stockRow.id));
  }

  const itemCode = (data.value.item_code ?? '').toString().trim();
  formData.append('item_code', itemCode);
  const colorVal = (data.value.color ?? '').toString().trim();
  formData.append('color', colorVal);
  const classificationVal = data.value.item_classification_id;
  if (classificationVal !== '' && classificationVal !== null && classificationVal !== undefined) {
   formData.append('item_classification_id', String(classificationVal));
  }
  const recipeId = data.value.recipe_id;
  if (recipeId !== '' && recipeId !== null && recipeId !== undefined) {
   formData.append('recipe_id', String(recipeId));
  }

  const productType = (data.value.product_type ?? '').toString().trim();
  if (productType) {
   formData.append('product_type', productType);
  }
  formData.append('allow_wip_sale', data.value.allow_wip_sale ? '1' : '0');

  // Append the image file to FormData
  if (this.selectedFile) {
   formData.append('category_image', this.selectedFile, this.selectedFile.name);
  }
  console.log(formData);
  this.category.addCategory(formData).subscribe((data) => {
   console.log(data);

   this.errorMessage = null;
   this.clr();
   this.showmsg();
  }, (error) => {
   console.log(error.error.message);
   this.errorMessage = error.error.message;

  }
  )
 }

 durationInSeconds = 2;
 showmsg() {
  const snackBarRef = this._snackBar.openFromComponent(SnackBarComponent, {
   duration: this.durationInSeconds * 1000,
  });
  snackBarRef.instance.message = 'تم اضافة الصنف بنجاح  ';
 }
 getProduction() {
  this.production.getProductions().pipe(catchError(() => of([]))).subscribe((data: any) => {
   this.productionData = (Array.isArray(data) ? data : []).filter((item) => item.warehouse == this.warehouse);
   this.syncSelectModel('production', this.productionData, 'id');
  })
 }

 getUnits() {
  this.units.getUnits().pipe(catchError(() => of([]))).subscribe((data: any) => {
   this.unitsData = (Array.isArray(data) ? data : []).filter((item) => item.warehouse == this.warehouse);
   this.syncSelectModel('unit', this.unitsData, 'id');
  })
 }

 getClassifications() {
  this.classifications.getClassifications().pipe(catchError(() => of([]))).subscribe((data: any) => {
   this.classificationsData = (Array.isArray(data) ? data : []).filter((item) => item.warehouse == this.warehouse);
  })
 }

 canSelectWarehouse(warehouseName: string): boolean {
  return canSelectCategoryWarehouse(warehouseName, this.user, this.rbac);
 }

 onWarehouseChange(event: any) {
  this.warehouse = event.target.value;
  this.unitsData = [];
  this.productionData = [];
  this.classificationsData = [];
  const form = this.addCat?.form;
  if (form) {
   form.controls['unit']?.setValue('');
   form.controls['production']?.setValue('');
   form.controls['item_classification_id']?.setValue('');
  }
  this.getProduction();
  this.getUnits();
  this.getClassifications();
 }

 /**
  * بعد تعبئة خيارات السيلكت من السيرفر، المتصفح قد يُظهر أول خيار بدون أن يُحدَّث ngModel،
  * فيبقى required فاشل. نضبط القيمة صراحةً بعد رسم الخيارات.
  */
 private syncSelectModel(controlName: 'unit' | 'production', rows: any[] | undefined, idKey: string) {
  setTimeout(() => {
   const form = this.addCat?.form;
   const ctrl = form?.controls[controlName];
   if (!ctrl) return;
   if (!rows?.length) {
    ctrl.setValue('');
    ctrl.updateValueAndValidity();
    return;
   }
   const id = rows[0][idKey];
   const value = id != null && id !== '' ? id : '';
   ctrl.setValue(value);
   ctrl.markAsDirty();
   ctrl.updateValueAndValidity();
  });
 }


 clr() {
  this.imgtext = "اختر صورة";
  this.fileopend = false;
  this.addCat.resetForm();
 }
}
