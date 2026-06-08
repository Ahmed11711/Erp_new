import { DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import Swal from 'sweetalert2';
import { ManufacturingService } from '../services/manufacturing.service';

@Component({
  selector: 'app-manufacturing-confirmation',
  templateUrl: './manufacturing-confirmation.component.html',
  styleUrls: ['./manufacturing-confirmation.component.css']
})
export class ManufacturingConfirmationComponent implements OnInit {

  product_id!: any;
  total: number = 0;
  quantity: number = 1;
  productPrice: number = 0;
  status: string = 'تم الانتهاء';

  /** مخزن السطر الأول: تحت تشغيل / منتج تام */
  selectedWarehouse: string = 'مخزن منتج تام';

  /** إغلاق رصيد WIP → تام (يُحترم عند «تم الانتهاء») */
  wipMode: 'auto' | 'full_same' | 'partial_new' | 'partial_merge' = 'auto';
  mergeProducts: any[] = [];
  mergeTargetProductId: number | null = null;
  /** رصيد تحت التشغيل للصنف المختار */
  availableStockQty: number | null = null;

  /**
   * استهلاك خام من الوصفة وزيادة رصيد صنف تحت التشغيل فقط، بدون تحويل لتام الآن.
   */
  wipKeepUnderProcessing = false;

  constructor(
    private datePipe: DatePipe,
    private manufacturingService: ManufacturingService,
    private route: Router
  ) {}

  ngOnInit(): void {
    this.reloadProducts(this.selectedWarehouse);
    this.reloadMergeProducts();
  }

  products: any[] = [];
  catword = 'category_name';

  more: any[] = [];
  catword2 = 'category_name';

  private reloadProducts(warehouse: string) {
    this.manufacturingService.manfuctureByWarhouse(warehouse).subscribe((result: any) => {
      this.products = result;
    });
  }

  private reloadMergeProducts() {
    this.manufacturingService
      .manfuctureByWarhouse('مخزن منتج تام', 'all_categories')
      .subscribe((result: any) => {
        this.mergeProducts = result;
      });
  }

  warehouseType(e: Event) {
    const v = (e.target as HTMLSelectElement).value;
    this.selectedWarehouse = v;
    this.reloadProducts(v);
    this.product_id = undefined as any;
    this.availableStockQty = null;
    this.productPrice = 0;
    this.total = 0;
    this.mergeTargetProductId = null;
    this.wipKeepUnderProcessing = false;
  }

  onWipKeepToggle(): void {
    if (this.wipKeepUnderProcessing) {
      this.mergeTargetProductId = null;
    }
  }

  productChange(event: any) {
    this.productPrice = event.cost;
    this.product_id = event.id;
    this.availableStockQty =
      event.quantity != null && event.quantity !== '' ? Number(event.quantity) : null;
    this.recalcTotal();
  }

  quantityFun(e: Event) {
    const t = e.target as HTMLInputElement;
    if (t.value === '') {
      this.quantity = 1;
    } else {
      this.quantity = Number(t.value);
    }
    this.recalcTotal();
  }

  private recalcTotal() {
    this.total = Number(this.productPrice) * Number(this.quantity);
  }

  mergeProductSelected(event: any) {
    this.mergeTargetProductId = event.id;
  }

  moreChange(_event: unknown) {}

  dateSelected = false;
  date: any;

  OnDateChange(event: unknown) {
    const inputDate = new Date(event as string | Date);
    this.date = this.datePipe.transform(inputDate, 'yyyy-M-d');
    this.dateSelected = true;
  }

  isSubmitting: boolean = false;

  submit() {
    if (!this.product_id || !this.date || this.isSubmitting) {
      return;
    }

    const qty = Number(this.quantity);
    const wipWarehouse = 'مخزن منتج تحت التشغيل';

    if (
      this.selectedWarehouse === wipWarehouse &&
      this.status === 'تم الانتهاء' &&
      !this.wipKeepUnderProcessing
    ) {
      if (this.wipMode === 'partial_merge' && !this.mergeTargetProductId) {
        Swal.fire({
          icon: 'warning',
          title: 'اختر صنف التام',
          text: 'وضع «دمج في صنف تام» يتطلب اختيار صنف من مخزن المنتج التام.',
        });
        return;
      }
      if (this.availableStockQty != null && qty > this.availableStockQty + 0.0001) {
        Swal.fire({
          icon: 'error',
          title: 'الكمية أكبر من الرصيد',
          text: `الرصيد المتاح تحت التشغيل: ${this.availableStockQty}`,
        });
        return;
      }
    }

    this.isSubmitting = true;

    const data: Record<string, unknown> = {
      total: this.total,
      product_id: this.product_id,
      status: this.status,
      date: this.date,
      quantity: this.quantity,
    };

    if (this.selectedWarehouse === wipWarehouse && this.status === 'تم الانتهاء') {
      if (this.wipKeepUnderProcessing) {
        data['wip_keep_under_processing'] = true;
      } else {
        data['wip_mode'] = this.wipMode;
        if (this.wipMode === 'partial_merge' && this.mergeTargetProductId) {
          data['wip_target_product_id'] = this.mergeTargetProductId;
        }
      }
    }

    this.manufacturingService.confirm(data).subscribe({
      next: (result: any) => {
        if (result?.wip_completion?.new_category_id) {
          Swal.fire({
            icon: 'success',
            title: 'تم إنشاء صنف تام جديد',
            html: `رقم الصنف الجديد: <strong>${result.wip_completion.new_category_id}</strong>`,
            timer: 4000,
            showConfirmButton: true,
          }).then(() => {
            this.route.navigate(['/dashboard/manufacturing/orders']);
          });
        } else if (result?.wip_stayed_under_processing) {
          Swal.fire({
            icon: 'success',
            title: 'تم التخزين في تحت التشغيل',
            text:
              'تم استهلاك المواد الخام وفق الوصفة وزيادة رصيد الصنف في مخزن تحت التشغيل. يمكنك لاحقاً تحويل الكمية إلى منتج تام من نفس الشاشة دون تفعيل هذا الخيار.',
            timer: 5000,
            showConfirmButton: true,
          }).then(() => {
            this.route.navigate(['/dashboard/manufacturing/orders']);
          });
        } else {
          this.route.navigate(['/dashboard/manufacturing/orders']);
        }
      },
      error: (err: any) => {
        const msg =
          err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر تأكيد أمر التصنيع';
        Swal.fire({ icon: 'error', title: 'فشل التأكيد', text: String(msg) });
        this.isSubmitting = false;
      },
      complete: () => {
        this.isSubmitting = false;
      },
    });
  }

  resetInp() {
    this.productPrice = 0;
    this.availableStockQty = null;
    this.recalcTotal();
  }
}
