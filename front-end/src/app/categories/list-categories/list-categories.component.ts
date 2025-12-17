import { Component, ViewChild } from '@angular/core';
import { MatPaginator } from '@angular/material/paginator';
import { CategoryService } from '../services/category.service';
import { ProductionService } from '../services/production.service';
import { NgForm } from '@angular/forms';
import { AuthService } from 'src/app/auth/auth.service';
import Swal from 'sweetalert2';
import { environment } from 'src/env/env';
import { ExcelService } from 'src/app/excel.service';

@Component({
 selector: 'app-list-categories',
 templateUrl: './list-categories.component.html',
 styleUrls: ['./list-categories.component.css'],
})
export class ListCategoriesComponent {
 selectedQuantityFilter: string = '';
 categories: any;
 productionData: any;
 imgUrl!: string;
 length = 50;
 pageSize = 100;
 page = 0;
 pageSizeOptions = [100, 5000];
 productionline: string = '';
 warehouse: string = '';
 category_name: string = '';
 user!: string;
 ware = '';
 line = '';
 param: any = {};
 allCategories: any[] = [];

 // --------- Inline Edit Code ---------
 editingCodeId: number | null = null; // الصف اللي بيعدل
 newCategoryCode: string = '';        // الكود الجديد أثناء التعديل
 // -----------------------------------

 @ViewChild(MatPaginator, { static: true }) paginator!: MatPaginator;
 @ViewChild('listcat', { static: false }) listcat!: NgForm;

 constructor(
  private category: CategoryService,
  private production: ProductionService,
  private authService: AuthService,
  private excelService: ExcelService
 ) {
  this.imgUrl = environment.imgUrl;
 }

 ngOnInit() {
  this.user = this.authService.getUser();

  this.paginator._intl.itemsPerPageLabel = "عدد العناصر في الجدول";
  this.search();

  this.production.getProductions().subscribe((data: any) => {
   this.productionData = data;
  });
 }

 exportTableToExcel() {
  let fileName = this.warehouse ?? 'المخازن';
  const tableElement: any = document.getElementById('capture');
  this.excelService.generateExcel(fileName, tableElement, 1);
 }

 getcategories(itemsperpage: number = this.pageSize, page: number = this.page + 1) {
  this.category.getCategories(itemsperpage, page).subscribe((data: any) => {
   this.categories = data.data;
   this.length = data.total;
   this.pageSize = data.per_page;
  });
 }

 onPageChange(event: any) {
  this.pageSize = event.pageSize;
  this.page = event.pageIndex;
  this.search();
 }

 onProductionchange(event: any) {
  this.productionline = event.target.value;
  this.search();
 }

 onWarehousechange(event: any) {
  this.warehouse = event.target.value;
  this.search();
 }

 onCategorychange(event: any) {
  this.category_name = event.target.value;
  this.search();
 }

 search() {
  this.param = {};
  if (this.productionline) this.param['production_id'] = this.productionline;
  if (this.warehouse) this.param['warehouse'] = this.warehouse;
  if (this.category_name) this.param['category_name'] = this.category_name;

  this.category.searchCategories(this.pageSize, this.page + 1, this.param).subscribe((data: any) => {
   this.allCategories = data.data;
   this.applyFilters();
   this.length = data.total;
   this.pageSize = data.per_page;
  });
 }

 applyFilters() {
  this.categories = this.allCategories.filter(item => {
   if (this.selectedQuantityFilter) {
    const qty = item.quantity;
    switch (this.selectedQuantityFilter) {
     case '0': if (qty > 0) return false; break;
     case '10': if (qty <= 0 || qty > 10) return false; break;
     case 'more': if (qty <= 10) return false; break;
    }
   }
   if (this.category_name && !item.category_name.includes(this.category_name)) return false;
   if (this.warehouse && item.warehouse != this.warehouse) return false;
   if (this.productionline && item.production.production_line != this.productionline) return false;
   return true;
  });
 }

 clearSearch() {
  this.listcat.resetForm();
  this.line = '';
  this.param = {};
  this.category_name = '';
  this.warehouse = '';
  this.productionline = '';

  if (this.user == 'Customer Service') {
   this.warehouse = 'مخزن منتج تام';
   this.ware = 'مخزن منتج تام';
   this.param['warehouse'] = this.ware;
  }
  this.search();
 }

 deleteCategory(id: number) {
  Swal.fire({
   title: 'تاكيد الحذف ؟',
   icon: 'warning',
   showCancelButton: true,
   confirmButtonText: 'نعم',
   cancelButtonText: 'لا',
  }).then((result: any) => {
   if (result.isConfirmed) {
    this.category.deleteCategory(id).subscribe(res => {
     this.search();
     Swal.fire({ icon: 'success', timer: 3000, showConfirmButton: false });
    });
   }
  });
 }

 // ------------------------
 // دوال لتلوين وتحديث الرصيد
 // ------------------------
 getQuantityColor(quantity: number): string {
  if (quantity === 0) return '#ffcccc';
  if (quantity < 10) return '#ffe5b4';
  return '#ccffcc';
 }

 updateQuantity(item: any, event: Event) {
  const input = event.target as HTMLInputElement;
  if (!input) return;

  const value = Number(input.value);
  if (isNaN(value) || value < 0) return;

  this.category.updateQuantity(item.id, value).subscribe((res: any) => {
   if (res.success) {
    item.quantity = res.quantity;
   } else {
    Swal.fire({ icon: 'error', title: 'فشل تحديث الرصيد' });
    this.search();
   }
  });
 }

 onQuantityFilterChange() {
  this.search();
  if (!this.selectedQuantityFilter) return;

  this.categories = this.categories.filter(item => {
   const qty = item.quantity;
   switch (this.selectedQuantityFilter) {
    case '0': return qty <= 0;
    case '10': return qty > 0 && qty <= 10;
    case 'more': return qty > 10;
    default: return true;
   }
  });
 }

 // ------------------------
 // Inline Edit كود الصنف
 // ------------------------
 startEditCode(item: any) {
  this.editingCodeId = item.id;
  this.newCategoryCode = item.category_code;
 }

 saveCategoryCode(item: any) {
  if (!this.newCategoryCode || this.newCategoryCode === item.category_code) {
   this.editingCodeId = null;
   return;
  }

  this.category.updateCategoryCode(item.id, this.newCategoryCode).subscribe({
   next: (res: any) => {
    item.category_code = this.newCategoryCode;
    this.editingCodeId = null;
   },
   error: (err) => {
    console.error(err);
    this.editingCodeId = null;
   }
  });
 }

}
