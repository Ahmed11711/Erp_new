import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { CategoryService } from 'src/app/categories/services/category.service';
import { BanksService } from 'src/app/financial/services/banks.service';
import { SafeService } from 'src/app/accounting/services/safe.service';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service';
import { SuppliersService } from 'src/app/suppliers/services/suppliers.service';
import { ShippingCompanyService } from 'src/app/shipping/services/shipping-company.service';
import { InvoiceService } from '../service/invoice.service';
import { finalize } from 'rxjs/operators';
import Swal from 'sweetalert2';
import { dateToIsoString, isoStringToDate } from 'src/app/shared/date/date-utils';


@Component({
  selector: 'app-add-invoice',
  templateUrl: './add-invoice.component.html',
  styleUrls: ['./add-invoice.component.css']
})
export class AddInvoiceComponent implements OnInit {
  invoiceId;
  /** للعرض فقط عند التعديل */
  purchaseSerialId: number | null = null;
  errorMessage = '';
  /** يمنع النقر المتكرر أثناء إرسال الفاتورة */
  isSubmitting = false;
  editTracking: any[] = [];
  /** رقم فاتورة داخلي مخصص؛ يُترك فارغاً للترقيم التلقائي PUR-xxxx */
  customInvoiceNo = '';
  /** رقم فاتورة المورد (ورقي) — اختياري */
  externalInvoiceNo = '';
  products:any[] = [];
  categories : any[] = [];
  suppliers : any[] = [];
  shippingReps: any[] = [];
  /** عرض اسم المورد/المندوب عند التعديل — يُربط بـ ng-autocomplete initialValue */
  supplierInitialValue = '';
  shippingRepInitialValue = '';
  /** إعادة بناء autocomplete بعد تحميل بيانات التعديل */
  supplierFieldReady = true;
  shippingRepFieldReady = true;
  private pendingEditPayload: { invoice: any; categories: any[] } | null = null;
  banks: any[] = [];
  safes: any[] = [];
  serviceAccounts: any[] = [];
  paymentType: 'bank' | 'safe' | 'service_account' = 'bank';
  safeId: number | null = null;
  serviceAccountId: number | null = null;
  keyword = 'supplier_name';
  shippingRepKeyword = 'name';
  catword = 'category_name';

  /** نص الصنف في حقل الإكمال بعد الاختيار (بدون وسوم تمييز البحث) */
  selectedCategoryLabel = (item: { category_name?: string } | null | undefined): string => {
    if (!item || item.category_name == null) {
      return '';
    }
    return this.stripHighlightTags(String(item.category_name));
  };

  private stripHighlightTags(value: string): string {
    return String(value ?? '').replace(/<\/?b>/gi, '');
  }

  /** متوسط تكلفة الوحدة (total_price ÷ quantity) — للعرض في قائمة الاختيار */
  averageUnitCost(item: {
    quantity?: number;
    total_price?: number;
    unit_price?: number;
  }): number {
    const q = Number(item?.quantity ?? 0);
    const tp = Number(item?.total_price ?? 0);
    const up = Number(item?.unit_price ?? 0);
    if (q > 0.0000001) {
      return tp / q;
    }
    if (up > 0.0000001) {
      return up;
    }
    return 0;
  }

