import { Component, OnInit } from '@angular/core';
import { OfferService } from '../services/offer.service';
import { OfferQuotationExportService } from '../services/offer-quotation-export.service';
import { ActivatedRoute } from '@angular/router';
import { CompaniesService } from 'src/app/shipping/services/companies.service';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/env/env';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-offer1-details',
  templateUrl: './offer1-details.component.html',
  styleUrls: ['./offer1-details.component.css']
})
export class Offer1DetailsComponent implements OnInit {
  offer: any = {};
  categories: any[] = [];
  showOldPrice: boolean = false;
  companies: any[] = [];
  selectedCompany: any = null;
  catword = 'name';
  linking = false;
  showCreateForm = false;
  creating = false;
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

  productGaps: any[] = [];
  productGapsSummary: any = null;
  loadingGaps = false;
  creatingCategories = false;
  linkingCategory = false;
  categorySearchKeyword = 'category_name';
  /** أصناف مخزن المنتج التام للبحث اليدوي */
  finishedCategories: any[] = [];

  exportingPdf = false;
  /** أقسام الإدارة مطوية افتراضياً لتوفير مساحة فوق الكوتيشن */
  showLinkClientPanel = false;
  showProductGapsPanel = false;

  toggleLinkClientPanel(): void {
    this.showLinkClientPanel = !this.showLinkClientPanel;
  }

  toggleProductGapsPanel(): void {
    this.showProductGapsPanel = !this.showProductGapsPanel;
    if (this.showProductGapsPanel && !this.productGaps?.length && this.offer?.id) {
      this.loadProductGaps();
    }
  }

  constructor(
    private offerService: OfferService,
    private route: ActivatedRoute,
    private companiesService: CompaniesService,
    private http: HttpClient,
    private quotationExport: OfferQuotationExportService,
    public rbac: RbacService,
  ) { }

  ngOnInit(): void {
    const id = this.route.snapshot.params['id'];
    this.loadOffer(id);
    this.loadFinishedCategories();
    this.companiesService.data().subscribe({
      next: (res) => this.companies = res || [],
      error: () => this.companies = [],
    });
    this.http.get('assets/egypt/governorates.json').subscribe((data: any) => this.location = data || []);
    this.http.get('assets/egypt/cities.json').subscribe((data: any) => {
      this.cities = (data || []).filter((elem: any) => elem.governorate_id == 1);
    });
  }

  loadFinishedCategories(): void {
    this.http.get<any>(`${environment.Url}/categories/search`, {
      params: {
        itemsPerPage: 2000,
        warehouse: 'مخزن منتج تام',
      },
    }).subscribe({
      next: (res) => {
        this.finishedCategories = (res?.data || []).map((item: any) => ({
          ...item,
          category_name: item.category_name || '',
        }));
      },
      error: () => {
        this.finishedCategories = [];
      },
    });
  }

  loadOffer(id: any) {
    this.offerService.getOfferById(id).subscribe((res: any) => {
      this.offer = res;
      this.categories = res.category;
      let oldPrice = this.categories.some(elm => elm.old_category_price > 0);
      if (oldPrice) {
        this.showOldPrice = true;
      }
      if (res?.debt_posted_at && res?.customer_company_id) {
        this.ensureDebtGl(res.id);
      }
      this.loadProductGaps(id);
    });
  }

  loadProductGaps(offerId?: number | string) {
    const id = offerId || this.offer?.id;
    if (!id) {
      return;
    }
    this.loadingGaps = true;
    this.offerService.getProductGaps(id).subscribe({
      next: (res: any) => {
        this.productGaps = res?.lines || [];
        this.productGapsSummary = res?.summary || null;
        this.loadingGaps = false;
      },
      error: () => {
        this.productGaps = [];
        this.productGapsSummary = null;
        this.loadingGaps = false;
      },
    });
  }

  get actionableGaps(): any[] {
    return (this.productGaps || []).filter(
      (l) =>
        l.status === 'missing_category'
        || l.status === 'missing_recipe'
        || l.match_type === 'space_insensitive'
        || l.match_type === 'manual'
    );
  }

  private stripHighlightTags(value: string): string {
    return String(value ?? '').replace(/<\/?b>/gi, '');
  }

  selectedGapCategoryLabel = (item: any): string => {
    if (!item?.category_name) {
      return '';
    }
    const name = this.stripHighlightTags(String(item.category_name));
    const code = String(item.item_code ?? '').trim();
    return code ? `${name} (${code})` : name;
  };

