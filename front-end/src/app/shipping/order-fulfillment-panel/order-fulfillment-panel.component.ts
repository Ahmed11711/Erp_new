import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import { OrderService } from '../services/order.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-order-fulfillment-panel',
  templateUrl: './order-fulfillment-panel.component.html',
  styleUrls: ['./order-fulfillment-panel.component.css'],
})
export class OrderFulfillmentPanelComponent implements OnChanges {
  @Input() orderId!: number;
  @Input() readonly = false;

  loading = false;
  data: any = null;

  constructor(private orderService: OrderService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['orderId']?.currentValue) {
      this.load();
    }
  }

  load(): void {
    if (!this.orderId) return;
    this.loading = true;
    this.orderService.getOrderFulfillment(this.orderId).subscribe({
      next: (res) => {
        this.data = res;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
      },
    });
  }

  badgeClass(kind: string, value: string): string {
    const map: Record<string, string> = {
      collected: 'badge-success',
      delivered: 'badge-info',
      shipped: 'badge-primary',
      pending: 'badge-warning',
      transferred: 'badge-secondary',
      not_required: 'badge-light',
      settled: 'badge-success',
      open: 'badge-warning',
    };
    return 'badge ' + (map[value] || 'badge-light');
  }

  async transferLiability(): Promise<void> {
    if (this.readonly || !this.data) return;
    const { value: holder } = await Swal.fire({
      title: 'نقل الذمة — من يتحمل المبلغ؟',
      input: 'select',
      inputOptions: {
        courier: 'المندوب / شركة الشحن',
        collection_company: 'شركة تحصيل',
      },
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
    });
    if (!holder) return;

    const providerId =
      holder === 'collection_company'
        ? this.data.collection?.collection_provider_id
        : this.data.delivery?.shipping_provider_id;

    if (!providerId) {
      Swal.fire('تنبيه', 'حدد جهة الشحن أو التحصيل أولاً من شاشة الشحن.', 'warning');
      return;
    }

    this.orderService
      .transferOrderLiability(this.orderId, {
        to_holder_type: holder === 'collection_company' ? 'collection_company' : 'courier',
        to_holder_id: providerId,
      })
      .subscribe({
        next: () => {
          Swal.fire('تم', 'تم نقل الذمة بنجاح', 'success');
          this.load();
        },
        error: (err) => Swal.fire('خطأ', err?.error?.message || 'فشل نقل الذمة', 'error'),
      });
  }
}
