import { DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { OrderService } from '../services/order.service';
import { ShippingCompanyService } from '../services/shipping-company.service';
import { CollectionCompanyService } from '../services/collection-company.service';

@Component({
  selector: 'app-ship-order',
  templateUrl: './ship-order.component.html',
  styleUrls: ['./ship-order.component.css']
})
export class ShipOrderComponent implements OnInit {
  lines: any = [];
  companies: any[] = [];
  collectionCompanies: any[] = [];

  customer_type!: string;
  orderStatus = '';
  paymentTypeActive = false;
  completedShip = false;
  orderSnap: any;
  /** طلب محوّل من عرض سعر — شركة الشحن اختيارية والذمة على العميل */
  isOfferOrder = false;

  selectedShippingCompanyId: string = '';
  companySearchKeyword = 'name';
  /** معرّف من جدول collection_companies */
  selectedCollectionCompanyId: string = '';

  showCollectionSplit = false;
  manualSplitMode = false;
  manualShippingAmount: number | null = null;
  manualCollectionAmount: number | null = null;

  computedShippingAmount = 0;
  computedCollectionAmount = 0;
  splitError: string = '';

  constructor(
    private order: OrderService,
    private route: ActivatedRoute,
    private datePipe: DatePipe,
    private company: ShippingCompanyService,
    private collectionCompanyService: CollectionCompanyService,
    private router: Router
  ) {}

  /** طلب مؤكد أو شحن جزئي + فرد + يوجد مبلغ تحصيل/مقدم */
  get showCollectionCompanyField(): boolean {
    if (this.customer_type === 'شركة' || !this.orderSnap) {
      return false;
    }
    if (!['طلب مؤكد', 'شحن جزئي', 'طلب جديد'].includes(this.orderStatus)) {
      return false;
    }
    const net = parseFloat(this.orderSnap.net_total) || 0;
    const prepaid = parseFloat(this.orderSnap.prepaid_amount) || 0;
    return net - prepaid > 0.009 || prepaid > 0.009;
  }

  ngOnInit() {
    const id = this.route.snapshot.params['id'];
    this.order.getOrderById(id).subscribe((res: any) => {
      this.orderSnap = res;
      this.customer_type = res?.customer_type;
      this.orderStatus = res?.order_status || '';
      this.isOfferOrder = !!(res?.offer_id || res?.offer_debt_posted);
      if (res?.customer_type == 'شركة') {
        // لطلبات العروض: افتراضي أجل (الذمة على العميل) — شركة الشحن اختيارية
        if (this.isOfferOrder) {
          this.payment = 'أجل';
          this.paymentTypeActive = false;
        } else {
          this.paymentTypeActive = true;
        }
        const status = res?.order_products.every((elm: any) => elm.quantity - elm.shipped_quantity == 0);
        if (status) {
          this.completedShip = true;
          this.router.navigate([this.afterShipPath()]);
        }
      }

      this.lines = res.order_details?.shipping_line;
      this.recalcSplit();
    });

    this.company.shippingCompanySelect().subscribe((res: any) => {
      this.companies = res;
    });
    this.collectionCompanyService.select().subscribe((res: any) => {
      this.collectionCompanies = (res || []).filter((c: any) => c.status !== 'inactive');
    });
  }
  onShippingCompanySelected(item: any): void {
    this.selectedShippingCompanyId = item?.id != null ? String(item.id) : '';
    this.onShippingCompanyChange();
  }

  onShippingCompanyCleared(): void {
    this.selectedShippingCompanyId = '';
    this.onShippingCompanyChange();
  }

  onShippingCompanyChange(): void {
    this.updateCollectionSplitVisibility();
    this.recalcSplit();
  }

  onCollectionCompanyChange(): void {
    this.updateCollectionSplitVisibility();
    if (!this.showCollectionSplit) {
      this.manualSplitMode = false;
      this.manualShippingAmount = null;
      this.manualCollectionAmount = null;
    }
    this.recalcSplit();
  }

  private updateCollectionSplitVisibility(): void {
    const collId = this.selectedCollectionCompanyId;
    const shipId = this.selectedShippingCompanyId;
    this.showCollectionSplit = !!(collId && collId !== '' && collId !== shipId);
  }

  recalcSplit(): void {
    if (!this.orderSnap) {
      this.computedShippingAmount = 0;
      this.computedCollectionAmount = 0;
      this.splitError = '';
      return;
    }

    const net = parseFloat(this.orderSnap.net_total) || 0;
    const prepaid = parseFloat(this.orderSnap.prepaid_amount) || 0;

    if (this.manualSplitMode && this.manualShippingAmount != null && this.manualCollectionAmount != null) {
      const ship = this.manualShippingAmount || 0;
      const coll = this.manualCollectionAmount || 0;
      this.computedShippingAmount = ship;
      this.computedCollectionAmount = coll;
      const sum = Math.round((ship + coll) * 100) / 100;
      const roundedNet = Math.round(net * 100) / 100;
      if (Math.abs(sum - roundedNet) > 0.02) {
        this.splitError = `المجموع (${sum}) ≠ صافي الطلب (${roundedNet})`;
      } else {
        this.splitError = '';
      }
    } else if (this.showCollectionSplit && prepaid > 0) {
      this.computedCollectionAmount = Math.round(Math.min(prepaid, net) * 100) / 100;
      this.computedShippingAmount = Math.round(Math.max(0, net - this.computedCollectionAmount) * 100) / 100;
      this.splitError = '';
    } else {
      this.computedShippingAmount = Math.round(net * 100) / 100;
      this.computedCollectionAmount = 0;
      this.splitError = '';
    }
  }

  myFilter = (d: Date | null): boolean => {
    const today = new Date();
    const selectedDate = d || today;
    const timeDifference = Math.ceil((selectedDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    return timeDifference >= 0 && timeDifference <= 2;
  };

  date: any;
  dateSelected: boolean = false;
  OnDateChange(event: any) {
    const inputDate = new Date(event);
    this.date = this.datePipe.transform(inputDate, 'yyyy-M-d');
    this.dateSelected = true;
  }

  selectedFile: any;
  imgselect = false;
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.imgselect = true;
  }

  productsArray: any[] = [];
  shippStatus = true;

  getshippedquantity(data: any) {
    this.shippStatus = data.shippstatus;
    this.productsArray = data.shipProducts.map((elm: any) => {
      return { id: elm.id, quantity: elm.requiredQuantity };
    });
  }

  payment!: string;
  cashType = false;
  cashval = false;
  cash = 0;

  paymentType(e: any) {
    if (e.target.id == 'paymentType') {
      this.payment = e.target.value;
      if (this.payment == 'أجل' || this.payment == 'نقدي') {
        this.paymentTypeActive = false;
      }
      if (this.payment == 'نقدي') {
        this.cashType = true;
      } else if (this.payment == 'أجل') {
        this.cashType = false;
        this.cashval = false;
        this.cash = 0;
      }
    }
    if (e.target.id == 'cash') {
      this.cash = e.target.value;
      if (this.cash > 0) {
        this.cashType = false;
        this.cashval = true;
      } else {
        this.cashType = true;
      }
    }
  }

  canSubmitShip(): boolean {
    if (!this.dateSelected || !this.shippStatus || this.paymentTypeActive || this.cashType || !!this.splitError) {
      return false;
    }
    if (this.isOfferOrder) {
      if (this.payment === 'نقدي' && !this.selectedShippingCompanyId) {
        return false;
      }
      return true;
    }
    return !!this.selectedShippingCompanyId;
  }

  private afterShipPath(): string {
    return this.isOfferOrder
      ? '/dashboard/shipping/offer-orders'
      : '/dashboard/shipping/listorders';
  }

  shipOrder(form: any) {
    if (!this.canSubmitShip()) {
      return;
    }
    if (this.isOfferOrder && this.payment === 'نقدي' && !this.selectedShippingCompanyId) {
      return;
    }

    const formData = new FormData();
    if (this.selectedShippingCompanyId) {
      formData.append('company_id', this.selectedShippingCompanyId);
    }

    if (this.selectedCollectionCompanyId && this.selectedCollectionCompanyId !== '') {
      formData.append('collection_provider_type', 'collection_company');
      formData.append('collection_provider_id', this.selectedCollectionCompanyId);
      const coll = this.collectionCompanies.find((c: any) => String(c.id) === String(this.selectedCollectionCompanyId));
      if (coll?.linked_shipping_company_id) {
        formData.append('collection_company_id', String(coll.linked_shipping_company_id));
      }
    }

    if (this.manualSplitMode && this.manualShippingAmount != null && this.manualCollectionAmount != null) {
      formData.append('shipping_receivable_amount', String(this.manualShippingAmount));
      formData.append('collection_receivable_amount', String(this.manualCollectionAmount));
    }

    formData.append('date', this.date);
    formData.append('shippment_number', form.value.shippment_number || '');
    formData.append('productsToShip', JSON.stringify(this.productsArray));
    const id = this.route.snapshot.params['id'];

    if (this.customer_type == 'شركة') {
      formData.append('payment_way', this.payment || 'أجل');
      if (this.payment == 'نقدي') {
        formData.append('cash', String(this.cash));
      }
    }

    this.order.shipOrder(formData, id).subscribe((res: any) => {
      if (res.message === 'success') {
        this.router.navigate([this.afterShipPath()]);
      }
    });
  }
}

