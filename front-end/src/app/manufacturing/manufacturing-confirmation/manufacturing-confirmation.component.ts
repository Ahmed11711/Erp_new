import { DatePipe } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import Swal from 'sweetalert2';
import { forkJoin } from 'rxjs';
import {
  ManufactureConsumptionLine,
  ManufacturingService,
} from '../services/manufacturing.service';

@Component({
  selector: 'app-manufacturing-confirmation',
  templateUrl: './manufacturing-confirmation.component.html',
  styleUrls: ['./manufacturing-confirmation.component.css']
})
export class ManufacturingConfirmationComponent implements OnInit, OnDestroy {
  readonly Math = Math;

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

  consumptionLines: ManufactureConsumptionLine[] = [];
  consumptionApplies = false;
  consumptionTotalCost = 0;
  consumptionAllSufficient = true;
  consumptionMessage: string | null = null;
  consumptionPreviewPending = false;
  consumptionPreviewError: string | null = null;

  /** عند التأكيد، حفظ المواد/الكميات الحالية على الوصفة الأصلية */
  updateRecipeOnConfirm = false;
  updatingRecipe = false;

  private previewTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(
    private datePipe: DatePipe,
    private manufacturingService: ManufacturingService,
    private route: Router
  ) {}

  ngOnInit(): void {
    this.reloadProducts(this.selectedWarehouse);
    this.reloadMergeProducts();
    this.loadSubstituteMaterialOptions();
  }

  ngOnDestroy(): void {
    if (this.previewTimer) {
      clearTimeout(this.previewTimer);
    }
  }

  products: any[] = [];
  catword = 'category_name';
  /** مواد بديلة مسموح بها (خام + تحت التشغيل) */
  substituteMaterialOptions: any[] = [];
  catwordSubstitute = 'category_name';

  more: any[] = [];
  catword2 = 'category_name';

  private reloadProducts(warehouse: string) {
    this.manufacturingService.manfuctureByWarhouse(warehouse).subscribe((result: any) => {
      this.products = result;
    });
  }

