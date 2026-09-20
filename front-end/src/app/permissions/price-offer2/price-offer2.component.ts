import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { OfferService } from '../services/offer.service';
import { MatDialog } from '@angular/material/dialog';
import { AngularEditorComponent } from 'src/app/shared/angular-editor/angular-editor.component';
import { Subscription } from 'rxjs';
import { CompaniesService } from 'src/app/shipping/services/companies.service';
import { CorparatesSalesService } from 'src/app/corparates-sales/services/corparates-sales.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { calculateOfferTotals } from 'src/app/shared/utils/vat';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-price-offer2',
  templateUrl: './price-offer2.component.html',
  styleUrls: ['./price-offer2.component.css']
})
export class PriceOffer2Component implements OnInit, OnDestroy {
  note!: any;
  saving = false;
  /** عميل شركة قادم من صفحة عملاء الشركات */
  sourceCompanyId: number | null = null;
  sourceCompanyName = '';
  sourceLeadId: number | null = null;
  sourceLeadName = '';
  private routeSub?: Subscription;
  id: any = null;
  convertedOrderId: number | null = null;

  rows: {
    category_name: string;
    category_quantity: number;
    new_category_price: number;
    old_category_price: number;
    total_price: number;
    description: string;
    image?: File;
    imageName?: string;
    imageUrl?: string;
    category_image?: string;
  }[] = [
    {
      category_name: 'product',
      description: 'description',
      category_quantity: 0,
      old_category_price: 0,
      new_category_price: 0,
      total_price: 0
    },
  ];

  addRow() {
    if (!this.canEditItems()) {
      return;
    }
    this.rows.push({ category_name: 'product', description: 'description', category_quantity: 0, old_category_price:0, new_category_price: 0, total_price: 0 });
  }

  removeRow(index: number) {
    if (!this.canEditItems()) {
      return;
    }
    if (this.rows.length <= 1) {
      Swal.fire({ icon: 'info', text: 'يجب الإبقاء على صنف واحد على الأقل في العرض.' });
      return;
    }
    this.rows.splice(index, 1);
    this.calc(null);
  }

  /** إنشاء عرض جديد متاح لمن يصل للصفحة؛ التعديل يحتاج offers.edit */
  canEditItems(): boolean {
    if (!this.id) {
      return true;
    }
    return this.rbac.can('offers.edit') || this.rbac.can('system.rbac');
  }

  openFileInput(index: number) {
    const fileInput = document.getElementById('fileInput' + index) as HTMLInputElement;
    if (fileInput) {
      fileInput.click();
    }
  }

  onFileChanged(event: any, index: number) {
    const file = event.target.files[0];
    if (file) {
      this.rows[index].image = file;
      this.rows[index].imageName = file.name;

      const reader = new FileReader();
      reader.onload = () => {
        this.rows[index].imageUrl = reader.result as string;
      };
      reader.readAsDataURL(file);
    }
  }

  updateTotal(row: any) {
    row.total_price = row.category_quantity * row.new_category_price;
    this.calc(arguments);
  }

  errorform: boolean = false;
  errorMessage!: string;
  dateFrom!: any;
  dateTo!: any;

  phone_number: string = '+201118127345';
  email: string = 'info@magalis-egypt.com';
  quote!: string;
  contact_person!: string;
  client_phone!: string;
  title!: string;

  constructor(
    private offerService: OfferService,
    private companiesService: CompaniesService,
    private leadsService: CorparatesSalesService,
    private router: Router,
    private dialog: MatDialog,
    private cd: ChangeDetectorRef,
    private activatedRoute: ActivatedRoute,
    private rbac: RbacService,
  ) {}

