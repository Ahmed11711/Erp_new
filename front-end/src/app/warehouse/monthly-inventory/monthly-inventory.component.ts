import { Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { CategoryService } from 'src/app/categories/services/category.service';
import { Location } from '@angular/common';
import Swal from 'sweetalert2';
import { Subscription } from 'rxjs';

/** صف جدول الجرد الشهري من واجهة الـ API */
export interface MonthlyInventoryRow {
  quantity: number;
  total_price: number;
  sell_total_price: number;
  by: string;
  category: {
    category_name: string;
    category_price?: number;
    measurement: { unit: string };
  };
}

@Component({
  selector: 'app-monthly-inventory',
  templateUrl: './monthly-inventory.component.html',
  styleUrls: ['./monthly-inventory.component.css'],
})
export class MonthlyInventoryComponent implements OnInit, OnDestroy {

  warehouse = '';
  categories: MonthlyInventoryRow[] = [];
  prevMonthValue!: string;
  month!: number;
  year!: number;

  length = 50;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15, 50];

  private querySub?: Subscription;

  constructor(private category: CategoryService, private route: ActivatedRoute, private _location: Location) {}


  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe((params) => {
      this.warehouse = params['warehouse'] ?? '';
      if (!this.prevMonthValue) {
        const today = new Date();
        let year = today.getFullYear();
        let month = today.getMonth() + 1;
        let prevYear = year;
        let prevMonth = month - 1;
        if (prevMonth === 0) {
          prevMonth = 12;
          prevYear--;
        }
        this.year = prevYear;
        this.month = prevMonth;
        this.prevMonthValue = `${prevYear}-${String(prevMonth).padStart(2, '0')}`;
      }
      this.page = 0;
      this.getData();
    });
  }

  ngOnDestroy(): void {
    this.querySub?.unsubscribe();
  }



  onMonthChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.prevMonthValue = target.value;
    const [yearStr, monthStr] = this.prevMonthValue.split('-');
    this.year = parseInt(yearStr, 10);
    this.month = parseInt(monthStr, 10);
    this.getData();
  }

  getData(){
    this.category.monthlyInventoryDetails(this.warehouse, this.pageSize,this.page+1 , this.prevMonthValue , this.param).subscribe((res:any)=>{
      const rows = res?.data;
      this.categories = Array.isArray(rows) ? rows as MonthlyInventoryRow[] : [];
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
      this.page = event.pageIndex;
      this.getData();
  }

  back() {
    this._location.back();
  }

  param: Record<string, string | boolean> = {};
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

  /** حفظ لقطة جرد شهرية بناءً على الأرصدة الحالية (نفس منطق صفحة أصناف المخزن). */
  recordMonthlySnapshot(): void {
    if (!this.warehouse) {
      Swal.fire({ icon: 'warning', text: 'لم يُحدد مخزن' });
      return;
    }
    const month = this.prevMonthValue;
    Swal.fire({
      title: 'تأكيد حفظ لقطة الجرد الشهري؟',
      html: `سيتم تسجيل لقطة مخزون للشهر <strong>${month}</strong> بناءً على الأرصدة الحالية.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم',
      cancelButtonText: 'لا',
    }).then((result: { isConfirmed: boolean }) => {
      if (!result.isConfirmed) {
        return;
      }
      this.category.monthlyInventory(this.warehouse, month).subscribe({
        next: (res: { success?: boolean; month?: string; lines?: number }) => {
          if (res?.success) {
            Swal.fire({
              icon: 'success',
              title: 'تم التسجيل',
              text: `الشهر: ${res.month} — ${res.lines ?? 0} صنفاً`,
              timer: 2200,
              showConfirmButton: false,
            });
          } else {
            Swal.fire({ icon: 'success', title: 'تم', timer: 1500, showConfirmButton: false });
          }
          this.getData();
        },
        error: (err: { error?: { message?: string; errors?: Record<string, string[]> }; message?: string }) => {
          const msg =
            err?.error?.message
            || (err?.error?.errors ? Object.values(err.error.errors).flat().join(', ') : null)
            || err?.message
            || 'تعذر تسجيل الجرد';
          Swal.fire({ icon: 'error', text: msg, showConfirmButton: true });
        },
      });
    });
  }

}
