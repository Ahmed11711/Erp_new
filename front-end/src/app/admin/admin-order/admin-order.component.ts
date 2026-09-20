import { Component, OnInit } from '@angular/core';
import { NotificationService } from 'src/app/notification/service/notification.service';
import { OrderService } from 'src/app/shipping/services/order.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-admin-order',
  templateUrl: './admin-order.component.html',
  styleUrls: ['./admin-order.component.css']
})
export class AdminOrderComponent implements OnInit {

  data: any[] = [];
  loading = false;
  loadError = '';

  length = 0;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15, 50, 100];

  constructor(
    private notificationService: NotificationService,
    private orderService: OrderService,
  ) {}

  ngOnInit(): void {
    this.getData();
  }

  onPageChange(event: any): void {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.getData();
  }

  getData(): void {
    this.loading = true;
    this.loadError = '';

    this.notificationService.recievedNotifiy(this.pageSize, this.page + 1, {
      review_status_user: '1',
    }).subscribe({
      next: (res: any) => {
        this.data = Array.isArray(res?.data) ? res.data : [];
        this.length = Number(res?.total ?? 0);
        this.pageSize = Number(res?.per_page ?? this.pageSize);
        this.loading = false;
      },
      error: (err) => {
        this.data = [];
        this.length = 0;
        this.loadError = err?.error?.message || 'تعذّر تحميل الأوردرات.';
        this.loading = false;
      },
    });
  }

  orderId(item: any): number {
    return Number(item?.ref ?? item?.order_id ?? 0);
  }

  ownerName(item: any): string {
    return item?.order?.customer_name || item?.sender?.name || '—';
  }

  statusLabel(item: any): string {
    return item?.order?.order_status || item?.note || item?.type || '—';
  }

  approve(item: any): void {
    const id = this.orderId(item);
    if (!id) {
      return;
    }

    Swal.fire({
      title: 'تأكيد الموافقة؟',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'نعم',
      cancelButtonText: 'لا',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      const request$ = item?.type === 'مراجعة مؤقتة'
        ? this.orderService.readTempReview(id)
        : this.orderService.reviewOrder({ id, reviewd: true, reviewd_note: '' });

      request$.subscribe({
        next: () => {
          Swal.fire({ icon: 'success', timer: 1500, showConfirmButton: false });
          this.getData();
        },
        error: (err) => {
          Swal.fire({
            icon: 'error',
            title: err?.error?.message || 'تعذّر تنفيذ الموافقة.',
          });
        },
      });
    });
  }

  reject(item: any): void {
    const id = this.orderId(item);
    if (!id) {
      return;
    }

    Swal.fire({
      title: 'تأكيد الرفض؟',
      input: item?.type === 'مراجعة مؤقتة' ? undefined : 'textarea',
      inputPlaceholder: 'ملاحظة الرفض (اختياري)',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'رفض',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      const request$ = item?.type === 'مراجعة مؤقتة'
        ? this.notificationService.delete(item.id)
        : this.orderService.reviewOrder({
          id,
          reviewd: false,
          reviewd_note: String(result.value ?? '').trim(),
        });

      request$.subscribe({
        next: () => {
          Swal.fire({ icon: 'success', timer: 1500, showConfirmButton: false });
          this.getData();
        },
        error: (err) => {
          Swal.fire({
            icon: 'error',
            title: err?.error?.message || 'تعذّر تنفيذ الرفض.',
          });
        },
      });
    });
  }
}