  readonly purchaseStatusOptions = [
    'اضافة وارد جديد',
    'مرتجع مبيعات',
    'امانات',
    'تم الاستلام',
    'اضافة وارد تشغيل',
  ];
  constructor(
    private bank: BanksService,
    private safeService: SafeService,
    private serviceAccountsService: ServiceAccountsService,
    private router: Router,
    private invoice: InvoiceService,
    private suppliersService: SuppliersService,
    private shippingCompanyService: ShippingCompanyService,
    private cat: CategoryService,
    private route: ActivatedRoute,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    const today = new Date();

    this.invoiceId =
      this.route.snapshot.paramMap.get('id') ||
      this.route.snapshot.queryParamMap.get('editId');

    if (!this.invoiceId) {
      this.status = 'تم الاستلام';
      this.date = dateToIsoString(today);
    }

    this.suppliersService.suppliersname().subscribe({
      next: (res: unknown) => {
        const raw = res != null && typeof res === 'object' && 'data' in (res as object)
          ? (res as { data: unknown }).data
          : res;
        this.suppliers = Array.isArray(raw) ? raw.filter((s: any) => s && (s.supplier_name ?? '').toString().trim() !== '') : [];
        this.applyPendingEditSelections();
        this.cdr.markForCheck();
      },
      error: () => {
        this.suppliers = [];
        this.errorMessage = 'تعذّر تحميل قائمة الموردين. تحقّق من الصلاحيات أو اتصال الخادم.';
        this.cdr.markForCheck();
      },
    });

    this.shippingCompanyService.shippingCompanySelect().subscribe({
      next: (res: any) => {
        const list = Array.isArray(res) ? res : [];
        this.shippingReps = this.buildShippingPartyOptions(list);
        this.applyPendingEditSelections();
        this.cdr.markForCheck();
      },
      error: () => {
        this.shippingReps = [];
        this.cdr.markForCheck();
      },
    });

    this.cat.getCatBywarehouse('مخزن مواد خام').subscribe((res:any)=>{
      this.categories = res;
    })

    this.bank.bankSelect().subscribe((res: any) => {
      this.banks = res;
    });
    this.safeService.getAll({ per_page: 500 }).subscribe((res: any) => {
      this.safes = res.data || res || [];
    });
    this.serviceAccountsService.index().subscribe((res: any) => {
      this.serviceAccounts = res || [];
    });

    if (this.invoiceId) {
      this.purchaseSerialId = Number(this.invoiceId);
      this.supplierFieldReady = false;
      this.shippingRepFieldReady = false;
      this.invoice.getInvoiceById(this.invoiceId, true).subscribe({
        next: (res) => {
          this.pendingEditPayload = {
            invoice: res['invoice'],
            categories: res['categories'] ?? [],
          };
          this.editTracking = res['tracking'] ?? [];
          this.applyPendingEditSelections();
          this.cdr.markForCheck();
        },
        error: () => {
          this.errorMessage = 'تعذّر تحميل بيانات الفاتورة للتعديل.';
          this.cdr.markForCheck();
        },
      });
    }

  }

  /** يُستدعى بعد تحميل الفاتورة و/أو قوائم الموردين والمناديب */
  private applyPendingEditSelections(): void {
    if (!this.pendingEditPayload) {
      return;
    }

    const inv = this.pendingEditPayload.invoice;
    if (!inv) {
      return;
    }

    this.products = this.pendingEditPayload.categories ?? [];
    this.status = inv.invoice_type;
    this.date = this.normalizeReceiptDate(inv.receipt_date);
    this.totalInvice = inv.total_price;
    this.paidamount = inv.paid_amount;
    this.dueamount = inv.due_amount;
    this.transportcost = inv.transport_cost;
    this.paymentType = inv.payment_type || 'bank';
    this.bankId = inv.bank_id;
    this.safeId = inv.safe_id;
    this.serviceAccountId = inv.service_account_id;
    this.externalInvoiceNo = inv.external_invoice_no || '';

    const no = inv?.invoice_no != null && String(inv.invoice_no).trim() !== ''
      ? String(inv.invoice_no).trim()
      : '';
    const num = inv?.invoice_number != null && String(inv.invoice_number).trim() !== ''
      ? String(inv.invoice_number).trim()
      : '';
    this.customInvoiceNo = no || num;

    this.supplierId = this.normalizeEntityId(inv.supplier_id);
    this.shippingCompanyId = this.normalizeEntityId(inv.shipping_company_id);

    const supplierName = (inv.supplier?.supplier_name ?? '').toString().trim();
    if (supplierName) {
      this.supplierInitialValue = supplierName;
      this.suplierSelected = this.hasValidSupplier();
      if (inv.supplier_id && !this.suppliers.some((s: any) => Number(s.id) === Number(inv.supplier_id))) {
        this.suppliers = [{ id: inv.supplier_id, supplier_name: supplierName }, ...this.suppliers];
      }
    } else if (inv.supplier_id) {
      const found = this.suppliers.find((s: any) => Number(s.id) === Number(inv.supplier_id));
      if (found) {
        this.supplierInitialValue = found.supplier_name ?? '';
        this.suplierSelected = this.hasValidSupplier();
      }
    }

    const repName = this.resolveShippingRepName(inv);
    if (repName) {
      this.shippingRepInitialValue = repName;
      if (inv.shipping_company_id && !this.shippingReps.some((r: any) => Number(r.id) === Number(inv.shipping_company_id))) {
        const co = inv?.shipping_company ?? inv?.shippingCompany;
        const coType = (co?.type ?? 'مندوب').toString();
        this.shippingReps = [{ id: inv.shipping_company_id, name: repName, type: coType }, ...this.shippingReps];
      }
    } else if (inv.shipping_company_id) {
      const foundRep = this.shippingReps.find((r: any) => Number(r.id) === Number(inv.shipping_company_id));
      if (foundRep) {
        this.shippingRepInitialValue = foundRep.name ?? '';
      }
    } else {
      this.shippingRepInitialValue = '';
    }

    this.calc();

    if (this.invoiceId) {
      this.supplierFieldReady = false;
      this.shippingRepFieldReady = false;
      setTimeout(() => {
        this.supplierFieldReady = true;
        this.shippingRepFieldReady = true;
        this.syncSupplierSelection();
        this.syncShippingRepSelection();
        this.cdr.markForCheck();
      }, 0);
    }
  }

