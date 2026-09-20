import { Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { CategoryService } from 'src/app/categories/services/category.service';
import { ProductionService } from 'src/app/categories/services/production.service';
import {Location} from '@angular/common';
import Swal from 'sweetalert2';
import { Subscription } from 'rxjs';
import { WAREHOUSE_STOCK_ROWS } from 'src/app/shared/constants/warehouse-stock-rows';
@Component({
  selector: 'app-cat',
  templateUrl: './cat.component.html',
  styleUrls: ['./cat.component.css']
})
export class CatComponent implements OnInit, OnDestroy {

  warehouse:string = "";
  warehouseOptions = WAREHOUSE_STOCK_ROWS.map((row) => row.nameAr);
  categories:any[] = [];
  balance:number= 0;
  productions: Array<{ id: number; production_line?: string }> = [];
  readonly operatingSuppliesWarehouse = 'مستلزمات تشغيل وأدوات تشغيل';

  length = 0;
  pageSize = 15;
  page = 0;
  listLoading = false;
  listError = false;

  pageSizeOptions = [15,50];

  private querySub?: Subscription;
  private dataSub?: Subscription;

  constructor(
    private category: CategoryService,
    private productionService: ProductionService,
    private route: ActivatedRoute,
    private router: Router,
    private _location: Location
  ) {}


  ngOnInit(){
    this.productionService.getProductions().subscribe({
      next: (res: any) => {
        this.productions = Array.isArray(res) ? res : (res?.data ?? []);
      },
      error: () => {
        this.productions = [];
      },
    });
    this.querySub = this.route.queryParams.subscribe((params) => {
      const next = params['warehouse'] ?? '';
      if (next !== this.warehouse) {
        this.page = 0;
        this.param = {};
      }
      this.warehouse = next;
      this.balance = Number(params['balance']);
      if (Number.isNaN(this.balance)) {
        this.balance = 0;
      }
      if (this.warehouse) {
        this.getData();
      } else {
        this.categories = [];
        this.length = 0;
      }
    });
  }

  onWarehouseChange(event: Event): void {
    const name = (event.target as HTMLSelectElement).value;
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { warehouse: name || null },
      queryParamsHandling: 'merge',
    });
  }

  ngOnDestroy(): void {
    this.querySub?.unsubscribe();
    this.dataSub?.unsubscribe();
  }

  getData(){
    if (!this.warehouse) {
      this.categories = [];
      this.length = 0;
      this.listLoading = false;
      this.listError = false;
      return;
    }
    this.listLoading = true;
    this.listError = false;
    this.dataSub?.unsubscribe();
    this.dataSub = this.category.categoryDetails(this.warehouse, this.pageSize,this.page+1 , this.param).subscribe({
      next: (res:any)=>{
        this.categories = Array.isArray(res?.data) ? res.data : [];
        this.length = Number(res?.total ?? 0);
        this.pageSize = Number(res?.per_page ?? this.pageSize);
        this.listLoading = false;
      },
      error: () => {
        this.categories = [];
        this.length = 0;
        this.listLoading = false;
        this.listError = true;
      },
    });
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
      this.page = event.pageIndex;
      this.getData();
  }

  back() {
    this._location.back();
  }

  param = {};
  onCategorychange(event :any){
    if (event.target.id == 'type') {
      this.param['name']=event.target.value;
    }
    if (event.target.id == 'check') {
      console.log(event.target.checked);
      if (event.target.checked) {
        this.param['sort']= true;
      } else {
        delete this.param['sort'];
      }

    }
    this.getData();
  }

  private parseQuantityInput(value: unknown): number | null {
    if (value === null || value === undefined || value === '') {
      return null;
    }
    const n = typeof value === 'number' ? value : Number(String(value).trim());
    if (Number.isNaN(n)) {
      return null;
    }
    return n;
  }

  private todayIsoDate(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  private promptQtyAndDate(
    title: string,
    opts: { min: number; allowZero?: boolean; initialQty?: string }
  ): Promise<{ qty: number; date: string } | null> {
    const today = this.todayIsoDate();
    const initial = opts.initialQty ?? '';
    return Swal.fire({
      title,
      html:
        `<label class="d-block text-right mb-1">الكمية</label>` +
        `<input id="qty-adj-qty" type="number" min="${opts.min}" step="any" class="swal2-input" style="margin:0 0 0.75rem" value="${initial}">` +
        `<label class="d-block text-right mb-1">تاريخ الحركة</label>` +
        `<input id="qty-adj-date" type="date" class="swal2-input" style="margin:0 0 0.5rem" value="${today}" max="${today}">` +
        `<p class="small text-muted text-right mb-0">تظهر هذه الكمية في تقارير المخزون حسب التاريخ المختار</p>`,
      showCancelButton: true,
      confirmButtonText: 'حفظ',
      cancelButtonText: 'إلغاء',
      focusConfirm: false,
      preConfirm: () => {
        const qtyEl = document.getElementById('qty-adj-qty') as HTMLInputElement | null;
        const dateEl = document.getElementById('qty-adj-date') as HTMLInputElement | null;
        const qty = this.parseQuantityInput(qtyEl?.value);
        const date = (dateEl?.value || '').trim();
        if (qty === null || (opts.allowZero ? qty < 0 : qty <= 0)) {
          Swal.showValidationMessage(
            opts.allowZero ? 'يجب ادخال قيمة صحيحة' : 'يجب ادخال قيمة صحيحة أكبر من صفر'
          );
          return false;
        }
        if (!date) {
          Swal.showValidationMessage('اختر تاريخ الحركة');
          return false;
        }
        if (date > today) {
          Swal.showValidationMessage('لا يمكن اختيار تاريخ في المستقبل');
          return false;
        }
        return { qty, date };
      },
    }).then((result) => {
      if (!result.isConfirmed || !result.value) {
        return null;
      }
      return result.value as { qty: number; date: string };
    });
  }

  addQuantiy(id: number, name: string) {
    this.promptQtyAndDate(` ( ${name} ) اضافة كمية الى  `, { min: 0 }).then((picked) => {
      if (!picked) {
        return;
      }
      this.category.changeCategoryQuantity(id, 'add', picked.qty, picked.date).subscribe({
        next: () => this.getData(),
        error: (err) => Swal.fire({ icon: 'error', title: 'فشل تحديث الكمية', text: err?.error?.message || '' }),
      });
    });
  }

  removeQuantiy(id: number, name: string) {
    this.promptQtyAndDate(` ( ${name} )  تقليل كمية من `, { min: 0 }).then((picked) => {
      if (!picked) {
        return;
      }
      this.category.changeCategoryQuantity(id, 'add', -picked.qty, picked.date).subscribe({
        next: () => this.getData(),
        error: (err) => Swal.fire({ icon: 'error', title: 'فشل تحديث الكمية', text: err?.error?.message || '' }),
      });
    });
  }

  get isOperatingSuppliesWarehouse(): boolean {
    return this.warehouse === this.operatingSuppliesWarehouse;
  }

  get canAdjustClassicQty(): boolean {
    return (
      this.warehouse === 'مخزن منتج تام' ||
      this.warehouse === 'مخزن مواد خام' ||
      this.warehouse === 'مخزن منتج تحت التشغيل' ||
      this.isOperatingSuppliesWarehouse
    );
  }

  issueToProduction(item: { id: number; category_name: string; quantity?: number; production_id?: number }) {
    const optionsHtml = [
      '<option value="">— اختر قسم الإنتاج —</option>',
      ...this.productions.map(
        (p) =>
          `<option value="${p.id}" ${Number(item.production_id) === Number(p.id) ? 'selected' : ''}>${
            p.production_line || '#' + p.id
          }</option>`
      ),
    ].join('');

    Swal.fire({
      title: `صرف مستلزمات — ${item.category_name}`,
      html:
        `<label class="d-block text-right mb-1">الكمية</label>` +
        `<input id="ops-qty" type="number" min="0.000001" step="any" class="swal2-input" style="margin:0 0 0.75rem" placeholder="الكمية">` +
        `<label class="d-block text-right mb-1">قسم الإنتاج</label>` +
        `<select id="ops-prod" class="swal2-select" style="width:100%;margin:0 0 0.75rem">${optionsHtml}</select>` +
        `<label class="d-block text-right mb-1">ملاحظات (اختياري)</label>` +
        `<input id="ops-notes" type="text" class="swal2-input" style="margin:0" placeholder="ملاحظات">`,
      showCancelButton: true,
      confirmButtonText: 'صرف وترحيل القيد',
      cancelButtonText: 'إلغاء',
      focusConfirm: false,
      preConfirm: () => {
        const qtyEl = document.getElementById('ops-qty') as HTMLInputElement | null;
        const prodEl = document.getElementById('ops-prod') as HTMLSelectElement | null;
        const notesEl = document.getElementById('ops-notes') as HTMLInputElement | null;
        const qty = this.parseQuantityInput(qtyEl?.value);
        const productionId = prodEl?.value ? Number(prodEl.value) : null;
        if (qty === null || qty <= 0) {
          Swal.showValidationMessage('أدخل كمية صحيحة أكبر من صفر');
          return false;
        }
        if (!productionId) {
          Swal.showValidationMessage('اختر قسم الإنتاج');
          return false;
        }
        return {
          qty,
          production_id: productionId,
          notes: (notesEl?.value || '').trim(),
        };
      },
    }).then((result) => {
      if (!result.isConfirmed || !result.value) {
        return;
      }
      const { qty, production_id, notes } = result.value as {
        qty: number;
        production_id: number;
        notes: string;
      };
      this.category
        .issueOperatingSupplies({
          category_id: item.id,
          qty,
          production_id,
          notes: notes || undefined,
        })
        .subscribe({
          next: () => {
            Swal.fire({
              icon: 'success',
              title: 'تم الصرف',
              text: 'تم خصم المخزن وترحيل قيد المصروفات الصناعية غير المباشرة',
              timer: 2000,
              showConfirmButton: false,
            });
            this.getData();
          },
          error: (err) => {
            Swal.fire({
              icon: 'error',
              text: err?.error?.message || 'تعذر صرف المستلزمات',
            });
          },
        });
    });
  }

  editQuantiy(id: number, name: string, currentQty?: number) {
    const initial = currentQty != null && !Number.isNaN(Number(currentQty)) ? String(currentQty) : '';
    this.promptQtyAndDate(` ( ${name} )  تعديل كمية  `, { min: 0, allowZero: true, initialQty: initial }).then((picked) => {
      if (!picked) {
        return;
      }
      this.category.changeCategoryQuantity(id, 'edit', picked.qty, picked.date).subscribe({
        next: () => this.getData(),
        error: (err) => Swal.fire({ icon: 'error', title: 'فشل تحديث الكمية', text: err?.error?.message || '' }),
      });
    });
  }

  monthlyInventory(){
    const d = new Date();
    d.setMonth(d.getMonth() - 1);
    const month = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;

    Swal.fire({
      title: ' تأكيد الجرد الشهري ؟',
      html: `سيتم تسجيل لقطة مخزون لشهر <strong>${month}</strong> بناءً على الأرصدة الحالية والتكلفة المرجّحة (مثل منطق الشحن).`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم',
      cancelButtonText: 'لا',
    }).then((result:any) => {
      if (result.isConfirmed) {
        this.category.monthlyInventory(this.warehouse, month).subscribe({
          next: (res: any) => {
            if (res?.success) {
              Swal.fire({
                icon:'success',
                title: 'تم التسجيل',
                text: `الشهر: ${res.month} — ${res.lines ?? 0} صنفاً`,
                timer: 2200,
                showConfirmButton: false,
              });
            } else {
              Swal.fire({ icon: 'success', title: 'تم', timer: 1500, showConfirmButton: false });
            }
          },
          error: (err: any) => {
            const msg = err?.error?.message
              || (err?.error?.errors && typeof err.error.errors === 'object'
                ? Object.values(err.error.errors).flat().join(', ')
                : null)
              || err?.message
              || 'تعذر تسجيل الجرد';
            Swal.fire({ icon: 'error', text: msg, showConfirmButton: true });
          }
        });
      }
    })

  }

}
