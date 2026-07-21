import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { OrderService } from '../services/order.service';
import { CollectionCompanyService } from '../services/collection-company.service';
import { ShippingCompanyService } from '../services/shipping-company.service';
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
    private shippingCompanyService: ShippingCompanyService,
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

    // تحميل القوائم الكاملة حتى يمكن اختيار أي شركة (الافتراضي: الشركة التابعة للطلب).
    let shippingList: any[] = [];
    let collectionList: any[] = [];
    try {
      const s: any = await firstValueFrom(this.shippingCompanyService.shippingCompanySelect());
      shippingList = Array.isArray(s) ? s : (s?.data ?? []);
    } catch {}
    try {
      const c: any = await firstValueFrom(this.collectionCompanyService.select());
      collectionList = Array.isArray(c) ? c : (c?.data ?? []);
    } catch {}

    const shipOpts = shippingList
      .filter((c) => c?.id)
      .map((c) => {
        const type = c.type === 'مندوب' ? 'courier' : 'shipping_company';
        return `<option value="${type}:${c.id}">${this.escapeHtml(c.name)}</option>`;
      })
      .join('');
    const collOpts = collectionList
      .filter((c) => c?.id && c.status !== 'inactive')
      .map((c) => `<option value="collection_company:${c.id}">${this.escapeHtml(c.name)}</option>`)
      .join('');

    if (!shipOpts && !collOpts) {
      Swal.fire('تنبيه', 'لا توجد شركات شحن أو تحصيل متاحة.', 'warning');
      return;
    }

    // الشركة التابعة للطلب هي الافتراضي.
    const providerId = this.data?.delivery?.shipping_provider_id;
    const providerName = this.data?.delivery?.shipping_provider_name;
    let defaultKey = '';
    if (providerId) {
      const match = shippingList.find((c) => String(c.id) === String(providerId));
      const type = match?.type === 'مندوب' ? 'courier' : 'shipping_company';
      defaultKey = `${type}:${providerId}`;
    } else if (
      this.data?.financial?.suggested_liability_holder_type &&
      this.data?.financial?.suggested_liability_holder_id
    ) {
      defaultKey = `${this.data.financial.suggested_liability_holder_type}:${this.data.financial.suggested_liability_holder_id}`;
    }

    const amount = this.data?.financial?.liability_transfer_amount;
    const amountLine =
      amount != null ? `<p style="margin:0 0 8px">المبلغ: <b>${this.escapeHtml(amount)}</b></p>` : '';
    const currentLine = providerName
      ? `<p style="margin:0 0 8px;color:#6c757d">الشركة التابعة للطلب: <b>${this.escapeHtml(providerName)}</b></p>`
      : '';

    const result = await Swal.fire<{ to_holder_type: string; to_holder_id: number }>({
      title: 'نقل الذمة',
      html: `
        <div style="text-align:right;direction:rtl">
          <p style="margin:0 0 8px">اختر الجهة التي تُنقل إليها الذمة — الافتراضي هو الشركة التابعة للطلب، ويمكنك تغييرها:</p>
          ${currentLine}
          ${amountLine}
          <select id="swal-holder" class="swal2-select" style="width:100%;margin:0">
            ${shipOpts ? `<optgroup label="شركات الشحن / المناديب">${shipOpts}</optgroup>` : ''}
            ${collOpts ? `<optgroup label="شركات التحصيل">${collOpts}</optgroup>` : ''}
          </select>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد ونقل الذمة',
      cancelButtonText: 'إلغاء',
      focusConfirm: false,
      didOpen: () => {
        const el = document.getElementById('swal-holder') as HTMLSelectElement | null;
        if (el && defaultKey) el.value = defaultKey;
      },
      preConfirm: () => {
        const el = document.getElementById('swal-holder') as HTMLSelectElement | null;
        const [type, id] = (el?.value || '').split(':');
        if (!type || !id) {
          Swal.showValidationMessage('اختر الجهة');
          return null as any;
        }
        return { to_holder_type: type, to_holder_id: Number(id) };
      },
    });

    if (!result.isConfirmed || !result.value) return;

    this.orderService
      .transferOrderLiability(this.orderId, {
        to_holder_type: result.value.to_holder_type,
        to_holder_id: Number(result.value.to_holder_id),
      })
      .subscribe({
        next: () => {
          Swal.fire('تم', 'تم نقل الذمة بنجاح', 'success');
          this.load();
        },
        error: (err) => Swal.fire('خطأ', err?.error?.message || 'فشل نقل الذمة', 'error'),
      });
  }

  private escapeHtml(value: any): string {
    return String(value ?? '').replace(
      /[&<>"']/g,
      (ch) => (({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } as any)[ch])
    );
  }
}