  filterGapCategorySearch = (items: any[], query: string) => {
    const q = (query ?? '').trim().toLowerCase();
    if (!q) {
      return [...items];
    }
    return items.filter((item) => {
      const name = this.stripHighlightTags(String(item.category_name ?? '')).toLowerCase();
      const code = String(item.item_code ?? '').toLowerCase();
      return name.includes(q) || code.includes(q);
    });
  };

  onGapCategorySelected(line: any, category: any): void {
    if (!this.offer?.id || !line?.offer_line_id || !category?.id || this.linkingCategory) {
      return;
    }
    this.linkingCategory = true;
    this.offerService.linkMatchedCategory(this.offer.id, line.offer_line_id, category.id).subscribe({
      next: (res: any) => {
        this.linkingCategory = false;
        this.productGaps = res?.analysis?.lines || [];
        this.productGapsSummary = res?.analysis?.summary || null;
        line.editingMatch = false;
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: 'تم ربط الصنف',
          showConfirmButton: false,
          timer: 1800,
        });
      },
      error: (err) => {
        this.linkingCategory = false;
        Swal.fire('خطأ', err?.error?.message || 'فشل ربط الصنف', 'error');
      },
    });
  }

  clearGapCategoryLink(line: any): void {
    if (!this.offer?.id || !line?.offer_line_id || this.linkingCategory) {
      return;
    }
    this.linkingCategory = true;
    this.offerService.clearMatchedCategory(this.offer.id, line.offer_line_id).subscribe({
      next: (res: any) => {
        this.linkingCategory = false;
        this.productGaps = res?.analysis?.lines || [];
        this.productGapsSummary = res?.analysis?.summary || null;
        line.editingMatch = false;
      },
      error: (err) => {
        this.linkingCategory = false;
        Swal.fire('خطأ', err?.error?.message || 'فشل إلغاء الربط', 'error');
      },
    });
  }

  startEditGapMatch(line: any): void {
    line.editingMatch = true;
  }

  createMissingCategories(lineIds?: number[]) {
    if (!this.offer?.id || this.creatingCategories) {
      return;
    }
    const count = lineIds?.length
      || (this.productGaps || []).filter((l) => l.status === 'missing_category').length;
    if (!count) {
      Swal.fire('تنبيه', 'لا توجد أصناف ناقصة للإنشاء', 'info');
      return;
    }

    Swal.fire({
      title: 'إنشاء الأصناف الناقصة؟',
      html: `سيتم إنشاء <b>${count}</b> صنف في مخزن المنتج التام. الأسماء المشابهة.`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'إنشاء',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#82225e',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.creatingCategories = true;
      this.offerService.createMissingCategories(this.offer.id, lineIds).subscribe({
        next: (res: any) => {
          this.creatingCategories = false;
          this.productGaps = res?.analysis?.lines || [];
          this.productGapsSummary = res?.analysis?.summary || null;
          const created = res?.created?.length || 0;
          const skipped = res?.skipped?.length || 0;
          Swal.fire('تم', `تم إنشاء ${created} صنف` + (skipped ? ` وتخطي ${skipped} (موجود مسبقاً)` : ''), 'success');
        },
        error: (err) => {
          this.creatingCategories = false;
          Swal.fire('خطأ', err?.error?.message || 'فشل إنشاء الأصناف', 'error');
        },
      });
    });
  }

  createOneMissingCategory(line: any) {
    if (line?.offer_line_id) {
      this.createMissingCategories([line.offer_line_id]);
    }
  }

  recipeLink(line: any): any[] {
    return ['/dashboard/manufacturing/addrecipe'];
  }

  recipeQuery(line: any): any {
    return {
      warehouse: 'مخزن منتج تام',
      productId: line?.category?.id,
      productName: line?.category?.category_name || line?.offer_name,
    };
  }

  ensureDebtGl(offerId: number | string) {
    this.offerService.syncDebtGl(offerId).subscribe({
      next: (res: any) => {
        if (res?.offer) {
          this.offer = res.offer;
          this.categories = res.offer.category || this.categories;
        }
      },
      error: () => {},
    });
  }

  onCompanySelected(company: any) {
    this.selectedCompany = company;
  }

  resetCompany() {
    this.selectedCompany = null;
  }

  get isLinked(): boolean {
    return !!this.offer?.customer_company_id && !!this.offer?.debt_posted_at;
  }

  linkToCompany() {
    if (!this.selectedCompany?.id) {
      Swal.fire('تنبيه', 'اختر عميل شركة أولاً', 'warning');
      return;
    }
    if (!this.offer?.id) {
      return;
    }
    if (this.isLinked) {
      Swal.fire('تنبيه', 'هذا العرض مربوط مسبقاً وتم ترحيل المديونية', 'info');
      return;
    }

    const total = Number(this.offer.total) || 0;
    Swal.fire({
      title: 'ربط عرض السعر بعميل شركة؟',
      html: `سيتم ربط العرض بـ <b>${this.selectedCompany.name}</b> وترحيل مديونية بقيمة <b>${total}</b> على رصيده.`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'تأكيد الربط',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#82225e',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.linking = true;
      this.offerService.linkClient(this.offer.id, this.selectedCompany.id).subscribe({
        next: (res: any) => {
          this.linking = false;
          this.offer = res.offer || this.offer;
          this.categories = this.offer.category || this.categories;
          Swal.fire('تم', res.message || 'تم الربط وترحيل المديونية', 'success');
        },
        error: (err) => {
          this.linking = false;
          Swal.fire('خطأ', err?.error?.message || 'فشل الربط', 'error');
        },
      });
    });
  }

  normalizePhone(phone: string): string {
    let digits = String(phone || '').replace(/\D+/g, '');
    if (digits.startsWith('20') && digits.length >= 12) {
      digits = digits.substring(2);
    }
    if (digits && !digits.startsWith('0') && digits.length === 10) {
      digits = '0' + digits;
    }
    return digits;
  }

  openCreateForm() {
    this.showCreateForm = true;
    this.newCompany = {
      name: this.offer?.quote || '',
      phone1: this.normalizePhone(this.offer?.client_phone || ''),
      phone2: '',
      governorate: '',
      city: '',
      address: this.offer?.contact_person
        ? `مسؤول التواصل: ${this.offer.contact_person}`
        : '',
      tel: '',
    };
    this.governName = false;
  }

  cancelCreateForm() {
    this.showCreateForm = false;
  }

  onGovernChange() {
    this.governName = this.newCompany.governorate === 'القاهرة';
    if (!this.governName) {
      this.newCompany.city = '';
    }
  }

  createAndLinkCompany() {
    if (!this.offer?.id) {
      return;
    }
    if (!this.newCompany.name?.trim()) {
      Swal.fire('تنبيه', 'اسم العميل مطلوب', 'warning');
      return;
    }
    if (!this.newCompany.phone1?.trim()) {
      Swal.fire('تنبيه', 'رقم الموبايل مطلوب', 'warning');
      return;
    }
    if (!this.newCompany.governorate || this.newCompany.governorate === 'المحافظة') {
      Swal.fire('تنبيه', 'المحافظة مطلوبة', 'warning');
      return;
    }
    if (!this.newCompany.address?.trim()) {
      Swal.fire('تنبيه', 'العنوان مطلوب', 'warning');
      return;
    }

    const total = Number(this.offer.total) || 0;
    Swal.fire({
      title: 'إنشاء عميل وربط العرض؟',
      html: `سيتم إنشاء <b>${this.newCompany.name}</b> كعميل شركة وترحيل مديونية بقيمة <b>${total}</b>.`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'إنشاء وترحيل',
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
          this.categories = this.offer.category || this.categories;
          this.companiesService.data().subscribe({
            next: (list) => this.companies = list || [],
          });
          Swal.fire('تم', res.message || 'تم إنشاء العميل وترحيل المديونية', 'success');
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

  font: string = 'f-1'

  /** Print preview with toolbar (طباعة + تحميل PDF). */
  downloadPDF() {
    const element = document.getElementById('capture');
    if (!element || !this.offer?.id) {
      return;
    }
    this.quotationExport.openPrintPreview(element, this.offer.id);
  }

  /** Direct file download as Quotation-{id}.pdf */
  saveQuotationPdf() {
    const element = document.getElementById('capture');
    if (!element || !this.offer?.id || this.exportingPdf) {
      return;
    }
    this.exportingPdf = true;
    this.quotationExport.downloadPdf(element, this.offer.id)
      .catch(() => {
        Swal.fire('خطأ', 'تعذر تحميل ملف PDF', 'error');
      })
      .finally(() => {
        this.exportingPdf = false;
      });
  }

}
