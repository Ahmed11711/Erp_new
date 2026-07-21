import { HttpClient } from '@angular/common/http';
import { Component, OnInit } from '@angular/core';
import { FormControl, FormGroup } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { OfferService } from 'src/app/permissions/services/offer.service';
import { CompaniesService } from '../services/companies.service';
import { OrderSourceService } from '../services/order-source.service';
import { ShippingWayService } from '../services/shipping-way.service';
import { dateToIsoString } from 'src/app/shared/date/date-utils';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-convert-offer-to-order',
  templateUrl: './convert-offer-to-order.component.html',
  styleUrls: ['./convert-offer-to-order.component.css'],
})
export class ConvertOfferToOrderComponent implements OnInit {
  offerId!: string;
  offer: any = null;
  loading = true;
  saving = false;
  creating = false;
  loadError = '';

  companies: any[] = [];
  shippingWays: any[] = [];
  orderSources: any[] = [];
  productGaps: any[] = [];
  selectedCompany: any = null;
  catword = 'name';

  showCreateForm = false;
  location: any[] = [];
  cities: any[] = [];
  governName = false;
  newCompany = {
    name: '',
    phone1: '',
    phone2: '',
    governorate: '',
    city: '',
    address: '',
    tel: '',
  };

  deliveryModes = [
    { value: 'later', label: 'الشحن لاحقاً / يُحدد عند التنفيذ' },
    { value: 'self_pickup', label: 'استلام ذاتي من العميل' },
    { value: 'client_rep', label: 'مندوب تابع للعميل' },
    { value: 'system', label: 'شحن عبر السيستم' },
  ];

  lines: Array<{
    offer_line_id: number;
    offer_name: string;
    category_id: number | null;
    category_name: string;
    quantity: number;
    price: number;
    total: number;
    status: string;
    match_type?: string;
  }> = [];

