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
  categories: any;
  allCategories: any = [];
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

  // **التعريف الصحيح للفلتر**
  selectedQuantityFilter: string = '';

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

  onQuantityFilterChange() {
    this.applyFilters();
  }

  getcategories(itemsperpage: number = this.pageSize, page: number = this.page + 1) {
    this.category.getCategories(itemsperpage, page).subscribe((data: any) => {
      this.allCategories = data.data;
      this.applyFilters();
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
    this.productionline = event.target.value.toString();
    this.search();
  }

  onWarehousechange(event: any) {
    this.warehouse = event.target.value.toString();
    this.search();
  }

  onCategorychange(event: any) {
    this.category_name = event.target.value.toString();
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
    if (!this.allCategories) return;

    this.categories = this.allCategories.filter(item => {
      if (!this.selectedQuantityFilter) return true;

      const qty = item.quantity;
      if (this.selectedQuantityFilter === '0') return qty <= 0;
      if (this.selectedQuantityFilter === '10') return qty > 0 && qty <= 10;
      if (this.selectedQuantityFilter === 'more') return qty > 10;

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
    this.selectedQuantityFilter = '';

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

getQuantityColor(quantity: number): string {
  if (quantity <= 0) return '#ffcccc'; // أحمر للصفر أو أقل
  if (quantity < 10) return '#ffe5b4'; // أصفر للكميات الصغيرة
  return '#ccffcc'; // أخضر للكميات الكبيرة
}


  updateQuantity(item: any, event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input) return;

    let value = Number(input.value);
    if (isNaN(value) || value < 0) return;

    if (value === 0) value = 1;
    input.value = value.toString();

    this.category.updateQuantity(item.id, value).subscribe((res: any) => {
      if (res.success) {
        item.quantity = res.quantity;
      } else {
        Swal.fire({ icon: 'error', title: 'فشل تحديث الرصيد' });
        this.search();
      }
    });
  }
}