  /** Laravel يرجّع العلاقة snake_case: shipping_company */
  private resolveShippingRepName(inv: any): string {
    const co = inv?.shipping_company ?? inv?.shippingCompany;
    return (co?.name ?? '').toString().trim();
  }

  /** شركات الشحن والمناديب — للاختيار في الفاتورة */
  private buildShippingPartyOptions(list: any[]): any[] {
    return list
      .filter((item: any) => {
        const name = (item?.name ?? '').toString().trim();
        const type = (item?.type ?? '').toString();
        return name !== '' && (type === 'مندوب' || type === 'شركة');
      })
      .sort((a: any, b: any) => {
        const typeOrder = (t: string) => (t === 'شركة' ? 0 : t === 'مندوب' ? 1 : 2);
        const byType = typeOrder(String(a?.type ?? '')) - typeOrder(String(b?.type ?? ''));
        if (byType !== 0) {
          return byType;
        }
        return String(a?.name ?? '').localeCompare(String(b?.name ?? ''), 'ar');
      });
  }

  shippingPartyTypeLabel(type: unknown): string {
    return String(type ?? '') === 'شركة' ? 'شركة' : 'مندوب';
  }

  changeQuantity(e,i){
    if (e.target.value > 1) {
      this.products[i].product_quantity = e.target.value;
      this.products[i].total = e.target.value * this.products[i].product_price;
      this.calc();
    } else {
      e.target.value = this.products[i].product_quantity;
      this.products[i].product_quantity = e.target.value;
      this.products[i].total = e.target.value * this.products[i].product_price;
      this.calc();
    }
  }

  normalizeReceiptDate(value: unknown): string | null {
    if (value == null || value === '') {
      return null;
    }
    const raw = String(value).trim();
    const iso = isoStringToDate(raw.slice(0, 10));
    if (iso) {
      return dateToIsoString(iso);
    }
    const parsed = isoStringToDate(raw);
    return dateToIsoString(parsed);
  }

  isLegacyPurchaseReturn(): boolean {
    return this.status === 'مرتجع';
  }

  calc(){
    const paidamount = Math.abs(this.paidamount);
    this.products.forEach(elm => {
      elm.product_quantity = Math.abs(elm.product_quantity);
      elm.total = elm.product_quantity * elm.product_price;
    });
    this.producttotal = this.products.reduce((sum, product) => sum + product.total, 0);
    this.totalInvice = Number(this.producttotal) + Number(this.transportcost);
    this.dueamount = this.totalInvice - paidamount;
  }

  reomveProduct(i:number){
    this.products.splice(i, 1);
    this.calc();
  }

  changeStatus(value: string){
    this.status = value;
    this.calc();
  }
  paymentTypeChange() {
    this.bankId = null;
    this.safeId = null;
    this.serviceAccountId = null;
  }