  openEditorDialog(description, i) {
    const dialogRef = this.dialog.open(AngularEditorComponent, {
      width: '90%',
      data: { htmlContent: description }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.rows[i].description = result;
        this.cd.detectChanges();
      }
    });
  }

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
        this.convertedOrderId = null;
      }

      const companyId = params.get('company_id');
      if (companyId && !nextId) {
        this.loadSourceCompany(companyId);
      } else if (!companyId) {
        this.sourceCompanyId = null;
        this.sourceCompanyName = '';
      }

      const leadId = params.get('lead_id');
      if (leadId && !nextId) {
        this.loadSourceLead(leadId);
      } else if (!leadId && !this.id) {
        this.sourceLeadId = null;
        this.sourceLeadName = '';
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

  private loadSourceLead(leadId: string) {
    const id = Number(leadId);
    if (!id) {
      return;
    }
    this.sourceLeadId = id;
    this.leadsService.getLeadsById(id).subscribe({
      next: (lead: any) => {
        if (!lead?.id) {
          this.errorform = true;
          this.errorMessage = 'تعذر تحميل بيانات الـ Lead';
          return;
        }
        this.sourceLeadName = lead.company_name || '';
        this.quote = lead.company_name || this.quote;
        const contact = Array.isArray(lead.contact) ? lead.contact[0] : null;
        this.contact_person = contact?.name || lead.contact_title || this.contact_person || lead.company_name || '';
        const phone = contact?.phones?.[0];
        if (phone) {
          this.client_phone = `${phone.dial_code || ''} ${phone.contact_number || ''}`.trim();
        }
      },
      error: () => {
        this.errorform = true;
        this.errorMessage = 'تعذر تحميل بيانات الـ Lead';
      },
    });
  }

  private loadOffer(id: any) {
    if (!this.canEditItems()) {
      Swal.fire('غير مسموح', 'ليس لديك صلاحية تعديل عروض الأسعار أو أصنافها', 'warning')
        .then(() => this.router.navigate(['/dashboard/permissions/priceoffer']));
      return;
    }

    this.offerService.getOfferById(id).subscribe({
      next: (res: any) => {
        if (res?.debt_posted_at && !res?.converted_order_id) {
          Swal.fire('غير مسموح', 'لا يمكن تعديل عرض تم ترحيل مديونيته — راجع المحاسبة أولاً', 'warning')
            .then(() => this.router.navigate(['/dashboard/permissions/offer2', id]));
          return;
        }
        this.convertedOrderId = res?.converted_order_id ? Number(res.converted_order_id) : null;
        if (res?.corporate_sales_lead_id) {
          this.sourceLeadId = Number(res.corporate_sales_lead_id);
          this.sourceLeadName = res?.lead?.company_name || this.sourceLeadName;
        }
        this.rows = Array.isArray(res.category) && res.category.length
          ? res.category
          : [{
              category_name: 'product',
              description: 'description',
              category_quantity: 0,
              old_category_price: 0,
              new_category_price: 0,
              total_price: 0,
            }];
        this.dateFrom = res.dateFrom;
        this.dateTo = res.dateTo;
        this.quote = res.quote;
        this.contact_person = res.contact_person || '';
        this.client_phone = res.client_phone || '';
        this.phone_number = res.phone_number;
        this.email = res.email;
        this.transportation = res.transportation;
        this.vat = res.vat;
        this.title = res.title;
        this.note = res.note || '';
        this.changedVat = !(Number(res.vat) > 0);
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

  subtotal: number = 0;
  vat: number = 0;
  vatPercent: number = 14;
  total: number = 0;
  transportation: number = 0;
  clearTransportation: boolean = true;
  cleardiv: boolean = true;
  changedVat: boolean = false;

  calc(e) {
    this.subtotal = 0;
    this.rows.forEach(elm => this.subtotal += Number(elm.total_price) || 0);
    this.transportation = Number(this.transportation) || 0;
    if (e?.target?.id == 'vat') {
      this.changedVat = !(Number(this.vat) > 0);
    }
    const totals = calculateOfferTotals(this.subtotal, this.transportation, this.changedVat ? 0 : 1);
    this.vat = totals.vat;
    this.total = totals.total;
  }

  clientFieldsInvalid = false;

  private validateClientFields(): boolean {
    const quote = this.quote?.trim() || '';
    const contact = this.contact_person?.trim() || '';
    if (!quote || !contact) {
      this.errorform = true;
      this.clientFieldsInvalid = true;
      this.errorMessage = 'بيانات العميل إجبارية: اسم العميل ومسؤول التواصل';
      return false;
    }
    this.errorform = false;
    this.clientFieldsInvalid = false;
    this.errorMessage = '';
    return true;
  }

  private async confirmConvertedOrderUpdate(): Promise<boolean | null> {
    if (!this.id || !this.convertedOrderId) {
      return false;
    }

    const choice = await Swal.fire({
      title: 'تعديل الطلب المحوّل؟',
      html: `هذا العرض محوّل إلى الطلب رقم <b>#${this.convertedOrderId}</b>.<br>هل تريد تطبيق التعديلات على الطلب أيضاً؟`,
      icon: 'question',
      showDenyButton: true,
      showCancelButton: true,
      confirmButtonText: 'نعم، حدّث العرض والطلب',
      denyButtonText: 'لا، حدّث العرض فقط',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#82225e',
    });

    if (choice.isDismissed) {
      return null;
    }

    return choice.isConfirmed;
  }

  private afterOfferSaved(result: any, offerId: number | string) {
    this.saving = false;
    if (result?.needs_product_link) {
      this.askLinkUnmatchedProducts(result, offerId);
      return;
    }
    const goDetails = (openMatch = false) => this.router.navigate(
      ['/dashboard/permissions/offer2', offerId],
      { queryParams: openMatch ? { match: '1' } : undefined },
    );
    if (result?.order_updated) {
      Swal.fire({
        icon: 'success',
        title: 'تم التعديل',
        text: result.order_update_message || result.message || 'تم تعديل العرض وتحديث الطلب المحوّل',
      }).then(() => goDetails());
      return;
    }
    if (result?.order_update_message) {
      Swal.fire({
        icon: 'warning',
        title: 'تم حفظ العرض',
        text: result.order_update_message,
      }).then(() => goDetails());
      return;
    }
    goDetails();
  }

  private askLinkUnmatchedProducts(result: any, offerId: number | string) {
    const names = (result?.unmatched_products || []).filter((n: string) => !!n);
    const list = names.length
      ? `<br><b>${names.map((n: string) => this.escapeHtml(n)).join('، ')}</b>`
      : '';
    const goDetails = (openMatch = false) => this.router.navigate(
      ['/dashboard/permissions/offer2', offerId],
      { queryParams: openMatch ? { match: '1' } : undefined },
    );
    Swal.fire({
      title: 'أصناف غير موجودة',
      html: `بعض أصناف العرض غير موجودة في النظام.${list}<br>هل تريد ربطها في الطلب الآن أم لاحقاً؟`,
      icon: 'question',
      showDenyButton: true,
      confirmButtonText: 'ربطها في الطلب',
      denyButtonText: 'لاحقاً',
      confirmButtonColor: '#82225e',
      allowOutsideClick: false,
    }).then((choice) => {
      if (!choice.isConfirmed) {
        goDetails(true);
        return;
      }
      this.saving = true;
      this.offerService.syncConvertedOrder(offerId, true).subscribe({
        next: (res: any) => {
          this.saving = false;
          if (res?.order_updated) {
            Swal.fire('تم', res.message || 'تم ربط الأصناف وتحديث الطلب', 'success')
              .then(() => goDetails());
            return;
          }
          Swal.fire({
            icon: 'warning',
            title: 'تم حفظ العرض',
            text: res?.message || 'تعذر ربط كل الأصناف. يمكنك المطابقة لاحقاً من صفحة التفاصيل.',
          }).then(() => goDetails(true));
        },
        error: (err) => {
          this.saving = false;
          Swal.fire({
            icon: 'warning',
            title: 'تم حفظ العرض',
            text: err?.error?.message || 'تعذر ربط الأصناف في الطلب. يمكنك المطابقة لاحقاً.',
          }).then(() => goDetails(true));
        },
      });
    });
  }

  private escapeHtml(value: string): string {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  async submitform() {
    if (this.saving) {
      return;
    }
    if (this.id && !this.canEditItems()) {
      Swal.fire('غير مسموح', 'ليس لديك صلاحية تعديل عروض الأسعار أو أصنافها', 'warning');
      return;
    }
    if (!this.validateClientFields()) {
      return;
    }

    const updateConvertedOrder = await this.confirmConvertedOrderUpdate();
    if (updateConvertedOrder === null) {
      return;
    }

    const formData = new FormData();

    formData.append('dateFrom', this.dateFrom);
    formData.append('dateTo', this.dateTo || this.dateFrom);
    formData.append('quote', this.quote.trim());
    formData.append('contact_person', this.contact_person.trim());
    formData.append('client_phone', (this.client_phone || '').trim());
    if (this.note && this.note.length > 0) {
      formData.append('note', this.note);
    }
    if (this.title && this.title.length > 2) {
      formData.append('title', this.title);
    }
    if (this.id) {
      formData.append('id', String(this.id));
    }
    formData.append('offer', 'offer2');
    formData.append('update_converted_order', updateConvertedOrder ? '1' : '0');
    if (this.sourceLeadId) {
      formData.append('corporate_sales_lead_id', String(this.sourceLeadId));
    }

    this.rows.forEach((row, index) => {
      formData.append(`categories[${index}][category_name]`, row.category_name);
      formData.append(`categories[${index}][description]`, row.description ?? '');
      formData.append(`categories[${index}][category_quantity]`, String(row.category_quantity ?? 0));
      formData.append(`categories[${index}][old_category_price]`, String(row.old_category_price ?? 0));
      formData.append(`categories[${index}][new_category_price]`, String(row.new_category_price ?? 0));
      formData.append(`categories[${index}][total_price]`, String(row.total_price ?? 0));
      if ((row as any).id) {
        formData.append(`categories[${index}][id]`, String((row as any).id));
      }
      if ((row as any).matched_category_id) {
        formData.append(`categories[${index}][matched_category_id]`, String((row as any).matched_category_id));
      }

      if (row.image) {
        formData.append(`categories[${index}][image]`, row.image, row.imageName || `image${index}.jpg`);
      } else {
        formData.append(`categories[${index}][original_image]`, row.category_image ?? '');
      }
    });

    formData.append('subtotal', String(Number(this.subtotal) || 0));
    formData.append('vat', String(Number(this.vat) || 0));
    formData.append('transportation', String(Number(this.transportation) || 0));
    formData.append('total', String(Number(this.total) || 0));
    formData.append('email', this.email);
    formData.append('phone_number', String(this.phone_number));

    this.saving = true;
    this.offerService.addOffer(formData).subscribe({
      next: (result: any) => {
        const offerId = result?.id || this.id;
        if (!offerId) {
          this.saving = false;
          this.router.navigateByUrl('/dashboard/permissions/priceoffer');
          return;
        }
        if (this.id) {
          this.afterOfferSaved(result, offerId);
          return;
        }
        this.finishCreate(offerId);
      },
      error: (error) => {
        this.saving = false;
        this.errorform = true;
        this.errorMessage = error?.error?.message
          || error?.error?.errors?.quote?.[0]
          || error?.error?.errors?.contact_person?.[0]
          || error?.error?.errors?.client_phone?.[0]
          || 'تعذر حفظ عرض السعر';
      },
    });
  }

  private finishCreate(offerId: number | string) {
    if (!this.sourceCompanyId) {
      this.saving = false;
      this.router.navigate(['/dashboard/permissions/offer2', offerId]);
      return;
    }

    if (Number(this.total) <= 0) {
      this.saving = false;
      Swal.fire({
        icon: 'info',
        title: 'تم حفظ العرض',
        text: 'أضف بنوداً بإجمالي أكبر من صفر ثم اربط العميل من صفحة التفاصيل لترحيل المديونية.',
      }).then(() => this.router.navigate(['/dashboard/permissions/offer2', offerId]));
      return;
    }

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
        this.router.navigate(['/dashboard/permissions/offer2', offerId]);
      },
      error: (err) => {
        this.saving = false;
        Swal.fire({
          icon: 'warning',
          title: 'تم حفظ العرض',
          text: err?.error?.message || 'تعذر الربط التلقائي — يمكنك الربط من صفحة التفاصيل',
        }).then(() => this.router.navigate(['/dashboard/permissions/offer2', offerId]));
      },
    });
  }
}