  private loadSubstituteMaterialOptions(): void {
    forkJoin({
      raw: this.manufacturingService.manfuctureByWarhouse('مخزن مواد خام', 'all_categories'),
      wip: this.manufacturingService.manfuctureByWarhouse('مخزن منتج تحت التشغيل', 'all_categories'),
    }).subscribe({
      next: ({ raw, wip }) => {
        const asList = (value: unknown): any[] => (Array.isArray(value) ? value : []);
        const merged = [...asList(raw), ...asList(wip)];
        const seen = new Set<number>();
        this.substituteMaterialOptions = merged.filter((item) => {
          const id = Number(item?.id ?? 0);
          if (!id || seen.has(id)) {
            return false;
          }
          seen.add(id);
          return true;
        });
      },
      error: () => {
        this.substituteMaterialOptions = [];
      },
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
    this.clearConsumptionPreview();
  }

  onWipKeepToggle(): void {
    if (this.wipKeepUnderProcessing) {
      this.mergeTargetProductId = null;
    }
    this.scheduleConsumptionPreview();
  }

  onStatusChange(): void {
    this.scheduleConsumptionPreview();
  }

  productChange(event: any) {
    this.productPrice = event.cost;
    this.product_id = event.id;
    this.availableStockQty =
      event.quantity != null && event.quantity !== '' ? Number(event.quantity) : null;
    this.recalcTotal();
    this.scheduleConsumptionPreview();
  }

  quantityFun(e: Event) {
    const t = e.target as HTMLInputElement;
    if (t.value === '') {
      this.quantity = 1;
    } else {
      this.quantity = Number(t.value);
    }
    this.recalcTotal();
    this.scheduleConsumptionPreview();
  }

  private recalcTotal() {
    this.total = Number(this.productPrice) * Number(this.quantity);
  }

  get recipeBasedTotal(): number {
    return Math.round(Number(this.productPrice) * Number(this.quantity) * 10000) / 10000;
  }

  get consumptionCostDifference(): number {
    return Math.round((Number(this.consumptionTotalCost) - this.recipeBasedTotal) * 10000) / 10000;
  }

  /** اعتماد تكلفة المواد الفعلية (من الجدول) كتكلفة هذا الأمر */
  applyActualMaterialCost(): void {
    this.total = Math.round(Number(this.consumptionTotalCost) * 10000) / 10000;
    if (Number(this.quantity) > 0) {
      this.productPrice = Math.round((this.total / Number(this.quantity)) * 10000) / 10000;
    }
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

  private clearConsumptionPreview(): void {
    this.consumptionLines = [];
    this.consumptionApplies = false;
    this.consumptionTotalCost = 0;
    this.consumptionAllSufficient = true;
    this.consumptionMessage = null;
    this.consumptionPreviewError = null;
  }

  scheduleConsumptionPreview(delayMs = 350): void {
    if (this.previewTimer) {
      clearTimeout(this.previewTimer);
    }
    this.previewTimer = setTimeout(() => this.loadConsumptionPreview(), delayMs);
  }

  loadConsumptionPreview(): void {
    if (!this.product_id || !this.quantity || this.quantity <= 0) {
      this.clearConsumptionPreview();
      return;
    }

    this.consumptionPreviewPending = true;
    this.consumptionPreviewError = null;

    const payload: Record<string, unknown> = {
      product_id: this.product_id,
      quantity: this.quantity,
      status: this.status,
      wip_keep_under_processing: this.wipKeepUnderProcessing,
    };

    if (this.consumptionLines.length > 0) {
      payload['consumption_lines'] = this.consumptionLines.map((line) => ({
        bom_item_id: line.bom_item_id,
        resolved_category_id: line.resolved_category_id,
        quantity: line.quantity,
      }));
    }

    this.manufacturingService.previewConsumption(payload as any).subscribe({
      next: (preview) => {
        this.consumptionApplies = !!preview.applies;
        this.consumptionLines = Array.isArray(preview.lines) ? preview.lines : [];
        this.consumptionTotalCost = Number(preview.total_cost ?? 0);
        this.consumptionAllSufficient = preview.all_sufficient !== false;
        this.consumptionMessage = preview.message ?? null;
        this.consumptionPreviewPending = false;
      },
      error: (err) => {
        this.clearConsumptionPreview();
        this.consumptionPreviewError =
          err?.error?.message ?? 'تعذر تحميل مواد الاستهلاك.';
        this.consumptionPreviewPending = false;
      },
    });
  }

  onConsumptionQtyChange(line: ManufactureConsumptionLine, raw: string): void {
    const qty = raw === '' ? 0 : Number(raw);
    if (Number.isNaN(qty) || qty < 0) {
      return;
    }
    line.quantity = qty;
    line.line_cost = Math.round(qty * Number(line.unit_cost) * 10000) / 10000;
    line.sufficient = Number(line.available_quantity) + 0.00001 >= qty;
    line.is_customized = this.isLineCustomized(line);
    this.recalcConsumptionTotals();
    this.scheduleConsumptionPreview(500);
  }

  onSubstituteMaterial(line: ManufactureConsumptionLine, item: any): void {
    const newId = Number(item?.id ?? 0);
    if (!newId) {
      return;
    }
    line.resolved_category_id = newId;
    line.is_substituted = this.isLineSubstituted(line);
    line.is_customized = this.isLineCustomized(line);
    this.scheduleConsumptionPreview(200);
  }

  private isLineSubstituted(line: ManufactureConsumptionLine): boolean {
    const defaultId = Number(line.default_resolved_category_id ?? line.resolved_category_id);
    return Number(line.resolved_category_id) !== defaultId;
  }

  private isLineCustomized(line: ManufactureConsumptionLine): boolean {
    return (
      this.isLineSubstituted(line) ||
      Math.abs(Number(line.quantity) - Number(line.default_quantity)) > 0.000001
    );
  }

  resetConsumptionLine(line: ManufactureConsumptionLine): void {
    if (line.default_resolved_category_id != null) {
      line.resolved_category_id = Number(line.default_resolved_category_id);
    }
    line.quantity = Number(line.default_quantity);
    line.is_substituted = false;
    line.is_customized = false;
    this.scheduleConsumptionPreview(200);
  }

  resetAllConsumptionLines(): void {
    this.consumptionLines = [];
    this.scheduleConsumptionPreview(100);
  }

  private recalcConsumptionTotals(): void {
    this.consumptionTotalCost = this.consumptionLines.reduce(
      (sum, line) => sum + Number(line.line_cost ?? 0),
      0
    );
    this.consumptionAllSufficient = this.consumptionLines.every((line) => line.sufficient);
  }

  private buildConsumptionPayload(): Array<{
    bom_item_id: number;
    resolved_category_id: number;
    quantity: number;
  }> | undefined {
    if (!this.consumptionApplies || this.consumptionLines.length === 0) {
      return undefined;
    }
    return this.consumptionLines.map((line) => ({
      bom_item_id: line.bom_item_id,
      resolved_category_id: line.resolved_category_id,
      quantity: Number(line.quantity),
    }));
  }

  get hasCustomizedConsumption(): boolean {
    return this.consumptionLines.some((line) => line.is_customized);
  }

  saveRecipeFromConsumption(): void {
    const payload = this.buildConsumptionPayload();
    if (!this.product_id || !payload?.length) {
      return;
    }

    Swal.fire({
      icon: 'question',
      title: 'تحديث الوصفة؟',
      text: 'سيتم حفظ المواد والكميات الحالية على الوصفة الأصلية. أوامر التصنيع القادمة ستستخدم هذا التعديل.',
      showCancelButton: true,
      confirmButtonText: 'نعم، حفظ',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      this.updatingRecipe = true;
      this.manufacturingService
        .updateRecipeFromConsumption({
          product_id: Number(this.product_id),
          quantity: Number(this.quantity),
          consumption_lines: payload,
        })
        .subscribe({
          next: (res) => {
            this.updatingRecipe = false;
            Swal.fire({
              icon: 'success',
              title: 'تم الحفظ',
              text: res?.message ?? 'تم تحديث الوصفة.',
              timer: 2500,
              showConfirmButton: true,
            });
            this.resetAllConsumptionLines();
            if (Number(this.quantity) > 0 && Number(this.consumptionTotalCost) > 0) {
              this.productPrice = Math.round((Number(this.consumptionTotalCost) / Number(this.quantity)) * 10000) / 10000;
              this.recalcTotal();
            }
          },
          error: (err) => {
            this.updatingRecipe = false;
            Swal.fire({
              icon: 'error',
              title: 'تعذر التحديث',
              text: err?.error?.message ?? 'لم يتم حفظ الوصفة.',
            });
          },
        });
    });
  }

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

    if (this.consumptionApplies && !this.consumptionAllSufficient) {
      Swal.fire({
        icon: 'error',
        title: 'رصيد خام غير كافٍ',
        text: 'عدّل كميات الاستهلاك أو زِد مخزون المواد الخام قبل التأكيد.',
      });
      return;
    }

    this.isSubmitting = true;

    const data: Record<string, unknown> = {
      total: this.total,
      product_id: this.product_id,
      status: this.status,
      date: this.date,
      quantity: this.quantity,
    };

    const consumptionLines = this.buildConsumptionPayload();
    if (consumptionLines) {
      data['consumption_lines'] = consumptionLines;
    }
    if (this.updateRecipeOnConfirm && this.hasCustomizedConsumption) {
      data['update_recipe'] = true;
    }

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
    this.clearConsumptionPreview();
  }
}
