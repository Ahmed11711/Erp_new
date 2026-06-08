import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { Router } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';
import { OrderService } from '../services/order.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { RBAC_ROUTE } from 'src/app/guards/rbac-route-data';

@Component({
  selector: 'app-dialog-shopify-order-review',
  templateUrl: './dialog-shopify-order-review.component.html',
  styleUrls: ['./dialog-shopify-order-review.component.css']
})
export class DialogShopifyOrderReviewComponent implements OnInit {
  loading = true;
  submitting = false;
  order: any = null;
  reviewNote = '';

  constructor(
    public dialogRef: MatDialogRef<DialogShopifyOrderReviewComponent>,
    @Inject(MAT_DIALOG_DATA) public data: { orderId: number; listItem?: any; refresh?: () => void },
    private orderService: OrderService,
    private router: Router,
    private snackBar: MatSnackBar,
    private rbac: RbacService,
  ) {}

  get canConfirmReview(): boolean {
    return this.rbac.canAny([...RBAC_ROUTE.shopifyOrderReview]);
  }

  ngOnInit(): void {
    this.orderService.getOrderById(this.data.orderId).subscribe({
      next: (res: any) => {
        this.order = res;
        this.reviewNote = res?.shopify_review_note || '';
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.snackBar.open('تعذّر تحميل بيانات الطلب', 'إغلاق', { duration: 5000 });
      }
    });
  }

  get isReviewed(): boolean {
    return !!this.order?.shopify_reviewed_at;
  }

  get financialStatusLabel(): string {
    const s = this.order?.shopify_financial_status;
    if (!s) return '—';
    const map: Record<string, string> = {
      paid: 'مدفوع',
      pending: 'معلق (COD)',
      partially_paid: 'مدفوع جزئياً',
      authorized: 'مصرّح',
      refunded: 'مسترد',
      voided: 'ملغي',
    };
    return map[s] || s;
  }

  openEditOrder(): void {
    this.dialogRef.close();
    this.router.navigate(['/dashboard/shipping/orderdetails', this.data.orderId], {
      queryParams: { shopifyReview: '1' },
    });
  }

  confirmReview(): void {
    if (!this.canConfirmReview) {
      this.snackBar.open('ليس لديك صلاحية مراجعة طلبات Shopify', 'إغلاق', { duration: 5000 });
      return;
    }
    if (this.submitting) return;
    this.submitting = true;
    this.orderService.shopifyReviewOrder(this.data.orderId, {
      note: this.reviewNote?.trim() || null,
    }).subscribe({
      next: (res: any) => {
        this.submitting = false;
        this.order = { ...this.order, ...(res?.order || {}) };
        this.snackBar.open(res?.message || 'تم تسجيل المراجعة', 'إغلاق', { duration: 4000 });
        this.data.refresh?.();
      },
      error: (err) => {
        this.submitting = false;
        const msg = err?.error?.message || 'فشل تسجيل المراجعة';
        this.snackBar.open(msg, 'إغلاق', { duration: 6000 });
      }
    });
  }

  close(): void {
    this.dialogRef.close(this.isReviewed);
  }
}
