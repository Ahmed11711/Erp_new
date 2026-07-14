import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { OrderService } from '../services/order.service';
import { CollectionCompanyService } from '../services/collection-company.service';
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

  constructor(
    private orderService: OrderService,
    private collectionCompanyService: CollectionCompanyService,
  ) {}

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

  canTransferLiability(): boolean {
    if (!this.data?.financial || this.data.financial.liability_transferred_at) {
      return false;
    }
    if (this.data.financial.can_transfer_liability === true) {
      return true;
    }
    const remaining = parseFloat(String(this.data.financial.remaining_amount ?? 0)) || 0;
    const collectionReceivable =
      parseFloat(String(this.data.financial.collection_receivable_amount ?? 0)) || 0;
    return remaining > 0.009 || collectionReceivable > 0.009;
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

    const toHolderType = holder === 'collection_company' ? 'collection_company' : 'courier';
    let providerId: number | null =
      holder === 'collection_company'
        ? this.data.collection?.collection_provider_id ??
          (this.data.financial?.suggested_liability_holder_type === 'collection_company'
            ? this.data.financial?.suggested_liability_holder_id
            : null)
        : this.data.delivery?.shipping_provider_id;

    if (holder === 'collection_company' && !providerId) {
      providerId = await this.pickCollectionCompany();
      if (!providerId) return;
    }

    if (!providerId) {
      Swal.fire(
        'تنبيه',
        holder === 'collection_company'
          ? 'تعذر تحديد شركة التحصيل. اختر شركة من القائمة أو راجع إعدادات شركات التحصيل.'
          : 'حدد جهة الشحن أو المندوب أولاً من شاشة الشحن.',
        'warning'
      );
      return;
    }

    this.orderService
      .transferOrderLiability(this.orderId, {
        to_holder_type: toHolderType,
        to_holder_id: Number(providerId),
      })
      .subscribe({
        next: () => {
          Swal.fire('تم', 'تم نقل الذمة بنجاح', 'success');
          this.load();
        },
        error: (err) => Swal.fire('خطأ', err?.error?.message || 'فشل نقل الذمة', 'error'),
      });
  }

  private async pickCollectionCompany(): Promise<number | null> {
    let companies: { id: number; name: string; status?: string }[] = [];
    try {
      const res: any = await firstValueFrom(this.collectionCompanyService.select());
      companies = (Array.isArray(res) ? res : res?.data ?? []).filter(
        (c: any) => c?.id && c.status !== 'inactive'
      );
    } catch {
      Swal.fire('خطأ', 'تعذر تحميل شركات التحصيل', 'error');
      return null;
    }

    if (!companies.length) {
      Swal.fire(
        'تنبيه',
        'لا توجد شركات تحصيل. أضف شركة من إدارة شركات التحصيل أولاً.',
        'warning'
      );
      return null;
    }

    const inputOptions: Record<string, string> = {};
    for (const c of companies) {
      inputOptions[String(c.id)] = c.name;
    }

    const { value } = await Swal.fire({
      title: 'اختر شركة التحصيل',
      text: 'الطلب غير مرتبط بوسيلة دفع (Paymob/Shopify) — اختر شركة التحصيل يدوياً',
      input: 'select',
      inputOptions,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      inputValidator: (v) => (!v ? 'اختر شركة التحصيل' : null),
    });

    return value ? Number(value) : null;
  }
}
