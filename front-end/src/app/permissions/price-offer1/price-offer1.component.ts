import { Component, OnDestroy, OnInit } from '@angular/core';
import { OfferService } from '../services/offer.service';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription } from 'rxjs';
import { CompaniesService } from 'src/app/shipping/services/companies.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-price-offer1',
  templateUrl: './price-offer1.component.html',
  styleUrls: ['./price-offer1.component.css']
})
export class PriceOffer1Component implements OnInit, OnDestroy {
  id: any = null;
  saving = false;
  /** عميل شركة قادم من صفحة عملاء الشركات */
  sourceCompanyId: number | null = null;
  sourceCompanyName = '';
  private routeSub?: Subscription;

  rows: { category_name: string; category_quantity: number; new_category_price: number; old_category_price: number; total_price: number }[] = [
    { category_name: 'category', category_quantity: 0,  old_category_price:0, new_category_price: 0, total_price: 0 },
  ];

  addRow() {
    this.rows.push({ category_name: 'category', category_quantity: 0, old_category_price:0, new_category_price: 0, total_price: 0 });
  }

  updateTotal(row: any) {
    row.total_price = row.category_quantity * row.new_category_price;
    this.calc(arguments)
  }

  errorform:boolean= false;
  errorMessage!:string;
  dateFrom!:any;
  dateTo!:any;
  note!:any;

  phone_number:string="+201118127345";
  email:string="info@magalis-egypt.com";
  quote!:string;
  contact_person!:string;
  client_phone!:string;

  constructor(
    private offerService: OfferService,
    private companiesService: CompaniesService,
    private router: Router,
    private activatedRoute: ActivatedRoute,
  ) {}

  ngOnInit() {
    const initialId = this.activatedRoute.snapshot.queryParamMap.get('id');
    if (initialId) {
      this.id = initialId;
      this.loadOffer(this.id);
    }

    this.routeSub = this.activatedRoute.queryParamMap.subscribe((params) => {
      const nextId = params.get('id');
      if (nextId && nextId !== String(this.id || '')) {
        this.id = nextId;
        this.loadOffer(this.id);
      } else if (!nextId) {
        this.id = null;
      }

      const companyId = params.get('company_id');
      if (companyId && !nextId) {
        this.loadSourceCompany(companyId);
      } else if (!companyId) {
        this.sourceCompanyId = null;
        this.sourceCompanyName = '';
      }
    });
  }

  ngOnDestroy(): void {
    this.routeSub?.unsubscribe();
  }

  private loadSourceCompany(companyId: string) {
    const id = Number(companyId);
    if (!id) {
      return;
    }
    this.sourceCompanyId = id;
    this.companiesService.getCompany(id).subscribe({
      next: (res: any) => {
        const company = res?.data?.[0] || res?.data?.data?.[0];
        if (!company) {
          this.errorform = true;
          this.errorMessage = 'تعذر تحميل بيانات عميل الشركة';
          return;
        }
        this.sourceCompanyName = company.name || '';
        this.quote = company.name || this.quote;
        this.client_phone = company.phone1 || company.phone2 || this.client_phone || '';
        this.contact_person = this.contact_person || company.name || 'مسؤول الشركة';
      },
      error: () => {
        this.errorform = true;
        this.errorMessage = 'تعذر تحميل بيانات عميل الشركة';
      },
    });
  }

