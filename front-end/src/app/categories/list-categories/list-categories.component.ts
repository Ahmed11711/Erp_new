import { Component, OnDestroy, OnInit, ViewChild } from '@angular/core';
import { MatPaginator } from '@angular/material/paginator';
import { CategoryService } from '../services/category.service';
import { ProductionService } from '../services/production.service';
import { NgForm } from '@angular/forms';
import { AuthService } from 'src/app/auth/auth.service';
import Swal from 'sweetalert2';
import { environment } from 'src/env/env';
import { ExcelService } from 'src/app/excel.service';
import { Subject, firstValueFrom } from 'rxjs';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { canShowCategoryRowActions } from 'src/app/shared/utils/category-warehouse-access';

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
 selectedItems = new Map<number, any>();
 exportingExcel = false;

 @ViewChild(MatPaginator, { static: true }) paginator!: MatPaginator;
 @ViewChild('listcat', { static: false }) listcat!: NgForm;

 constructor(
  private category: CategoryService,
  private production: ProductionService,
  private authService: AuthService,
  private excelService: ExcelService,
  public rbac: RbacService
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
  if (this.user !== 'Admin') {
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
  this.exportItemsToExcel();
 }

 exportItemsToExcel(): void {
  if (this.exportingExcel) {
   return;
  }

  this.exportingExcel = true;
  this.fetchAllFilteredCategories()
   .then((rows) => {
    const filtered = rows.filter((item) => this.matchesDisplayFilters(item));
    if (filtered.length === 0) {
     Swal.fire({ icon: 'info', title: 'لا توجد أصناف للتصدير', text: 'غيّر الفلاتر أو اختر مخزناً.' });
     return;
    }

    const data = filtered.map((item, index) => ({
     '#': index + 1,
     'اسم الصنف': item.category_name ?? '',
     'كود الصنف': item.item_code ?? '',
     'اللون': item.color ?? '',
     'المخزن': item.warehouse ?? '',
     'فرع الانتاج': item.production?.production_line ?? '',
     'الوحدة': item.measurement?.unit ?? '',
     'التكلفة او سعر البيع': Number(item.category_price ?? 0),
     'متوسط التكلفة': this.averageUnitCost(item),
     'الرصيد': Number(item.quantity ?? 0),
     'المرجع': item.ref ?? '',
     'الحد الأدنى': item.minimum_quantity ?? '',
    }));

    this.excelService.exportJsonToExcel(data, this.buildExportFileName(), true);
   })
   .catch((err: unknown) => {
    const msg =
     (err as { error?: { message?: string }; message?: string })?.error?.message ??
     (err as { message?: string })?.message ??
     'تعذّر تصدير الأصناف';
    Swal.fire({ icon: 'error', title: 'فشل التصدير', text: String(msg) });
   })
   .finally(() => {
    this.exportingExcel = false;
   });
 }

 private async fetchAllFilteredCategories(): Promise<any[]> {
  const pageSize = 5000;
  let page = 1;
  let all: any[] = [];
  let total = 0;

  do {
   const data: any = await firstValueFrom(
    this.category.searchCategories(pageSize, page, this.param),
   );
   const chunk = data?.data ?? [];
   all = all.concat(chunk);
   total = Number(data?.total ?? all.length);
   if (chunk.length < pageSize) {
    break;
   }
   page++;
  } while (all.length < total);

  return all;
 }

 private buildExportFileName(): string {
  const parts = ['الأصناف'];
  if (this.warehouse) {
   parts.push(this.warehouse);
  }
  if (this.productionline) {
   const prod = (this.productionData ?? []).find(
    (p: { id: number; production_line?: string }) =>
     String(p.id) === String(this.productionline),
   );
   if (prod?.production_line) {
    parts.push(String(prod.production_line));
   }
  }
  const date = new Date().toISOString().slice(0, 10);
  return `${parts.join(' - ')} (${date})`;
 }

 private matchesDisplayFilters(item: any): boolean {
  if (this.selectedQuantityFilter) {
   const qty = item.quantity;
   switch (this.selectedQuantityFilter) {
    case '0':
     if (qty > 0) return false;
     break;
    case '10':
     if (qty <= 0 || qty > 10) return false;
     break;
    case 'more':
     if (qty <= 10) return false;
     break;
   }
  }

  const nameFilter = (this.category_name ?? '').trim().toLowerCase();
  if (nameFilter) {
   const cn = (item.category_name ?? '').toLowerCase();
   const ic = (item.item_code ?? '').toLowerCase();
   if (!cn.includes(nameFilter) && !ic.includes(nameFilter)) {
    return false;
   }
  }

  if (this.warehouse && item.warehouse != this.warehouse) {
   return false;
  }
  if (this.productionline && String(item.production_id ?? '') !== String(this.productionline)) {
   return false;
  }

  return true;
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
  this.categories = this.allCategories.filter((item) => this.matchesDisplayFilters(item));
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
      const details: string[] = Array.isArray(err?.error?.details) ? err.error.details : [];
      const detailsHtml =
       details.length > 0
        ? `<ul dir="rtl" style="text-align:right;margin-top:12px;padding-right:20px;">${details
           .map((d) => `<li>${this.escHtml(String(d))}</li>`)
           .join('')}</ul>`
        : '';
      Swal.fire({
       icon: 'error',
       title: 'فشل الحذف',
       html:
        `<p>${this.escHtml(String(msg))}</p>` +
        detailsHtml +
        (details.length > 0
         ? '<p class="text-muted small mt-2 mb-0">يمكنك استخدام «حذف قسري» (Admin) أو دمج الصنف في صنف آخر.</p>'
         : ''),
      });
     },
    });
   }
  });
 }

 /** حذف قسري لصنف واحد — Admin فقط. */
 forceDeleteSingle(item: { id: number; category_name?: string }) {
  if (this.user !== 'Admin') {
   return;
  }
  this.runForceDeleteFlow({
   categoryIds: [Number(item.id)],
   title: `حذف قسري: ${item.category_name ?? ''}`,
   confirmPhrase: 'حذف نهائي',
  });
 }

 /** حذف قسري للأصناف المحددة — Admin فقط. */
 forceDeleteSelected() {
  if (this.user !== 'Admin') {
   return;
  }
  const items = Array.from(this.selectedItems.values());
  if (items.length === 0) {
   Swal.fire({ icon: 'info', title: 'لم يتم تحديد أصناف' });
   return;
  }
  this.runForceDeleteFlow({
   categoryIds: items.map((it) => Number(it.id)),
   title: `حذف قسري لـ ${items.length} صنف`,
   confirmPhrase: 'حذف نهائي',
  });
 }

 /** حذف كل أصناف المخزن المصفّى — Admin فقط. */
 forceDeleteWarehouse() {
  if (this.user !== 'Admin') {
   return;
  }
  const warehouse = String(this.warehouse || this.ware || '').trim();
  if (!warehouse) {
   Swal.fire({
    icon: 'info',
    title: 'اختر مخزناً أولاً',
    text: 'صفِّ الجدول حسب المخزن ثم أعد المحاولة.',
   });
   return;
  }
  this.runForceDeleteFlow({
   warehouse,
   title: `حذف كل أصناف: ${warehouse}`,
   confirmPhrase: warehouse,
  });
 }

 private runForceDeleteFlow(opts: {
  categoryIds?: number[];
  warehouse?: string;
  title: string;
  confirmPhrase: string;
 }) {
  Swal.fire({
   title: 'جاري تحليل التأثير…',
   allowOutsideClick: false,
   didOpen: () => Swal.showLoading(),
  });

  const payload: { category_ids?: number[]; warehouse?: string } = {};
  if (opts.warehouse) {
   payload.warehouse = opts.warehouse;
  } else if (opts.categoryIds?.length) {
   payload.category_ids = opts.categoryIds;
  }

  this.category.previewForceDeleteCategories(payload).subscribe({
   next: (preview: any) => {
    Swal.close();
    const count = Number(preview?.categories ?? 0);
    if (count === 0) {
     Swal.fire({ icon: 'info', title: 'لا توجد أصناف للحذف' });
     return;
    }

    const warnings: string[] = Array.isArray(preview?.warnings) ? preview.warnings : [];
    const links: Record<string, number> = preview?.links ?? {};
    const linkLines = Object.entries(links)
     .map(([k, v]) => `<li>${this.escHtml(k)}: ${v}</li>`)
     .join('');

    Swal.fire({
     title: opts.title,
     html:
      `<div dir="rtl" style="text-align:right;font-size:13px;">` +
      `<p class="mb-2"><strong>عدد الأصناف:</strong> ${count} — <strong>إجمالي الارتباطات:</strong> ${Number(preview?.total_links ?? 0)}</p>` +
      `<p class="text-danger small mb-2"><strong>تحذير:</strong> هذا حذف نهائي حتى لو كانت الأصناف مرتبطة بعمليات في النظام.</p>` +
      (warnings.length
       ? `<p class="mb-1"><strong>التأثير المتوقع:</strong></p><ul style="padding-right:20px;">${warnings
          .map((w) => `<li>${this.escHtml(w)}</li>`)
          .join('')}</ul>`
       : '') +
      (linkLines
       ? `<p class="mb-1 mt-2"><strong>تفاصيل الارتباطات:</strong></p><ul style="padding-right:20px;max-height:160px;overflow:auto;">${linkLines}</ul>`
       : '') +
      `<p class="mt-3 mb-1">للتأكيد اكتب: <strong>${this.escHtml(opts.confirmPhrase)}</strong></p>` +
      `</div>`,
     input: 'text',
     inputPlaceholder: opts.confirmPhrase,
     icon: 'warning',
     showCancelButton: true,
     confirmButtonText: 'حذف نهائياً',
     cancelButtonText: 'إلغاء',
     confirmButtonColor: '#d33',
     width: '720px',
     inputValidator: (value: string) =>
      value?.trim() === opts.confirmPhrase ? null : `اكتب «${opts.confirmPhrase}» بالضبط`,
    }).then((res: { isConfirmed: boolean; value?: string }) => {
     if (!res.isConfirmed) {
      return;
     }
     Swal.fire({
      title: 'جاري الحذف…',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
     });
     this.category
      .forceDeleteCategories({
       ...payload,
       confirm_phrase: res.value?.trim() ?? opts.confirmPhrase,
      })
      .subscribe({
       next: (out: any) => {
        this.clearSelection();
        this.search();
        Swal.fire({
         icon: 'success',
         title: out?.message ?? 'تم الحذف',
         text: `تم حذف ${out?.result?.categories_deleted ?? count} صنفاً.`,
         timer: 3500,
         showConfirmButton: false,
        });
       },
       error: (err) => {
        const msg = err?.error?.message ?? err?.message ?? 'تعذر تنفيذ الحذف';
        Swal.fire({ icon: 'error', title: 'فشل الحذف', text: String(msg) });
       },
      });
    });
   },
   error: (err) => {
    const msg = err?.error?.message ?? err?.message ?? 'تعذر جلب المعاينة';
    Swal.fire({ icon: 'error', title: 'المعاينة', text: String(msg) });
   },
  });
 }

 mergeIntoAnother(source: {
  id: number;
  category_name?: string;
  warehouse?: string;
  stock_id?: number;
  quantity?: number;
  item_code?: string;
 }) {
  const warehouse = String(source.warehouse ?? '').trim();
  const load$ =
   warehouse !== ''
    ? this.category.getCatBywarehouse(warehouse)
    : this.category.allCategories();

  load$.subscribe({
   next: (rows: any) => {
    const list: any[] = Array.isArray(rows) ? rows : rows?.data ?? [];
    this.openMergeDialog(source, list);
   },
   error: () => {
    Swal.fire({
     icon: 'error',
     title: 'تعذر تحميل الأصناف',
     text: 'حاول مرة أخرى أو صفِّ حسب المخزن أولاً.',
    });
   },
  });
 }

 // ------------------------
 // تحديد متعدد + دمج يدوي
 // ------------------------
 get selectedCount(): number {
  return this.selectedItems.size;
 }

 isSelected(item: { id: number }): boolean {
  return this.selectedItems.has(Number(item?.id));
 }

 toggleSelection(item: any, event: Event): void {
  const checked = (event.target as HTMLInputElement)?.checked;
  const id = Number(item?.id);
  if (checked) {
   this.selectedItems.set(id, item);
  } else {
   this.selectedItems.delete(id);
  }
 }

 get allDisplayedSelected(): boolean {
  const selectable = (this.categories ?? []).filter((it: any) => this.canShowRowActions(it));
  return selectable.length > 0 && selectable.every((it: any) => this.selectedItems.has(Number(it.id)));
 }

 toggleSelectAll(event: Event): void {
  const checked = (event.target as HTMLInputElement)?.checked;
  const selectable = (this.categories ?? []).filter((it: any) => this.canShowRowActions(it));
  for (const it of selectable) {
   if (checked) {
    this.selectedItems.set(Number(it.id), it);
   } else {
    this.selectedItems.delete(Number(it.id));
   }
  }
 }

 clearSelection(): void {
  this.selectedItems.clear();
 }

 /**
  * يبني قائمة أسماء قابلة للبحث والنقر (موثوقة في كل المتصفحات) لاستخدامها داخل نوافذ SweetAlert.
  * تُرجع html لإدراجه، ودالة wire() تُستدعى في didOpen، ودالة getValue() تُستدعى في preConfirm.
  */
 private buildSearchablePicker(
  options: { value: number | string; label: string }[],
  selected?: number | string
 ): { html: string; wire: () => void; getValue: () => string } {
  const id = 'sp_' + Math.random().toString(36).slice(2);
  const selectedStr = selected !== undefined && selected !== null ? String(selected) : '';

  const itemsHtml = options
   .map((o) => {
    const isSel = String(o.value) === selectedStr;
    return (
     `<div class="sp-item" data-value="${o.value}" data-label="${this.escHtml(o.label).toLowerCase()}" ` +
     `style="padding:7px 10px;cursor:pointer;border-bottom:1px solid #eee;${
      isSel ? 'background:#d4edda;' : ''
     }">${this.escHtml(o.label)}</div>`
    );
   })
   .join('');

  const html =
   `<input id="${id}_search" class="swal2-input" autocomplete="off" placeholder="ابحث بالاسم أو الكود..." style="margin-bottom:8px;">` +
   `<input type="hidden" id="${id}_value" value="${selectedStr}">` +
   `<div id="${id}_list" dir="rtl" style="max-height:260px;overflow:auto;border:1px solid #ddd;border-radius:6px;text-align:right;font-size:13px;">${itemsHtml}</div>`;

  const wire = () => {
   const search = document.getElementById(`${id}_search`) as HTMLInputElement | null;
   const list = document.getElementById(`${id}_list`) as HTMLElement | null;
   const valueInput = document.getElementById(`${id}_value`) as HTMLInputElement | null;
   if (!search || !list || !valueInput) {
    return;
   }
   const items = Array.from(list.querySelectorAll('.sp-item')) as HTMLElement[];
   items.forEach((it) => {
    it.addEventListener('click', () => {
     valueInput.value = it.getAttribute('data-value') ?? '';
     items.forEach((x) => (x.style.background = ''));
     it.style.background = '#d4edda';
    });
   });
   search.addEventListener('input', () => {
    const q = search.value.trim().toLowerCase();
    items.forEach((it) => {
     const label = it.getAttribute('data-label') ?? '';
     it.style.display = !q || label.includes(q) ? '' : 'none';
    });
   });
  };

  const getValue = () =>
   (document.getElementById(`${id}_value`) as HTMLInputElement | null)?.value ?? '';

  return { html, wire, getValue };
 }

 /**
  * دمج الأصناف المحددة يدوياً في صنف واحد محتفظ به،
  * مع نقل كل الارتباطات/الحركات/الأرصدة واستبدالها في كل العمليات.
  */
 mergeSelected(): void {
  const items = Array.from(this.selectedItems.values());
  if (items.length < 2) {
   Swal.fire({ icon: 'info', title: 'حدّد صنفين على الأقل', text: 'اختر أكثر من صنف لدمجهم في صنف واحد.' });
   return;
  }

  const warehouses = Array.from(new Set(items.map((it) => String(it.warehouse ?? '').trim())));
  if (warehouses.length > 1) {
   Swal.fire({
    icon: 'error',
    title: 'يجب أن تكون الأصناف في نفس المخزن',
    html:
     `<p dir="rtl" style="text-align:right;">لا يمكن دمج أصناف من مخازن مختلفة. المخازن المحددة:</p>` +
     `<ul dir="rtl" style="text-align:right;padding-right:20px;">${warehouses
      .map((w) => `<li>${this.escHtml(w || '—')}</li>`)
      .join('')}</ul>`,
   });
   return;
  }

  const pickerOptions = items
   .slice()
   .sort((a, b) => Number(b.quantity ?? 0) - Number(a.quantity ?? 0))
   .map((row) => ({
    value: Number(row.id),
    label:
     `#${row.id} — ${row.category_name ?? ''}` +
     (row.item_code ? ` (${row.item_code})` : '') +
     ` — رصيد ${Number(row.quantity ?? 0)}`,
   }));
  const picker = this.buildSearchablePicker(pickerOptions);

  Swal.fire({
   title: `دمج ${items.length} أصناف في صنف واحد`,
   html:
    `<p dir="rtl" style="text-align:right;">اختر الصنف الذي تريد الاحتفاظ به؛ سيُدمج باقي الأصناف المحددة فيه ويُستبدل بها في كل العمليات (الطلبات، التصنيع، الوصفات، حركات المخزون…) ثم تُحذف.</p>` +
    picker.html,
   width: '660px',
   showCancelButton: true,
   confirmButtonText: 'معاينة ثم دمج',
   cancelButtonText: 'إلغاء',
   didOpen: () => picker.wire(),
   preConfirm: () => {
    const value = picker.getValue();
    if (!value) {
     Swal.showValidationMessage('اختر الصنف المحتفظ به');
     return false;
    }
    return Number(value);
   },
  }).then((pick: { isConfirmed: boolean; value?: number }) => {
   if (!pick.isConfirmed || !pick.value) {
    return;
   }
   const targetId = Number(pick.value);
   const sources = items.filter((it) => Number(it.id) !== targetId);
   const sourceIds = sources.map((it) => Number(it.id));
   const targetItem = items.find((it) => Number(it.id) === targetId);

   Swal.fire({
    title: 'تأكيد الدمج',
    html:
     `<div dir="rtl" style="text-align:right;">` +
     `<p>سيتم الاحتفاظ بالصنف: <strong>${this.escHtml(String(targetItem?.category_name ?? ''))}</strong> (#${targetId})</p>` +
     `<p class="mb-1">وستُدمج فيه وتُحذف الأصناف التالية:</p>` +
     `<ul style="padding-right:20px;max-height:200px;overflow:auto;">${sources
      .map(
       (s) =>
        `<li>#${s.id} — ${this.escHtml(String(s.category_name ?? ''))}${
         s.item_code ? ' (' + this.escHtml(String(s.item_code)) + ')' : ''
        }</li>`
      )
      .join('')}</ul>` +
     `</div>`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'دمج الآن',
    cancelButtonText: 'إلغاء',
   }).then((confirm: { isConfirmed: boolean }) => {
    if (!confirm.isConfirmed) {
     return;
    }
    Swal.fire({
     title: 'جاري الدمج…',
     allowOutsideClick: false,
     didOpen: () => Swal.showLoading(),
    });
    this.category.mergeCategoriesBulk(targetId, sourceIds).subscribe({
     next: () => {
      this.clearSelection();
      this.search();
      Swal.fire({ icon: 'success', title: 'تم الدمج بنجاح', timer: 2500, showConfirmButton: false });
     },
     error: (err) => {
      const msg = err?.error?.message ?? err?.message ?? 'تعذر تنفيذ الدمج';
      Swal.fire({ icon: 'error', title: 'فشل الدمج', text: String(msg) });
     },
    });
   });
  });
 }

 /**
  * أداة معالجة كل الأصناف المكررة على مستوى السيستم:
  * يكتشف المجموعات المكررة (نفس الاسم داخل نفس المخزن) ويتيح دمجها دفعة واحدة
  * أو مراجعتها مجموعة بمجموعة.
  */
 async openDuplicateManager(): Promise<void> {
  if (this.user !== 'Admin') {
   return;
  }

  Swal.fire({
   title: 'جاري البحث عن المكررات…',
   allowOutsideClick: false,
   didOpen: () => Swal.showLoading(),
  });

  let groups: any[] = [];
  try {
   const res: any = await firstValueFrom(this.category.duplicateCategoryGroups());
   groups = Array.isArray(res?.groups) ? res.groups : [];
  } catch {
   Swal.fire({ icon: 'error', title: 'تعذر تحميل المكررات', text: 'حاول مرة أخرى.' });
   return;
  }

  if (groups.length === 0) {
   Swal.fire({
    icon: 'info',
    title: 'لا توجد أصناف مكررة',
    text: 'لم يُعثر على أصناف بنفس الاسم داخل نفس المخزن.',
   });
   return;
  }

  const totalDuplicates = groups.reduce((sum, g) => sum + (g.members.length - 1), 0);

  let rowsHtml = '';
  for (const g of groups) {
   const canonical = g.members.find((m: any) => m.id === g.canonical_id) ?? g.members[0];
   const dups = g.members.filter((m: any) => m.id !== g.canonical_id);
   rowsHtml +=
    `<tr>` +
    `<td style="text-align:right;">${this.escHtml(String(g.name ?? ''))}</td>` +
    `<td>${this.escHtml(String(g.warehouse ?? ''))}</td>` +
    `<td>#${canonical.id}${canonical.item_code ? ' (' + this.escHtml(String(canonical.item_code)) + ')' : ''}</td>` +
    `<td>${dups.map((d: any) => '#' + d.id).join('، ')}</td>` +
    `</tr>`;
  }

  const table =
   `<div dir="rtl" style="max-height:340px;overflow:auto;text-align:right;font-size:13px;">` +
   `<table class="table table-sm table-bordered mb-0"><thead><tr>` +
   `<th>الصنف</th><th>المخزن</th><th>المحتفظ به</th><th>سيُدمج ويُحذف</th>` +
   `</tr></thead><tbody>${rowsHtml}</tbody></table></div>`;

  const choice = await Swal.fire({
   title: `عدد المجموعات المكررة: ${groups.length}`,
   html:
    `<p class="text-muted small mb-2">سيتم دمج <strong>${totalDuplicates}</strong> صنفًا مكررًا في الأصناف المحتفظ بها (نفس الاسم + نفس المخزن). تُنقل كل الأرصدة والحركات والارتباطات إلى الصنف المحتفظ به قبل حذف المكرر.</p>` +
    table,
   width: '780px',
   showCancelButton: true,
   showDenyButton: true,
   confirmButtonText: 'دمج الكل تلقائيًا',
   denyButtonText: 'مراجعة مجموعة بمجموعة',
   cancelButtonText: 'إلغاء',
  });

  if (choice.isConfirmed) {
   await this.autoMergeAllGroups(groups);
  } else if (choice.isDenied) {
   await this.reviewDuplicateGroups(groups);
  }
 }

 private async autoMergeAllGroups(groups: any[]): Promise<void> {
  Swal.fire({
   title: 'جاري الدمج…',
   html: 'يرجى الانتظار حتى تكتمل معالجة كل المجموعات.',
   allowOutsideClick: false,
   didOpen: () => Swal.showLoading(),
  });

  let merged = 0;
  let failed = 0;
  const errors: string[] = [];

  for (const g of groups) {
   const sourceIds = g.members
    .filter((m: any) => m.id !== g.canonical_id)
    .map((m: any) => Number(m.id));
   if (sourceIds.length === 0) {
    continue;
   }
   try {
    await firstValueFrom(this.category.mergeCategoriesBulk(Number(g.canonical_id), sourceIds));
    merged += sourceIds.length;
   } catch (e: any) {
    failed += sourceIds.length;
    const msg = e?.error?.message ?? e?.message ?? 'فشل الدمج';
    errors.push(`${g.name}: ${msg}`);
   }
  }

  this.search();

  const errorsHtml = errors.length
   ? `<ul dir="rtl" style="text-align:right;max-height:180px;overflow:auto;padding-right:20px;">${errors
      .map((e) => `<li>${this.escHtml(e)}</li>`)
      .join('')}</ul>`
   : '';

  Swal.fire({
   icon: failed ? 'warning' : 'success',
   title: failed ? 'اكتمل الدمج مع بعض الأخطاء' : 'تم دمج كل المكررات',
   html:
    `<p>تم دمج <strong>${merged}</strong> صنفًا مكررًا.</p>` +
    (failed ? `<p class="text-danger">فشل دمج <strong>${failed}</strong> صنفًا.</p>${errorsHtml}` : ''),
   width: '600px',
  });
 }

 private async reviewDuplicateGroups(groups: any[]): Promise<void> {
  let merged = 0;

  for (let i = 0; i < groups.length; i++) {
   const g = groups[i];
   const picker = this.buildSearchablePicker(
    g.members.map((m: any) => ({
     value: Number(m.id),
     label:
      `#${m.id} — ${m.category_name ?? ''}` +
      (m.item_code ? ` (${m.item_code})` : '') +
      ` — رصيد ${Number(m.quantity ?? 0)} — ارتباطات ${Number(m.links ?? 0)}`,
    })),
    g.canonical_id
   );

   const res = await Swal.fire({
    title: `المجموعة ${i + 1}/${groups.length}: ${this.escHtml(String(g.name ?? ''))}`,
    html:
     `<p dir="rtl" style="text-align:right;" class="small text-muted">المخزن: ${this.escHtml(
      String(g.warehouse ?? '')
     )} — اختر الصنف الذي تريد الاحتفاظ به، وسيُدمج باقي أصناف المجموعة فيه.</p>` +
     picker.html,
    width: '660px',
    showCancelButton: true,
    showDenyButton: true,
    confirmButtonText: 'دمج هذه المجموعة',
    denyButtonText: 'تخطّي',
    cancelButtonText: 'إيقاف',
    didOpen: () => picker.wire(),
    preConfirm: () => {
     const value = picker.getValue();
     if (!value) {
      Swal.showValidationMessage('اختر الصنف المحتفظ به');
      return false;
     }
     return Number(value);
    },
   });

   if (res.dismiss === Swal.DismissReason.cancel) {
    break;
   }
   if (res.isDenied) {
    continue;
   }
   if (res.isConfirmed && res.value) {
    const targetId = Number(res.value);
    const sourceIds = g.members
     .filter((m: any) => Number(m.id) !== targetId)
     .map((m: any) => Number(m.id));
    if (sourceIds.length === 0) {
     continue;
    }
    try {
     await firstValueFrom(this.category.mergeCategoriesBulk(targetId, sourceIds));
     merged += sourceIds.length;
    } catch (e: any) {
     const msg = e?.error?.message ?? e?.message ?? 'تعذر تنفيذ الدمج';
     await Swal.fire({ icon: 'error', title: 'فشل دمج المجموعة', text: String(msg) });
    }
   }
  }

  this.search();
  Swal.fire({
   icon: 'success',
   title: 'انتهت المراجعة',
   text: `تم دمج ${merged} صنفًا مكررًا.`,
   timer: 3000,
   showConfirmButton: false,
  });
 }

 private openMergeDialog(
  source: { id: number; category_name?: string; warehouse?: string; stock_id?: number },
  rows: any[]
 ) {
  const sameStock = (row: { id: number; stock_id?: number; warehouse?: string }) => {
   if (source.stock_id != null && row.stock_id != null) {
    return Number(row.stock_id) === Number(source.stock_id);
   }
   return String(row.warehouse ?? '') === String(source.warehouse ?? '');
  };

  const candidates = rows
   .filter((row) => row.id !== source.id && sameStock(row))
   .sort((a, b) => Number(b.quantity ?? 0) - Number(a.quantity ?? 0));

  if (candidates.length === 0) {
   Swal.fire({
    icon: 'info',
    title: 'لا توجد أصناف بديلة',
    text: 'لا يوجد صنف آخر في نفس المخزن يمكن الدمج فيه.',
   });
   return;
  }

  const picker = this.buildSearchablePicker(
   candidates.map((row) => ({
    value: Number(row.id),
    label:
     `#${row.id} — ${row.category_name ?? ''}` +
     (row.item_code ? ` (${row.item_code})` : '') +
     ` — رصيد ${Number(row.quantity ?? 0)}`,
   }))
  );

  Swal.fire({
   title: 'دمج صنف مكرر',
   html:
    `<p dir="rtl" style="text-align:right;">سيتم نقل كل ارتباطات الصنف <strong>${this.escHtml(
     String(source.category_name ?? '')
    )}</strong> (#${source.id}) إلى الصنف المستهدف، ثم حذف المكرر.</p>` +
    picker.html,
   showCancelButton: true,
   confirmButtonText: 'معاينة ثم دمج',
   cancelButtonText: 'إلغاء',
   didOpen: () => picker.wire(),
   preConfirm: () => {
    const value = picker.getValue();
    if (!value) {
     Swal.showValidationMessage('اختر الصنف المستهدف');
     return false;
    }
    return value;
   },
  }).then((pick: { isConfirmed: boolean; value?: string }) => {
   if (!pick.isConfirmed || !pick.value) {
    return;
   }
   const targetId = Number(pick.value);
   this.category.previewCategoryMerge(source.id, targetId).subscribe({
    next: (preview: any) => {
     const linkLines = Object.entries(preview?.links ?? {})
      .map(([k, v]) => `<li>${this.escHtml(k)}: ${v}</li>`)
      .join('');
     Swal.fire({
      title: 'تأكيد الدمج',
      html:
       `<div dir="rtl" style="text-align:right;">` +
       `<p>من: <strong>${this.escHtml(preview?.source?.category_name ?? '')}</strong> (#${source.id})</p>` +
       `<p>إلى: <strong>${this.escHtml(preview?.target?.category_name ?? '')}</strong> (#${targetId})</p>` +
       `<p>الرصيد بعد الدمج: <strong>${Number(preview?.merged_quantity ?? 0)}</strong></p>` +
       (linkLines
        ? `<p class="mb-1">سيتم نقل الارتباطات:</p><ul style="padding-right:20px;">${linkLines}</ul>`
        : '<p class="text-muted small">لا توجد ارتباطات مسجّلة على الصنف المكرر.</p>') +
       `</div>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'دمج الآن',
      cancelButtonText: 'إلغاء',
     }).then((confirm: { isConfirmed: boolean }) => {
      if (!confirm.isConfirmed) {
       return;
      }
      this.category.mergeCategory(source.id, targetId).subscribe({
       next: () => {
        this.search();
        Swal.fire({ icon: 'success', title: 'تم الدمج بنجاح', timer: 2500, showConfirmButton: false });
       },
       error: (err) => {
        const msg = err?.error?.message ?? err?.message ?? 'تعذر تنفيذ الدمج';
        Swal.fire({ icon: 'error', title: 'فشل الدمج', text: String(msg) });
       },
      });
     });
    },
    error: (err) => {
     const msg = err?.error?.errors?.[0] ?? err?.error?.message ?? err?.message ?? 'تعذر معاينة الدمج';
     Swal.fire({ icon: 'error', title: 'لا يمكن الدمج', text: String(msg) });
    },
   });
  });
 }

 promoteToFinished(item: { id: number; category_name?: string; warehouse?: string; quantity?: number }) {
  const qty = Number(item?.quantity ?? 0);
  Swal.fire({
   title: 'ترقية لمنتج تام',
   html:
    `<p>هل تريد ترقية الصنف <strong>${item.category_name ?? ''}</strong> من تحت التشغيل إلى منتج تام؟</p>` +
    `<p class="text-muted small">الكمية الحالية: ${qty} — سيتم نقلها بالكامل إلى مخزن المنتج التام مع قيد محاسبي.</p>`,
   icon: 'question',
   showCancelButton: true,
   confirmButtonText: 'ترقية',
   cancelButtonText: 'إلغاء',
  }).then((result: { isConfirmed: boolean }) => {
   if (!result.isConfirmed) return;
   this.category.promoteToFinished(item.id).subscribe({
    next: (res: any) => {
     if (res?.success) {
      Swal.fire({
       icon: 'success',
       title: 'تم ترقية الصنف بنجاح',
       text: res.message ?? 'تم نقل الصنف إلى مخزن المنتج التام',
       timer: 3000,
       showConfirmButton: false,
      });
      this.search();
     } else {
      Swal.fire({ icon: 'error', title: 'فشلت الترقية', text: res?.message ?? '' });
     }
    },
    error: (err: any) => {
     const msg = err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر ترقية الصنف';
     Swal.fire({ icon: 'error', title: 'فشلت الترقية', text: String(msg) });
    },
   });
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

 canShowRowActions(item: { warehouse?: string }): boolean {
  return canShowCategoryRowActions(String(item?.warehouse ?? ''), this.user, this.rbac);
 }

}