  hasValidPaymentSource(): boolean {
    if (this.paidamount <= 0) return true;
    if (this.paymentType === 'bank') return !!this.bankId;
    if (this.paymentType === 'safe') return !!this.safeId;
    if (this.paymentType === 'service_account') return !!this.serviceAccountId;
    return false;
  }

  hasValidReceiptDate(): boolean {
    return !!this.normalizeReceiptDate(this.date);
  }

  /** شروط تفعيل زر الحفظ — المندوب اختياري */
  canSubmitInvoice(): boolean {
    if (this.isSubmitting) {
      return false;
    }
    if (!this.status) {
      return false;
    }
    if (this.products.length === 0) {
      return false;
    }
    if (!this.hasValidReceiptDate()) {
      return false;
    }
    if (!this.hasValidSupplier()) {
      return false;
    }
    if (!this.hasValidPaymentSource()) {
      return false;
    }
    return true;
  }

  /** رسالة توضيحية عند تعطيل زر الحفظ */
  submitBlockedReason(): string {
    if (this.isSubmitting) {
      return 'جاري الحفظ...';
    }
    if (!this.status) {
      return 'اختر حالة الشراء';
    }
    if (!this.hasValidSupplier()) {
      return 'اختر المورد من القائمة';
    }
    if (!this.hasValidReceiptDate()) {
      return 'اختر تاريخ الاستلام';
    }
    if (this.products.length === 0) {
      return 'أضف بنداً واحداً على الأقل';
    }
    if (!this.hasValidPaymentSource()) {
      return 'اختر مصدر الدفع للمبلغ المدفوع';
    }
    return '';
  }

  productname = '';
  productprice  = 0;
  productUnit='';
  productQuantity = 0;
  supplierId: number | null = null;
  shippingCompanyId: number | null = null;
  status:any;
  bankId:any;
  priceEdited = false;
  originalPrice = 0;
  date: string | null = null;
  productSelected = false;
  /** يُرسل مع فاتورة المشتريات لربط السطر بصف واحد في categories (مخزن مواد خام) */
  categoryId: number | null = null;
  selectEvent(item) {
    this.productname = this.stripHighlightTags(String(item.category_name ?? ''));
    this.categoryId = item.id != null ? Number(item.id) : null;
    const qty = Number(item.quantity) || 0;
    const tp = Number(item.total_price) || 0;
    const wac = qty > 0 ? tp / qty : 0;
    if (wac > 0) {
      this.productprice = Math.round(wac * 10000) / 10000;
    } else if (item.unit_price != null && Number(item.unit_price) > 0) {
      this.productprice = Number(item.unit_price);
    } else {
      this.productprice = item.category_price;
    }
    this.originalPrice = this.productprice;
    this.productUnit = item.measurement.unit
    this.productSelected = true;
  }
  suplierSelected = false;

  private normalizeEntityId(value: unknown): number | null {
    if (value == null || value === '' || value === 'undefined' || value === 'null') {
      return null;
    }
    const n = Number(value);
    return Number.isFinite(n) && n > 0 ? Math.trunc(n) : null;
  }

  /** يُعيد id المورد من الاختيار المباشر أو من الاسم المعروض في الحقل */
  resolveSupplierId(): number | null {
    const direct = this.normalizeEntityId(this.supplierId);
    if (direct != null) {
      return direct;
    }
    const fromName = this.findSupplierByName(this.supplierInitialValue);
    return fromName?.id ?? null;
  }

  private findSupplierByName(text: unknown): { id: number; supplier_name: string } | null {
    const needle = (text ?? '').toString().trim().toLowerCase();
    if (!needle) {
      return null;
    }
    const match = this.suppliers.find(
      (s: any) => (s?.supplier_name ?? '').toString().trim().toLowerCase() === needle
    );
    if (!match) {
      return null;
    }
    const id = this.normalizeEntityId(match.id);
    return id != null ? { id, supplier_name: match.supplier_name } : null;
  }

