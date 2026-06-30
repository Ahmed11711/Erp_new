import { Component, Inject } from '@angular/core';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { OrderService } from '../services/order.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-dialog-cancel-order-line',
  templateUrl: './dialog-cancel-order-line.component.html',
  styleUrls: ['./dialog-cancel-order-line.component.css'],
})
export class DialogCancelOrderLineComponent {
  submitting = false;
  maxQty = 0;
  productName = '';

  form = new FormGroup({
    quantity: new FormControl<number | null>(null, [Validators.required, Validators.min(0.001)]),
    reason: new FormControl<string>('', [Validators.required, Validators.minLength(2)]),
    note: new FormControl<string>(''),
  });

  constructor(
    public dialogRef: MatDialogRef<DialogCancelOrderLineComponent>,
    @Inject(MAT_DIALOG_DATA) public data: {
      orderId: number;
      product: any;
      refresh: () => void;
    },
    private orderService: OrderService,
  ) {
    const product = data.product;
    const quantity = Number(product?.quantity) || 0;
    const shipped = Number(product?.shipped_quantity) || 0;
    const cancelled = Number(product?.cancelled_quantity) || 0;
    this.maxQty = Math.max(0, quantity - shipped - cancelled);
    this.productName = product?.category?.category_name || product?.special_details || '—';

    this.form.patchValue({
      quantity: this.maxQty > 0 ? this.maxQty : null,
    });
  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

  submit(): void {
    if (this.form.invalid || this.submitting) {
      this.form.markAllAsTouched();
      return;
    }

    const qty = Number(this.form.value.quantity);
    if (qty > this.maxQty + 0.0001) {
      Swal.fire({ icon: 'error', title: `الحد الأقصى للإلغاء: ${this.maxQty}` });
      return;
    }

    this.submitting = true;
    this.orderService.cancelOrderLines(this.data.orderId, {
      lines: [{
        order_product_id: this.data.product.id,
        quantity: qty,
        reason: String(this.form.value.reason ?? '').trim(),
      }],
      note: String(this.form.value.note ?? '').trim() || undefined,
    }).subscribe({
      next: (res: any) => {
        this.submitting = false;
        this.data.refresh();
        this.dialogRef.close(true);
        Swal.fire({
          icon: 'success',
          title: res?.message || 'تم الإلغاء',
          timer: 2000,
          showConfirmButton: false,
        });
      },
      error: (err) => {
        this.submitting = false;
        Swal.fire({
          icon: 'error',
          title: err?.error?.message || 'تعذّر إلغاء الصنف',
        });
      },
    });
  }
}
