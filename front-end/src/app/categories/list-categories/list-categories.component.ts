import { Component, OnDestroy, OnInit, ViewChild } from '@angular/core';
import { MatPaginator } from '@angular/material/paginator';
import { CategoryService } from '../services/category.service';
import { ProductionService } from '../services/production.service';
import { NgForm } from '@angular/forms';
import { AuthService } from 'src/app/auth/auth.service';
import Swal from 'sweetalert2';
import { environment } from 'src/env/env';
import { ExcelService } from 'src/app/excel.service';
import { Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';

@Component({
 selector: 'app-list-categories',
 templateUrl: './list-categories.component.html',
 styleUrls: ['./list-categories.component.css'],
})
export class ListCategoriesComponent implements OnInit, OnDestroy {
 private categoryNameInput$ = new Subject<string>();
 private categoryNameSub = this.categoryNameInput$
  .pipe(debounceTime(350), distinctUntilChanged())
  .subscribe(() => this.search());
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
 syncingInventoryGl = false;

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

 ngOnDestroy() {
  this.categoryNameSub.unsubscribe();
 }

 private escHtml(s: string): string {
  return String(s ?? '')
   .replace(/&/g, '&amp;')
   .replace(/</g, '&lt;')
   .replace(/>/g, '&gt;');
 }

 private formatMoney(n: number): string {
  const x = Number(n ?? 0);
  if (Number.isNaN(x)) {
   return '0.00';
  }
  return x.toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
 }

 /** مطابقة تكلفة الأصناف مع حسابات المخزون في الشجرة + قيد يومية. */
 openInventoryGlSync(): void {
  const allowed =
   this.user === 'Admin' ||
   this.user === 'Account Management' ||
   this.user === 'Financial Accounts';
  if (!allowed) {
   return;
  }

  this.syncingInventoryGl = true;
  this.category.previewInventoryGlSync().subscribe({
   next: (preview: any) => {
    this.syncingInventoryGl = false;
    const rows: any[] = preview?.adjustments ?? [];
    const willPost = !!preview?.will_post;

    let table =
     '<div dir="rtl" style="max-height:320px;overflow:auto;text-align:right;font-size:13px;">';
    if (!willPost || rows.length === 0) {
     table +=
      '<p class="mb-2">لا توجد فروقات تتطلب ترحيلاً؛ أرصدة حسابات المخزون متسقة مع مجموع <code>total_price</code> للأصناف (مع تصغير الفروق الأقل من 0.02).</p>';
    } else {
     table +=
      '<table class="table table-sm table-bordered mb-0"><thead><tr>' +
      '<th>الحساب</th><th>الكود</th><th>تكلفة الأصناف</th><th>رصيد القيود</th><th>فرق التسوية</th>' +
      '</tr></thead><tbody>';
     for (const r of rows) {
      const nm = this.escHtml(String(r.account_name ?? ''));
      const adj = Number(r.adjustment ?? 0);
      const adjCls = adj >= 0 ? 'text-success' : 'text-danger';
      table += `<tr><td>${nm}</td><td>${this.escHtml(String(r.account_code ?? ''))}</td>` +
       `<td>${this.formatMoney(Number(r.target_cost_from_items ?? 0))}</td>` +
       `<td>${this.formatMoney(Number(r.book_balance_from_entries ?? 0))}</td>` +
       `<td class="${adjCls}">${this.formatMoney(adj)}</td></tr>`;
     }
     table += '</tbody></table>';
    }
    table += '</div>';

    Swal.fire({
     title: 'تسوية المخزون في الحسابات',
     html:
      '<p class="text-muted small mb-2">يُحسب مجموع تكلفة الأصناف لكل حساب مخزون فرعي ويُقارن برصيد القيود المحوسَب، ثم يُنشأ <strong>قيد يومية واحد</strong> مع طرف مقابل فروقات الجرد.</p>' +
      table,
     width: '720px',
     showCancelButton: willPost && rows.length > 0,
     confirmButtonText: willPost && rows.length > 0 ? 'ترحيل القيد' : 'حسناً',
     cancelButtonText: 'إلغاء',
    }).then((res: { isConfirmed: boolean }) => {
     if (!res.isConfirmed || !willPost || rows.length === 0) {
      return;
     }
     this.syncingInventoryGl = true;
     this.category.postInventoryGlSync().subscribe({
      next: (out: any) => {
       this.syncingInventoryGl = false;
       if (!out?.success) {
        Swal.fire({
         icon: 'error',
         title: 'لم يتم الترحيل',
         text: String(out?.message ?? ''),
        });
        return;
       }
       if (!out?.posted) {
        Swal.fire({ icon: 'info', title: out?.message ?? 'لا يوجد ما يُرحَّل' });
        return;
       }

       const jl: any[] = out?.journal_lines ?? [];
       let jt =
        '<div dir="rtl" style="max-height:280px;overflow:auto;font-size:13px;">' +
        `<p class="mb-2"><strong>رقم القيد:</strong> ${this.escHtml(String(out.entry_number ?? ''))} ` +
        `(معرّف ${this.escHtml(String(out.daily_entry_id ?? ''))})</p>` +
        '<table class="table table-sm table-bordered mb-0"><thead><tr>' +
        '<th>الحساب</th><th>مدين</th><th>دائن</th><th>البيان</th>' +
        '</tr></thead><tbody>';
       for (const ln of jl) {
        const nm = this.escHtml(String(ln.account_name ?? ln.account_code ?? ''));
        jt += `<tr><td>${nm}</td><td>${this.formatMoney(Number(ln.debit ?? 0))}</td>` +
         `<td>${this.formatMoney(Number(ln.credit ?? 0))}</td>` +
         `<td>${this.escHtml(String(ln.note ?? ''))}</td></tr>`;
       }
       jt += '</tbody></table></div>';

       Swal.fire({
        icon: 'success',
        title: 'تم إنشاء القيد وتحديث الشجرة',
        html: jt,
        width: '720px',
       });
      },
      error: (err: any) => {
       this.syncingInventoryGl = false;
       const msg =
        err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر ترحيل القيد';
       Swal.fire({ icon: 'error', title: 'فشل الترحيل', text: String(msg) });
      },
     });
    });
   },
   error: (err: any) => {
    this.syncingInventoryGl = false;
    const msg =
     err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر جلب المعاينة';
    Swal.fire({ icon: 'error', title: 'المعاينة', text: String(msg) });
   },
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

 onCategoryNameInput(value: string) {
  this.category_name = value ?? '';
  this.categoryNameInput$.next(this.category_name.trim());
 }

 search() {
  this.param = {};
  if (this.productionline) this.param['production_id'] = this.productionline;
  if (this.warehouse) this.param['warehouse'] = this.warehouse;
  const name = (this.category_name ?? '').trim();
  if (name) this.param['category_name'] = name;

  this.category.searchCategories(this.pageSize, this.page + 1, this.param).subscribe((data: any) => {
   this.allCategories = data.data;
   this.applyFilters();
   this.length = data.total;
   this.pageSize = data.per_page;
  });
 }

 applyFilters() {
  const nameFilter = (this.category_name ?? '').trim().toLowerCase();
  this.categories = this.allCategories.filter(item => {
   if (this.selectedQuantityFilter) {
    const qty = item.quantity;
    switch (this.selectedQuantityFilter) {
     case '0': if (qty > 0) return false; break;
     case '10': if (qty <= 0 || qty > 10) return false; break;
     case 'more': if (qty <= 10) return false; break;
    }
   }
   if (nameFilter) {
    const cn = (item.category_name ?? '').toLowerCase();
    const ic = (item.item_code ?? '').toLowerCase();
    if (!cn.includes(nameFilter) && !ic.includes(nameFilter)) return false;
   }
   if (this.warehouse && item.warehouse != this.warehouse) return false;
   if (this.productionline && String(item.production_id ?? '') !== String(this.productionline)) return false;
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

 promptAdjustAverageUnitCost(item: {
  id: number;
  quantity?: number;
  category_name?: string;
 }) {
  const qty = Number(item?.quantity ?? 0);
  const currentAvg = this.averageUnitCost(item);
  Swal.fire({
   title: 'تعديل متوسط تكلفة الوحدة',
   html:
    qty > 0.0000001
     ? `<p class="text-muted small mb-0">الصنف: ${item.category_name ?? ''} — الرصيد الحالي: ${qty}</p>`
     : `<p class="text-muted small mb-0">لا توجد كمية راصدة؛ سيتم حفظ التكلفة كمرجع للوحدة للحركات القادمة.</p>`,
   input: 'number',
   inputValue: currentAvg > 0 ? String(currentAvg) : '',
   inputAttributes: { step: 'any', min: '0' },
   showCancelButton: true,
   confirmButtonText: 'حفظ',
   cancelButtonText: 'إلغاء',
   inputValidator: (value: string) => {
    if (value === '' || value === null) {
     return 'أدخل متوسط التكلفة';
    }
    const n = Number(value);
    if (Number.isNaN(n) || n < 0) {
     return 'قيمة غير صالحة';
    }
    return null;
   },
  }).then((result: { isConfirmed: boolean; value?: string }) => {
   if (!result.isConfirmed) {
    return;
   }
   const val = Number(result.value);
   this.category.updateAverageUnitCost(item.id, val).subscribe({
    next: (res: any) => {
     if (res?.success) {
      item.quantity = res.quantity;
      if (res.total_price !== undefined && res.total_price !== null) {
       (item as any).total_price = res.total_price;
      }
      if (res.sell_total_price !== undefined && res.sell_total_price !== null) {
       (item as any).sell_total_price = res.sell_total_price;
      }
      if (res.unit_price !== undefined && res.unit_price !== null) {
       (item as any).unit_price = res.unit_price;
      }
      Swal.fire({ icon: 'success', timer: 2500, showConfirmButton: false });
     } else {
      Swal.fire({ icon: 'error', title: res?.message ?? 'فشل التحديث' });
     }
    },
    error: (err: any) => {
     const msg =
      err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر تحديث متوسط التكلفة';
     Swal.fire({ icon: 'error', title: 'فشل التحديث', text: String(msg) });
    },
   });
  });
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
    this.category.deleteCategory(id).subscribe({
     next: () => {
      this.search();
      Swal.fire({ icon: 'success', timer: 3000, showConfirmButton: false });
     },
     error: (err) => {
      const msg =
       err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر تنفيذ الحذف';
      Swal.fire({ icon: 'error', title: 'فشل الحذف', text: String(msg) });
     },
    });
   }
  });
 }

 // ------------------------
 // دوال لتلوين وتحديث الرصيد
 // ------------------------
 /**
  * متوسط تكلفة الوحدة (مرجّح): قيمة المخزون بالتكلفة ÷ الكمية (total_price) لكل المخازن.
  */
 averageUnitCost(item: {
  quantity?: number;
  total_price?: number;
  sell_total_price?: number;
  unit_price?: number;
  warehouse?: string;
 }): number {
  const q = Number(item?.quantity ?? 0);
  const tp = Number(item?.total_price ?? 0);
  const up = Number(item?.unit_price ?? 0);
  if (q > 0.0000001) {
   return tp / q;
  }
  if (up > 0.0000001) {
   return up;
  }
  return 0;
 }

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
    if (res.total_price !== undefined && res.total_price !== null) {
     item.total_price = res.total_price;
    }
    if (res.sell_total_price !== undefined && res.sell_total_price !== null) {
     item.sell_total_price = res.sell_total_price;
    }
    if (res.unit_price !== undefined && res.unit_price !== null) {
     item.unit_price = res.unit_price;
    }
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

}