  /** يُزامن supplierId من الاسم المعروض — يُستدعى قبل التحقق من صلاحية الحفظ */
  syncSupplierSelection(): void {
    const id = this.resolveSupplierId();
    if (id == null) {
      return;
    }
    this.supplierId = id;
    this.suplierSelected = true;
    const found = this.suppliers.find((s: any) => Number(s.id) === id);
    if (found?.supplier_name) {
      this.supplierInitialValue = found.supplier_name;
    }
  }

  hasValidSupplier(): boolean {
    this.syncSupplierSelection();
    return this.resolveSupplierId() != null;
  }

  /**
   * ng-autocomplete يُعيد emit باسم نصي (string) عند تحديث initialValue بعد الاختيار —
   * لا نُصفّر supplierId في هذه الحالة.
   */
  supEvent(item: { id?: unknown; supplier_id?: unknown; supplier_name?: string } | string | null | undefined) {
    if (!item) {
      return;
    }

    if (typeof item === 'string') {
      const text = item.trim();
      if (!text) {
        return;
      }
      const found = this.findSupplierByName(text);
      if (found) {
        this.supplierId = found.id;
        this.supplierInitialValue = found.supplier_name;
        this.suplierSelected = true;
      }
      return;
    }

    const id = this.normalizeEntityId(item.id ?? item.supplier_id);
    if (id != null) {
      this.supplierId = id;
      this.supplierInitialValue = item.supplier_name ?? '';
      this.suplierSelected = true;
      return;
    }

    const name = (item.supplier_name ?? '').toString().trim();
    const found = name ? this.findSupplierByName(name) : null;
    if (found) {
      this.supplierId = found.id;
      this.supplierInitialValue = found.supplier_name;
      this.suplierSelected = true;
    }
  }

  onSupplierInputChanged(value: string) {
    const typed = (value ?? '').toString().trim();
    if (!typed) {
      return;
    }
    const found = this.findSupplierByName(typed);
    if (found) {
      this.supplierId = found.id;
      this.supplierInitialValue = found.supplier_name;
      this.suplierSelected = true;
    }
  }

  resetSup(){
    this.supplierId = null;
    this.supplierInitialValue = '';
    this.suplierSelected = false;
  }

  resolveShippingCompanyId(): number | null {
    const direct = this.normalizeEntityId(this.shippingCompanyId);
    if (direct != null) {
      return direct;
    }
    const fromName = this.findShippingRepByName(this.shippingRepInitialValue);
    return fromName?.id ?? null;
  }

  private findShippingRepByName(text: unknown): { id: number; name: string } | null {
    const needle = (text ?? '').toString().trim().toLowerCase();
    if (!needle) {
      return null;
    }
    const match = this.shippingReps.find(
      (r: any) => (r?.name ?? '').toString().trim().toLowerCase() === needle
    );
    if (!match) {
      return null;
    }
    const id = this.normalizeEntityId(match.id);
    return id != null ? { id, name: match.name } : null;
  }

  syncShippingRepSelection(): void {
    const id = this.resolveShippingCompanyId();
    if (id == null) {
      return;
    }
    this.shippingCompanyId = id;
    const found = this.shippingReps.find((r: any) => Number(r.id) === id);
    if (found?.name) {
      this.shippingRepInitialValue = found.name;
    }
  }

  shippingRepEvent(item: { id?: unknown; shipping_company_id?: unknown; name?: string } | string | null | undefined) {
    if (!item) {
      return;
    }

    if (typeof item === 'string') {
      const text = item.trim();
      if (!text) {
        return;
      }
      const found = this.findShippingRepByName(text);
      if (found) {
        this.shippingCompanyId = found.id;
        this.shippingRepInitialValue = found.name;
      }
      return;
    }

    const id = this.normalizeEntityId(item.id ?? item.shipping_company_id);
    if (id != null) {
      this.shippingCompanyId = id;
      this.shippingRepInitialValue = item.name ?? '';
      return;
    }

    const name = (item.name ?? '').toString().trim();
    const found = name ? this.findShippingRepByName(name) : null;
    if (found) {
      this.shippingCompanyId = found.id;
      this.shippingRepInitialValue = found.name;
    }
  }

  onShippingRepInputChanged(value: string) {
    const typed = (value ?? '').toString().trim();
    if (!typed) {
      return;
    }
    const found = this.findShippingRepByName(typed);
    if (found) {
      this.shippingCompanyId = found.id;
      this.shippingRepInitialValue = found.name;
    }
  }

