import { Component, OnInit } from '@angular/core';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { MatSnackBar } from '@angular/material/snack-bar';
import { AuthService } from 'src/app/auth/auth.service';
import { SnackBarComponent } from 'src/app/shared/snack-bar/snack-bar.component';
import { CategoryService } from '../services/category.service';
import { ProductionService } from '../services/production.service';
import { UnitsService } from '../services/units.service';
import { ActivatedRoute, Router } from '@angular/router';
import { environment } from 'src/env/env';
import { StockService } from 'src/app/warehouse/services/stock.service';
import { catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { warehouseOptionsFromStocks } from 'src/app/shared/constants/warehouse-stock-rows';

/** قيم نموذج تعديل الصنف — يُستخدم لتطبيع patchValue و FormData */
interface EditCategoryFormValue {
  category_name: string | null;
  category_price: number | null;
  initial_balance: number | null;
  minimum_quantity: number | null;
  warehouse: string | null;
  measurement_id: number | null;
  production_id: number | null;
  item_code: string | null;
  color: string | null;
  recipe_id: number | null;
}

@Component({
  selector: 'app-edit-category',
  templateUrl: './edit-category.component.html',
  styleUrls: ['./edit-category.component.css'],
})
export class EditCategoryComponent implements OnInit {
  user!: string;
  productionData: any;
  unitsData: any;
  warehouse = '';
  stockData: any[] = [];
  recipesData: Array<{ id: number; recipe_name: string; description?: string | null }> = [];
  imgtext = 'صورة ';
  fileopend = false;
  price = 0;
  imgUrl!: string;
  id!: any;

  constructor(
    private _snackBar: MatSnackBar,
    private production: ProductionService,
    private units: UnitsService,
    private category: CategoryService,
    private StockService: StockService,
    private authService: AuthService,
    private route: ActivatedRoute,
    private router: Router
  ) {
    this.imgUrl = environment.imgUrl;
  }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    this.getStockData();
    this.category.listRecipes().subscribe({
      next: (rows) => {
        this.recipesData = Array.isArray(rows) ? rows : [];
      },
      error: () => {
        this.recipesData = [];
      },
    });

    this.id = this.route.snapshot.paramMap.get('id');

    this.category.getCategoryById(this.id).subscribe((res: any) => {
      this.warehouse = res.warehouse;
      this.getProduction();
      this.getUnits();
      this.form.patchValue({
        category_name: res.category_name,
        category_price: res.category_price,
        initial_balance: res.initial_balance,
        minimum_quantity: res.minimum_quantity,
        warehouse: res.warehouse,
        measurement_id: res.measurement_id != null ? Number(res.measurement_id) : null,
        production_id: res.production_id != null ? Number(res.production_id) : null,
        item_code: res.item_code ?? '',
        color: res.color ?? '',
        recipe_id: res.recipe_id != null ? Number(res.recipe_id) : null,
      });
    });
  }

  getStockData(): void {
    this.StockService.list()
      .pipe(catchError(() => of({ data: { data: [] as any[] } })))
      .subscribe((res) => {
        const stockList = this.StockService.parseListResponse(res);
        this.stockData = warehouseOptionsFromStocks(stockList);
      });
  }

  form = new FormGroup({
    category_name: new FormControl<string | null>(null, [Validators.required]),
    category_price: new FormControl<number | null>(null, [Validators.required]),
    initial_balance: new FormControl<number | null>(null, [Validators.required]),
    minimum_quantity: new FormControl<number | null>(null, [Validators.required]),
    warehouse: new FormControl<string | null>(null, [Validators.required]),
    measurement_id: new FormControl<number | null>(null, [Validators.required]),
    production_id: new FormControl<number | null>(null, [Validators.required]),
    item_code: new FormControl<string | null>(null),
    color: new FormControl<string | null>(null),
    recipe_id: new FormControl<number | null>(null),
  });

  openFileInput(): void {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
      this.fileopend = true;
    }
  }

  selectedFile: any;
  onFileChanged(event: any): void {
    this.selectedFile = event.target.files[0];
    this.imgtext = this.selectedFile?.name || 'No image selected';
  }

  submitform(): void {
    const data = this.form.value as EditCategoryFormValue;
    const formData = new FormData();

    formData.append('category_name', String(data.category_name ?? ''));
    formData.append('category_price', String(data.category_price ?? ''));
    formData.append('initial_balance', String(data.initial_balance ?? ''));
    formData.append('minimum_quantity', String(data.minimum_quantity ?? ''));
    formData.append('warehouse', String(data.warehouse ?? ''));
    formData.append('measurement_id', String(data.measurement_id ?? ''));
    formData.append('production_id', String(data.production_id ?? ''));

    const ic = (data.item_code ?? '').toString().trim();
    formData.append('item_code', ic);
    const col = (data.color ?? '').toString().trim();
    formData.append('color', col);
    if (data.recipe_id != null) {
      formData.append('recipe_id', String(data.recipe_id));
    } else {
      formData.append('recipe_id', '');
    }

    const warehouseName = String(data.warehouse ?? '');
    const stockRow = this.stockData.find((elm: { name: string; id?: number }) => elm.name === warehouseName);
    if (stockRow?.id) {
      formData.append('stock_id', String(stockRow.id));
    }

    if (this.selectedFile) {
      formData.append('category_image', this.selectedFile, this.selectedFile.name);
    }

    this.category.editCategory(this.id, formData).subscribe((res) => {
      if (res) {
        this.showmsg();
        this.router.navigate(['dashboard/categories/all_categories']);
      }
    });
  }

  durationInSeconds = 2;
  showmsg(): void {
    const snackBarRef = this._snackBar.openFromComponent(SnackBarComponent, {
      duration: this.durationInSeconds * 1000,
    });
    snackBarRef.instance.message = 'تم تعديل الصنف بنجاح  ';
  }

  getProduction(): void {
    this.production.getProductions().subscribe((data: any) => {
      this.productionData = data.filter((item: any) => item.warehouse == this.warehouse);
    });
  }

  getUnits(): void {
    this.units.getUnits().subscribe((data: any) => {
      this.unitsData = data.filter((item: any) => item.warehouse == this.warehouse);
    });
  }

  onWarehouseChange(): void {
    this.warehouse = this.form.get('warehouse')?.value ?? '';
    this.getProduction();
    this.getUnits();
  }
}