  form = new FormGroup({
    order_date: new FormControl(dateToIsoString(new Date())),
    delivery_date: new FormControl(null),
    shipping_method_id: new FormControl(null),
    order_source_id: new FormControl(null),
    shipping_cost: new FormControl(0),
    prepaid_amount: new FormControl(0),
    discount: new FormControl(0),
    vat: new FormControl(0),
    delivery_mode: new FormControl('later'),
    shipping_note: new FormControl(''),
    order_notes: new FormControl(''),
    governorate: new FormControl(''),
    city: new FormControl(''),
    address: new FormControl(''),
    customer_phone_1: new FormControl(''),
  });

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private http: HttpClient,
    private offerService: OfferService,
    private companiesService: CompaniesService,
    private orderSource: OrderSourceService,
    private shippingWay: ShippingWayService,
  ) {}

  ngOnInit(): void {
    this.offerId = this.route.snapshot.paramMap.get('offerId') || '';
    if (!this.offerId) {
      this.loadError = 'رقم العرض غير موجود';
      this.loading = false;
      return;
    }

    this.companiesService.data().subscribe({ next: (r) => (this.companies = r || []) });
    this.shippingWay.data().subscribe({ next: (r) => (this.shippingWays = r || []) });
    this.orderSource.data().subscribe({ next: (r) => (this.orderSources = r || []) });
    this.http.get('assets/egypt/governorates.json').subscribe((data: any) => (this.location = data || []));
    this.http.get('assets/egypt/cities.json').subscribe((data: any) => {
      this.cities = (data || []).filter((elem: any) => elem.governorate_id == 1);
    });

    this.loadOffer();
  }

  get canCreateCompany(): boolean {
    return !this.selectedCompany?.id && !this.offer?.customer_company_id && !this.alreadyDebtPosted;
  }

  loadOffer() {
    this.loading = true;
    this.offerService.getOfferById(this.offerId).subscribe({
      next: (res: any) => {
        this.offer = res;
        if (res?.converted_order_id) {
          this.loadError = `تم تحويل هذا العرض مسبقاً إلى الطلب رقم ${res.converted_order_id}`;
          this.loading = false;
          return;
        }

        this.form.patchValue({
          vat: Number(res.vat) || 0,
          shipping_cost: Number(res.transportation) || 0,
          address: res.customer_company?.address || '',
          governorate: res.customer_company?.governorate || '',
          city: res.customer_company?.city || '',
          customer_phone_1: res.customer_company?.phone1 || res.client_phone || '',
          order_notes: res.note || '',
        });

        if (res.customer_company) {
          this.selectedCompany = res.customer_company;
        }

        this.offerService.getProductGaps(this.offerId).subscribe({
          next: (gaps: any) => {
            this.productGaps = gaps?.lines || [];
            this.lines = (res.category || []).map((line: any) => {
              const gap = this.productGaps.find((g) => g.offer_line_id === line.id);
              const qty = Number(line.category_quantity) || 1;
              const price = Number(line.new_category_price) || 0;
              return {
                offer_line_id: line.id,
                offer_name: line.category_name,
                category_id: gap?.category?.id || null,
                category_name: gap?.category?.category_name || '',
                quantity: qty,
                price,
                total: Number(line.total_price) || qty * price,
                status: gap?.status || 'missing_category',
                match_type: gap?.match_type,
              };
            });
            this.loading = false;
          },
          error: () => {
            this.lines = (res.category || []).map((line: any) => ({
              offer_line_id: line.id,
              offer_name: line.category_name,
              category_id: null,
              category_name: '',
              quantity: Number(line.category_quantity) || 1,
              price: Number(line.new_category_price) || 0,
              total: Number(line.total_price) || 0,
              status: 'missing_category',
            }));
            this.loading = false;
          },
        });
      },
      error: () => {
        this.loadError = 'تعذر تحميل عرض السعر';
        this.loading = false;
      },
    });
  }

  onCompanySelected(c: any) {
    this.selectedCompany = c;
    this.form.patchValue({
      customer_phone_1: c.phone1 || this.form.value.customer_phone_1,
      governorate: c.governorate || this.form.value.governorate,
      city: c.city || this.form.value.city,
      address: c.address || this.form.value.address,
    });
  }

  resetCompany() {
    this.selectedCompany = this.offer?.customer_company || null;
  }

  private normalizePhone(phone: string): string {
    let n = String(phone || '').trim().replace(/\s+/g, '');
    if (n.startsWith('+2')) {
      n = n.substring(2);
    } else if (n.startsWith('2') && n.length > 10) {
      n = n.substring(1);
    }
    return n;
  }

  openCreateForm(): void {
    if (!this.canCreateCompany && !this.showCreateForm) {
      return;
    }
    this.showCreateForm = true;
    const offerPhone = this.normalizePhone(this.offer?.client_phone || '');
    const formPhone = this.normalizePhone(this.form.value.customer_phone_1 || '');
    this.newCompany = {
      name: (this.offer?.quote || '').trim(),
      phone1: formPhone || offerPhone,
      phone2: '',
      governorate: this.form.value.governorate || '',
      city: this.form.value.city || '',
      address: (this.form.value.address || '').trim()
        || (this.offer?.contact_person ? `مسؤول التواصل: ${this.offer.contact_person}` : ''),
      tel: '',
    };
    this.governName = this.newCompany.governorate === 'القاهرة';
  }

  cancelCreateForm(): void {
    this.showCreateForm = false;
  }

  clearSelectedCompany(): void {
    this.selectedCompany = null;
    this.form.patchValue({
      customer_phone_1: this.offer?.client_phone || '',
      governorate: '',
      city: '',
      address: '',
    });
  }

  onGovernChange(): void {
    this.governName = this.newCompany.governorate === 'القاهرة';
    if (!this.governName) {
      this.newCompany.city = '';
    }
  }

  createAndLinkCompany(): void {
    if (!this.offer?.id || this.creating) {
      return;
    }
    if (!this.newCompany.name?.trim()) {
      Swal.fire('تنبيه', 'اسم العميل مطلوب', 'warning');
      return;
    }
    const phone1 = this.normalizePhone(this.newCompany.phone1);
    if (!phone1) {
      Swal.fire('تنبيه', 'رقم الموبايل مطلوب', 'warning');
      return;
    }
    if (!/^01\d{9}$/.test(phone1)) {
      Swal.fire('تنبيه', 'أدخل موبايل صحيح (11 رقم يبدأ بـ 01)', 'warning');
      return;
    }
    if (this.newCompany.phone2?.trim()) {
      const phone2 = this.normalizePhone(this.newCompany.phone2);
      if (!/^01\d{9}$/.test(phone2)) {
        Swal.fire('تنبيه', 'الموبايل الإضافي غير صحيح', 'warning');
        return;
      }
      this.newCompany.phone2 = phone2;
    }
    if (!this.newCompany.governorate?.trim()) {
      Swal.fire('تنبيه', 'المحافظة مطلوبة', 'warning');
      return;
    }
    if (!this.newCompany.address?.trim()) {
      this.newCompany.address = 'غير محدد';
    }
    this.newCompany.phone1 = phone1;

    const total = Number(this.offer.total) || 0;
    Swal.fire({
      title: 'إنشاء عميل من بيانات العرض؟',
      html: `سيتم إنشاء <b>${this.newCompany.name}</b> كعميل شركة`
        + (total > 0 ? ` وترحيل مديونية بقيمة <b>${total}</b>` : '')
        + ' ثم يمكنك متابعة التحويل لطلب.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'إنشاء وربط',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#82225e',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.creating = true;
      this.offerService.createAndLinkClient(this.offer.id, {
        name: this.newCompany.name.trim(),
        phone1: this.normalizePhone(this.newCompany.phone1),
        phone2: this.newCompany.phone2 || undefined,
        governorate: this.newCompany.governorate,
        city: this.newCompany.city || undefined,
        address: this.newCompany.address.trim(),
        tel: this.newCompany.tel || undefined,
      }).subscribe({
        next: (res: any) => {
          this.creating = false;
          this.showCreateForm = false;
          this.offer = res.offer || this.offer;
          this.selectedCompany = res.company || res.offer?.customer_company || null;
          this.form.patchValue({
            customer_phone_1: this.selectedCompany?.phone1 || this.form.value.customer_phone_1,
            governorate: this.selectedCompany?.governorate || this.form.value.governorate,
            city: this.selectedCompany?.city || this.form.value.city,
            address: this.selectedCompany?.address || this.form.value.address,
          });
          this.companiesService.data().subscribe({
            next: (list) => (this.companies = list || []),
          });
          Swal.fire('تم', res.message || 'تم إنشاء العميل وربطه بالعرض', 'success');
        },
        error: (err) => {
          this.creating = false;
          const msg = err?.error?.message
            || err?.error?.errors?.name?.[0]
            || err?.error?.errors?.phone1?.[0]
            || 'فشل إنشاء العميل';
          Swal.fire('خطأ', msg, 'error');
        },
      });
    });
  }

  recalcLine(line: any) {
    line.total = Number(line.quantity || 0) * Number(line.price || 0);
  }

  get productsTotal(): number {
    return this.lines.reduce((s, l) => s + (Number(l.total) || 0), 0);
  }

  get netTotal(): number {
    const ship = Number(this.form.value.shipping_cost) || 0;
    const vat = Number(this.form.value.vat) || 0;
    const prepaid = Number(this.form.value.prepaid_amount) || 0;
    const discount = Number(this.form.value.discount) || 0;
    return this.productsTotal + ship + vat - prepaid - discount;
  }

  get unmatchedCount(): number {
    return this.lines.filter((l) => !l.category_id).length;
  }

  get alreadyDebtPosted(): boolean {
    return !!this.offer?.debt_posted_at;
  }

  submit() {
    if (this.saving) {
      return;
    }
    if (!this.selectedCompany?.id && !this.offer?.customer_company_id) {
      Swal.fire('تنبيه', 'اختر عميل شركة أولاً', 'warning');
      return;
    }
    if (this.unmatchedCount > 0) {
      Swal.fire(
        'تنبيه',
        `يوجد ${this.unmatchedCount} منتج غير مطابق لصنف. أنشئ الأصناف الناقصة من تفاصيل العرض أولاً.`,
        'warning'
      );
      return;
    }

    const mode = this.form.value.delivery_mode || 'later';
    const shippingNote = (this.form.value.shipping_note || '').trim();
    const companyId = this.selectedCompany?.id || this.offer.customer_company_id;
    const debtMsg = this.alreadyDebtPosted
      ? 'المديونية مرحّلة مسبقاً من العرض ولن تُكرَّر.'
      : `سيتم ترحيل مديونية بقيمة ${this.offer.total} على العميل.`;

    Swal.fire({
      title: 'تحويل العرض إلى طلب؟',
      html: `سيتم إنشاء طلب شركة جديد وربطه بالعميل.<br>${debtMsg}`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'تحويل',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#82225e',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      const payload = {
        customer_company_id: companyId,
        shipping_method_id: this.form.value.shipping_method_id,
        order_source_id: this.form.value.order_source_id,
        order_date: this.form.value.order_date,
        delivery_date: this.form.value.delivery_date || null,
        shipping_cost: Number(this.form.value.shipping_cost) || 0,
        prepaid_amount: Number(this.form.value.prepaid_amount) || 0,
        discount: Number(this.form.value.discount) || 0,
        vat: Number(this.form.value.vat) || 0,
        total_invoice: this.productsTotal,
        net_total: this.netTotal,
        delivery_mode: mode,
        shipping_note: shippingNote,
        order_notes: this.form.value.order_notes || '',
        governorate: this.form.value.governorate || 'غير محدد',
        city: this.form.value.city || null,
        address: this.form.value.address,
        customer_name: this.selectedCompany?.name || this.offer.quote,
        customer_phone_1: this.form.value.customer_phone_1,
        order_details: this.lines.map((l) => ({
          category_id: l.category_id,
          quantity: l.quantity,
          price: l.price,
          total: l.total,
          special_details: '',
          offer_line_id: l.offer_line_id,
        })),
      };

      this.saving = true;
      this.offerService.convertToOrder(this.offerId, payload).subscribe({
        next: (res: any) => {
          this.saving = false;
          Swal.fire('تم', res.message || 'تم التحويل', 'success').then(() => {
            this.router.navigate(['/dashboard/shipping/offer-orders']);
          });
        },
        error: (err) => {
          this.saving = false;
          Swal.fire('خطأ', err?.error?.message || 'فشل التحويل', 'error');
        },
      });
    });
  }
}