  private loadOffer(id: any) {
    this.offerService.getOfferById(id).subscribe({
      next: (res: any) => {
        this.rows = Array.isArray(res.category) && res.category.length
          ? res.category
          : [{ category_name: 'category', category_quantity: 0, old_category_price: 0, new_category_price: 0, total_price: 0 }];
        this.dateFrom = res.dateFrom;
        this.dateTo = res.dateTo;
        this.note = res.note || '';
        this.quote = res.quote;
        this.contact_person = res.contact_person || '';
        this.client_phone = res.client_phone || '';
        this.phone_number = res.phone_number;
        this.email = res.email;
        this.transportation = res.transportation;
        this.vat = res.vat;
        this.changedVat = true;
        this.calc(null);
      },
      error: () => {
        this.errorform = true;
        this.errorMessage = 'تعذر تحميل عرض السعر للتعديل';
      },
    });
  }

  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateFrom = target.value;
  }
  onDateToChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateTo = target.value;
  }

  subtotal:number=0;
  vat:number=0;
  vatPercent:number=14;
  total:number= 0;
  transportation:number= 0;
  clearTransportation:boolean = true;
  cleardiv:boolean = true;
  changedVat:boolean=false;

  calc(e){
    this.subtotal = 0;
    this.rows.forEach(elm=> this.subtotal += Number(elm.total_price) || 0);
    if (e?.target?.id == 'vat') {
      this.changedVat = true;
    }
    if (!this.changedVat) {
      this.vat = this.subtotal * this.vatPercent/100  + (this.transportation * this.vatPercent/100);
    }
    this.total = this.subtotal + this.vat + this.transportation;
  }
  clearinp(){
    this.vat = 0;
    this.cleardiv = false;
    this.changedVat = true;
    this.calc(arguments);
  }
  clearTransportationFn(){
    this.transportation = 0;
    this.clearTransportation = false;
    this.calc(arguments);
  }

  clientFieldsInvalid = false;

  private validateClientFields(): boolean {
    const quote = this.quote?.trim() || '';
    const contact = this.contact_person?.trim() || '';
    const phone = this.client_phone?.trim() || '';
    if (!quote || !contact || !phone) {
      this.errorform = true;
      this.clientFieldsInvalid = true;
      this.errorMessage = 'بيانات العميل إجبارية: اسم العميل، مسؤول التواصل، ورقم الهاتف';
      return false;
    }
    this.errorform = false;
    this.clientFieldsInvalid = false;
    this.errorMessage = '';
    return true;
  }

  private goToOfferDetails(offerId: number | string) {
    this.router.navigate(['/dashboard/permissions/offer1', offerId]);
  }

  private finishCreate(offerId: number | string) {
    if (!this.sourceCompanyId || this.id) {
      this.saving = false;
      this.goToOfferDetails(offerId);
      return;
    }

    if (Number(this.total) <= 0) {
      this.saving = false;
      Swal.fire({
        icon: 'info',
        title: 'تم حفظ العرض',
        text: 'أضف بنوداً بإجمالي أكبر من صفر ثم اربط العميل من صفحة التفاصيل لترحيل المديونية.',
      }).then(() => this.goToOfferDetails(offerId));
      return;
    }

    // ربط عميل الشركة وترحيل المديونية بعد الحفظ
    this.offerService.linkClient(offerId, this.sourceCompanyId).subscribe({
      next: () => {
        this.saving = false;
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: 'تم إنشاء العرض وربطه بالعميل وترحيل المديونية',
          showConfirmButton: false,
          timer: 2200,
        });
        this.goToOfferDetails(offerId);
      },
      error: (err) => {
        this.saving = false;
        Swal.fire({
          icon: 'warning',
          title: 'تم حفظ العرض',
          text: err?.error?.message || 'تعذر الربط التلقائي — يمكنك الربط من صفحة التفاصيل',
        }).then(() => this.goToOfferDetails(offerId));
      },
    });
  }

  submitform(){
    if (this.saving) {
      return;
    }
    if (!this.validateClientFields()) {
      return;
    }

    const payload: any = {
      dateFrom: this.dateFrom,
      dateTo: this.dateTo || this.dateFrom,
      quote: this.quote.trim(),
      contact_person: this.contact_person.trim(),
      client_phone: this.client_phone.trim(),
      offer: 'offer1',
      categories: this.rows,
      subtotal: this.subtotal,
      vat: this.vat,
      transportation: this.transportation,
      total: this.total,
      email: this.email,
      note: this.note,
      phone_number: this.phone_number,
    };

    if (this.id) {
      payload.id = this.id;
    }

    this.saving = true;
    this.offerService.addOffer(payload).subscribe({
      next: (result: any) => {
        const offerId = result?.id || this.id;
        if (!offerId) {
          this.saving = false;
          this.router.navigateByUrl('/dashboard/permissions/priceoffer');
          return;
        }
        if (this.id) {
          this.saving = false;
          this.goToOfferDetails(offerId);
          return;
        }
        this.finishCreate(offerId);
      },
      error: (err) => {
        this.saving = false;
        this.errorform = true;
        this.errorMessage = err?.error?.message
          || err?.error?.errors?.quote?.[0]
          || err?.error?.errors?.contact_person?.[0]
          || err?.error?.errors?.client_phone?.[0]
          || 'تعذر حفظ عرض السعر';
      },
    });
  }

}
