import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { OfferService } from '../services/offer.service';
import { MatDialog } from '@angular/material/dialog';
import { AngularEditorComponent } from 'src/app/shared/angular-editor/angular-editor.component';
import { Subscription } from 'rxjs';
import { CompaniesService } from 'src/app/shipping/services/companies.service';
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
  private routeSub?: Subscription;

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
    this.rows.push({ category_name: 'product', description: 'description', category_quantity: 0, old_category_price:0, new_category_price: 0, total_price: 0 });
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

  id: any = null;

  constructor(
    private offerService: OfferService,
    private companiesService: CompaniesService,
    private router: Router,
    private dialog: MatDialog,
    private cd: ChangeDetectorRef,
    private activatedRoute: ActivatedRoute,
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
    if (e?.target?.id == 'vat') {
      this.changedVat = true;
    }
    if (!this.changedVat) {
      this.vat = this.subtotal * this.vatPercent / 100 + (this.transportation * this.vatPercent / 100);
    }
    this.total = this.subtotal + this.vat + this.transportation;
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

  submitform() {
    if (this.saving) {
      return;
    }
    if (!this.validateClientFields()) {
      return;
    }

    const formData = new FormData();

    formData.append('dateFrom', this.dateFrom);
    formData.append('dateTo', this.dateTo || this.dateFrom);
    formData.append('quote', this.quote.trim());
    formData.append('contact_person', this.contact_person.trim());
    formData.append('client_phone', this.client_phone.trim());
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

    this.rows.forEach((row, index) => {
      formData.append(`categories[${index}][category_name]`, row.category_name);
      formData.append(`categories[${index}][description]`, row.description ?? '');
      formData.append(`categories[${index}][category_quantity]`, String(row.category_quantity ?? 0));
      formData.append(`categories[${index}][old_category_price]`, String(row.old_category_price ?? 0));
      formData.append(`categories[${index}][new_category_price]`, String(row.new_category_price ?? 0));
      formData.append(`categories[${index}][total_price]`, String(row.total_price ?? 0));

      if (row.image) {
        formData.append(`categories[${index}][image]`, row.image, row.imageName || `image${index}.jpg`);
      } else {
        formData.append(`categories[${index}][original_image]`, row.category_image ?? '');
      }
    });

    formData.append('subtotal', String(this.subtotal));
    formData.append('vat', String(this.vat));
    formData.append('transportation', String(this.transportation));
    formData.append('total', String(this.total));
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
          this.saving = false;
          this.router.navigate(['/dashboard/permissions/offer2', offerId]);
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