  resetShippingRep() {
    this.shippingCompanyId = null;
    this.shippingRepInitialValue = '';
  }
  selectedImg: any = null;
  chooseImg(event){
    this.selectedImg = event.target.files[0];
  }
  invoicePriceEdited = 0;
  addToTabel(){
    if(this.originalPrice != this.productprice){
      this.priceEdited = true;
      this.invoicePriceEdited = 1;
    }
    this.products.push({
      product_quantity: this.productQuantity,
      price_edited: this.priceEdited,
      product_price: this.productprice,
      product_name: this.productname,
      total: this.productprice * this.productQuantity,
      product_unit: this.productUnit,
      category_id: this.categoryId,
    });
    this.calc();
    this.priceEdited = false;
  }
  dueamount=0;
  producttotal = 0;
  paidamount=0;
  transportcost=0;
  totalInvice = 0;

  addInvoice(form:any){
    if (this.isSubmitting) {
      return;
    }
    this.syncSupplierSelection();
    this.syncShippingRepSelection();
    const supplierId = this.resolveSupplierId();
    if (supplierId == null) {
      this.errorMessage = 'يجب اختيار المورد من القائمة (لا يكفي كتابة الاسم فقط).';
      this.suplierSelected = false;
      return;
    }
    const paidamount = Math.abs(this.paidamount);

    let invoice = new FormData();
    if (this.invoiceId) {
      invoice.append('invoiceId', this.invoiceId);
    }
    invoice.append('supplier_id', String(supplierId));
    const shippingCompanyId = this.resolveShippingCompanyId();
    if (shippingCompanyId != null) {
      invoice.append('shipping_company_id', String(shippingCompanyId));
    }
    invoice.append('invoice_type', this.status);
    invoice.append('receipt_date', this.normalizeReceiptDate(this.date) || '');
    invoice.append('total_price', this.totalInvice.toString());
    invoice.append('paid_amount', paidamount.toString());
    invoice.append('due_amount', this.dueamount.toString());
    invoice.append('transport_cost', this.transportcost.toString());
    invoice.append('price_edited', this.invoicePriceEdited.toString());
    invoice.append('payment_type', this.paymentType);
    if (this.paymentType === 'bank' && this.bankId) {
      invoice.append('bank_id', this.bankId.toString());
    }
    if (this.paymentType === 'safe' && this.safeId) {
      invoice.append('safe_id', this.safeId.toString());
    }
    if (this.paymentType === 'service_account' && this.serviceAccountId) {
      invoice.append('service_account_id', this.serviceAccountId.toString());
    }
    const ext = (this.externalInvoiceNo || '').trim();
    if (ext) {
      invoice.append('external_invoice_no', ext);
    }
    const custom = (this.customInvoiceNo || '').trim();
    if (custom) {
      invoice.append('custom_invoice_no', custom);
    }
    if(this.selectedImg){
      invoice.append('invoice_image', this.selectedImg, this.selectedImg.name);
    }
    invoice.append('products', JSON.stringify(this.products));
    this.errorMessage = '';
    this.isSubmitting = true;
    this.invoice.addInvoice(invoice).pipe(
      finalize(() => { this.isSubmitting = false; })
    ).subscribe({
      next: (res:any)=>{
        if(res.success==true){
          const warnings: string[] = Array.isArray(res.warnings) ? res.warnings : [];
          if (warnings.length) {
            Swal.fire({
              icon: 'warning',
              title: 'تم حفظ التعديل',
              html: warnings.map((w) => `<p>${w}</p>`).join(''),
              confirmButtonText: 'حسناً',
            }).then(() => {
              this.router.navigate(['/dashboard/purchases/list_invoice']);
            });
          } else {
            this.router.navigate(['/dashboard/purchases/list_invoice']);
          }
        }
      },
      error: (err) => {
        this.errorMessage = err.error?.message || 'حدث خطأ أثناء حفظ الفاتورة';
      }
    })
  }

  resetInp(){
    this.productprice = 0;
    this.productQuantity = 0;
    this.categoryId = null;
  }

}
