import { Component, Inject, OnInit } from '@angular/core';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { OrderService } from '../services/order.service';
import Swal from 'sweetalert2';

export interface OrderRollbackDialogData {
  orderId: number;
  currentStatus: string;
  refreshData: () => void;
}

@Component({
  selector: 'app-dialog-order-rollback',
  templateUrl: './dialog-order-rollback.component.html',
  styleUrls: ['./dialog-order-rollback.component.css'],
})
export class DialogOrderRollbackComponent implements OnInit {
  title = 'إعادة فتح الطلب';
  loadingPreview = true;
  submitting = false;
  preview: any = null;
  loadError = '';

  form = new FormGroup({
    target: new FormControl<'confirmed' | 'new'>('confirmed', { nonNullable: true, validators: [Validators.required] }),
    reason: new FormControl<string>('', { nonNullable: true, validators: [Validators.required, Validators.minLength(3)] }),
    confirmCheck: new FormControl<boolean>(false, { nonNullable: true, validators: [Validators.requiredTrue] }),
  });

  constructor(
    public dialogRef: MatDialogRef<DialogOrderRollbackComponent>,
    @Inject(MAT_DIALOG_DATA) public data: OrderRollbackDialogData,
    private orderService: OrderService,
  ) {}

  ngOnInit(): void {
    this.form.controls.target.valueChanges.subscribe(() => this.loadPreview());
    this.loadPreview();
  }

  loadPreview(): void {
    this.loadingPreview = true;
    this.loadError = '';
    const target = this.form.controls.target.value;
    this.orderService.getOrderRollbackPreview(this.data.orderId, target).subscribe({
      next: (res) => {
        this.preview = res;
        this.loadingPreview = false;
      },
      error: (err) => {
        this.loadError = err?.error?.message || 'تعذر تحميل معاينة العمليات';
        this.loadingPreview = false;
      },
    });
  }

  onClose(): void {
    this.dialogRef.close();
  }

  submit(): void {
    if (!this.form.valid || this.submitting) {
      this.form.markAllAsTouched();
      return;
    }

    Swal.fire({
      title: 'تأكيد إعادة فتح الطلب',
      text: 'سيتم عكس جميع العمليات المحددة. هل أنت متأكد؟',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم، نفّذ',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      this.submitting = true;
      this.orderService.executeOrderRollback(this.data.orderId, {
        target: this.form.value.target!,
        reason: this.form.value.reason!,
        confirmed: true,
      }).subscribe({
        next: () => {
          this.submitting = false;
          this.data.refreshData();
          this.onClose();
          Swal.fire({
            icon: 'success',
            timer: 3000,
            showConfirmButton: false,
            titleText: 'تم إعادة فتح الطلب',
            position: 'bottom-end',
            toast: true,
            timerProgressBar: true,
          });
        },
        error: (err) => {
          this.submitting = false;
          Swal.fire({
            icon: 'error',
            title: err?.error?.message || 'تعذر تنفيذ إعادة الفتح',
          });
        },
      });
    });
  }
}
