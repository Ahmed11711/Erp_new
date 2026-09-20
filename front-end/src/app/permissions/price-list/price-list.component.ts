import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import Swal from 'sweetalert2';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { environment } from 'src/env/env';
import { PriceListExportService } from '../services/price-list-export.service';
import {
  PriceListItem,
  PriceListService,
  PriceListSummary,
} from '../services/price-list.service';

@Component({
  selector: 'app-price-list',
  templateUrl: './price-list.component.html',
  styleUrls: ['./price-list.component.css'],
})
export class PriceListComponent implements OnInit {
  view: 'lists' | 'detail' = 'lists';

  catalogs: PriceListSummary[] = [];
  activeListId: number | null = null;
  title = '';
  items: PriceListItem[] = [];

  loading = false;
  saving = false;
  exportingPdf = false;
  deletingId: number | null = null;
  deletingListId: number | null = null;

  showForm = false;
  editingItem: PriceListItem | null = null;
  form!: FormGroup;

  showListForm = false;
  listFormName = '';
  renamingList = false;

  showShopifyPicker = false;
  shopifyQuery = '';
  shopifyProducts: any[] = [];
  shopifyLoading = false;
  shopifyImportingId: number | null = null;
  shopifyPage = 1;
  shopifyLastPage = 1;
  shopifyCurrency = 'EGP';

  /** كتالوج صور Shopify داخل مودال الإضافة/التعديل */
  showImageCatalog = false;
  imageCatalogTarget: 1 | 2 = 1;
  imageCatalogQuery = '';
  imageCatalogItems: Array<{ url: string; name?: string; sku?: string }> = [];
  imageCatalogLoading = false;
  imageCatalogPage = 1;
  imageCatalogLastPage = 1;

  photo1File: File | null = null;
  photo2File: File | null = null;
  photo1Preview: string | null = null;
  photo2Preview: string | null = null;
  photo1RemoteUrl: string | null = null;
  photo2RemoteUrl: string | null = null;
  removePhoto1 = false;
  removePhoto2 = false;

  editingTitle = false;
  titleDraft = '';

  readonly imgUrl = environment.imgUrl;
  readonly currencies = ['EGP', 'USD', 'EUR', 'AED', 'SAR', 'GBP'];

  constructor(
    private priceListService: PriceListService,
    private priceListExport: PriceListExportService,
    private fb: FormBuilder,
    public rbac: RbacService,
  ) {}

  ngOnInit(): void {
    this.form = this.fb.group({
      code: ['', [Validators.required, Validators.maxLength(64)]],
      product_name: ['', [Validators.required, Validators.maxLength(255)]],
      price: [null, [Validators.required, Validators.min(0)]],
      currency: ['EGP', [Validators.required, Validators.maxLength(16)]],
    });
    this.loadCatalogs();
  }

  canEdit(): boolean {
    return this.rbac.can('price_list.edit');
  }

  canDelete(): boolean {
    return this.rbac.can('price_list.delete');
  }

  loadCatalogs(): void {
    this.loading = true;
    this.priceListService.getLists().subscribe({
      next: (res) => {
        this.catalogs = res.lists || [];
        this.loading = false;
      },
      error: () => {
        this.catalogs = [];
        this.loading = false;
        Swal.fire({ icon: 'error', title: 'تعذر تحميل قوائم الأسعار', timer: 2000, showConfirmButton: false });
      },
    });
  }

  openCatalog(list: PriceListSummary): void {
    this.activeListId = list.id;
    this.view = 'detail';
    this.loadDetail();
  }

  backToLists(): void {
    this.view = 'lists';
    this.activeListId = null;
    this.items = [];
    this.title = '';
    this.editingTitle = false;
    this.closeForm();
    this.loadCatalogs();
  }

  loadDetail(): void {
    if (!this.activeListId) {
      return;
    }
    this.loading = true;
    this.priceListService.getList(this.activeListId).subscribe({
      next: (res) => {
        this.title = res.name || res.title || '';
        this.items = res.items || [];
        this.loading = false;
      },
      error: () => {
        this.items = [];
        this.loading = false;
        Swal.fire({ icon: 'error', title: 'تعذر تحميل القائمة', timer: 2000, showConfirmButton: false });
      },
    });
  }

  openCreateList(): void {
    if (!this.canEdit()) {
      return;
    }
    this.renamingList = false;
    this.listFormName = '';
    this.showListForm = true;
  }

  closeListForm(): void {
    this.showListForm = false;
    this.listFormName = '';
    this.renamingList = false;
  }

  saveListForm(): void {
    if (!this.canEdit() || this.saving) {
      return;
    }
    const name = (this.listFormName || '').trim();
    if (!name) {
      Swal.fire({ icon: 'warning', title: 'اكتب اسم القائمة', timer: 1400, showConfirmButton: false });
      return;
    }

    this.saving = true;
    if (this.renamingList && this.activeListId) {
      this.priceListService.renameList(this.activeListId, name).subscribe({
        next: (res) => {
          this.title = res.name || res.title || name;
          this.saving = false;
          this.closeListForm();
          this.editingTitle = false;
          Swal.fire({ icon: 'success', title: 'تم حفظ الاسم', timer: 1200, showConfirmButton: false });
        },
        error: () => {
          this.saving = false;
          Swal.fire({ icon: 'error', title: 'تعذر حفظ الاسم' });
        },
      });
      return;
    }

    this.priceListService.createList(name).subscribe({
      next: (res: any) => {
        this.saving = false;
        this.closeListForm();
        Swal.fire({ icon: 'success', title: 'تم إنشاء القائمة', timer: 1200, showConfirmButton: false });
        const created = res?.list;
        if (created?.id) {
          this.openCatalog({
            id: created.id,
            name: created.name || name,
            items_count: 0,
          });
        } else {
          this.loadCatalogs();
        }
      },
      error: () => {
        this.saving = false;
        Swal.fire({ icon: 'error', title: 'تعذر إنشاء القائمة' });
      },
    });
  }

  deleteCatalog(list: PriceListSummary, event?: Event): void {
    event?.stopPropagation();
    if (!this.canDelete() || this.deletingListId != null) {
      return;
    }

    Swal.fire({
      title: 'حذف Price List؟',
      text: `سيتم حذف «${list.name}» وجميع منتجاتها نهائياً.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم، احذف',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#82225e',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.deletingListId = list.id;
      this.priceListService.deleteList(list.id).subscribe({
        next: () => {
          this.deletingListId = null;
          Swal.fire({ icon: 'success', title: 'تم الحذف', timer: 1200, showConfirmButton: false });
          if (this.activeListId === list.id) {
            this.backToLists();
          } else {
            this.loadCatalogs();
          }
        },
        error: () => {
          this.deletingListId = null;
          Swal.fire({ icon: 'error', title: 'تعذر حذف القائمة' });
        },
      });
    });
  }

  photoSrc(filename?: string | null): string | null {
    if (!filename) {
      return null;
    }
    return `${this.imgUrl}${filename}`;
  }

  startEditTitle(): void {
    if (!this.canEdit() || !this.activeListId) {
      return;
    }
    this.renamingList = true;
    this.listFormName = this.title;
    this.showListForm = true;
  }

  cancelEditTitle(): void {
    this.editingTitle = false;
    this.titleDraft = '';
  }

  saveTitle(): void {
    if (!this.canEdit() || this.saving || !this.activeListId) {
      return;
    }
    const next = (this.titleDraft || '').trim();
    if (!next) {
      return;
    }
    this.saving = true;
    this.priceListService.renameList(this.activeListId, next).subscribe({
      next: (res) => {
        this.title = res.name || res.title || next;
        this.editingTitle = false;
        this.saving = false;
        Swal.fire({ icon: 'success', title: 'تم حفظ الاسم', timer: 1200, showConfirmButton: false });
      },
      error: () => {
        this.saving = false;
        Swal.fire({ icon: 'error', title: 'تعذر حفظ الاسم' });
      },
    });
  }

  openAdd(): void {
    if (!this.canEdit() || !this.activeListId) {
      return;
    }
    this.editingItem = null;
    this.resetFormMedia();
    this.form.reset({ code: '', product_name: '', price: null, currency: 'EGP' });
    this.showForm = true;
  }

  openShopifyPicker(): void {
    if (!this.canEdit() || !this.activeListId) {
      return;
    }
    this.showShopifyPicker = true;
    this.shopifyQuery = '';
    this.shopifyCurrency = 'EGP';
    this.shopifyPage = 1;
    this.searchShopify();
  }

  closeShopifyPicker(): void {
    this.showShopifyPicker = false;
    this.shopifyProducts = [];
    this.shopifyImportingId = null;
  }

  searchShopify(page = 1): void {
    this.shopifyLoading = true;
    this.shopifyPage = page;
    this.priceListService.searchShopifyProducts(this.shopifyQuery.trim(), page, 20).subscribe({
      next: (res) => {
        this.shopifyProducts = res?.data || [];
        this.shopifyPage = res?.current_page || page;
        this.shopifyLastPage = res?.last_page || 1;
        this.shopifyLoading = false;
      },
      error: () => {
        this.shopifyProducts = [];
        this.shopifyLoading = false;
        Swal.fire({
          icon: 'error',
          title: 'تعذر جلب منتجات Shopify',
          text: 'تأكد من مزامنة المنتجات من لوحة Shopify أولاً.',
        });
      },
    });
  }

  importShopifyProduct(product: any): void {
    if (!this.canEdit() || !this.activeListId || this.shopifyImportingId != null) {
      return;
    }
    const id = Number(product?.id);
    if (!id) {
      return;
    }

    this.shopifyImportingId = id;
    this.priceListService.importFromShopify(this.activeListId, id, this.shopifyCurrency || 'EGP').subscribe({
      next: (res: any) => {
        this.shopifyImportingId = null;
        const photos = res?.images_downloaded;
        const photoNote =
          photos && (photos.photo1 || photos.photo2)
            ? ' مع الصور'
            : ' (بدون صور — أعد مزامنة Shopify أو أضف صوراً يدوياً)';
        Swal.fire({
          icon: 'success',
          title: 'تم الاستيراد من Shopify' + photoNote,
          timer: 1800,
          showConfirmButton: false,
        });
        this.loadDetail();
      },
      error: (err) => {
        this.shopifyImportingId = null;
        Swal.fire({
          icon: 'error',
          title: err?.error?.message || 'تعذر الاستيراد من Shopify',
        });
      },
    });
  }

  openEdit(item: PriceListItem): void {
    if (!this.canEdit()) {
      return;
    }
    this.editingItem = item;
    this.resetFormMedia();
    this.form.reset({
      code: item.code,
      product_name: item.product_name,
      price: item.price,
      currency: (item.currency || 'EGP').toUpperCase(),
    });
    this.photo1Preview = this.photoSrc(item.photo1);
    this.photo2Preview = this.photoSrc(item.photo2);
    this.showForm = true;
  }

  closeForm(): void {
    this.showForm = false;
    this.editingItem = null;
    this.resetFormMedia();
  }

  onPhotoChange(event: Event, which: 1 | 2): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] || null;
    if (!file) {
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      if (which === 1) {
        this.photo1File = file;
        this.photo1RemoteUrl = null;
        this.photo1Preview = String(reader.result);
        this.removePhoto1 = false;
      } else {
        this.photo2File = file;
        this.photo2RemoteUrl = null;
        this.photo2Preview = String(reader.result);
        this.removePhoto2 = false;
      }
    };
    reader.readAsDataURL(file);
  }

  clearPhoto(which: 1 | 2): void {
    if (which === 1) {
      this.photo1File = null;
      this.photo1RemoteUrl = null;
      this.photo1Preview = null;
      this.removePhoto1 = !!this.editingItem?.photo1;
    } else {
      this.photo2File = null;
      this.photo2RemoteUrl = null;
      this.photo2Preview = null;
      this.removePhoto2 = !!this.editingItem?.photo2;
    }
    const input = document.getElementById(`pl-file-${which}`) as HTMLInputElement | null;
    if (input) {
      input.value = '';
    }
  }

  openImageCatalog(which: 1 | 2): void {
    this.imageCatalogTarget = which;
    this.imageCatalogQuery = '';
    this.imageCatalogPage = 1;
    this.showImageCatalog = true;
    this.loadImageCatalog(1);
  }

  closeImageCatalog(): void {
    this.showImageCatalog = false;
    this.imageCatalogItems = [];
  }

  loadImageCatalog(page = 1): void {
    this.imageCatalogLoading = true;
    this.imageCatalogPage = page;
    this.priceListService.searchShopifyImages(this.imageCatalogQuery.trim(), page, 48).subscribe({
      next: (res) => {
        this.imageCatalogItems = res?.data || [];
        this.imageCatalogPage = res?.current_page || page;
        this.imageCatalogLastPage = res?.last_page || 1;
        this.imageCatalogLoading = false;
      },
      error: () => {
        this.imageCatalogItems = [];
        this.imageCatalogLoading = false;
        Swal.fire({
          icon: 'error',
          title: 'تعذر تحميل كتالوج صور Shopify',
          text: 'أعد مزامنة منتجات Shopify من لوحة التكامل أولاً.',
        });
      },
    });
  }

  selectCatalogImage(img: { url: string }): void {
    const url = String(img?.url || '').trim();
    if (!url) {
      return;
    }
    if (this.imageCatalogTarget === 1) {
      this.photo1File = null;
      this.photo1RemoteUrl = url;
      this.photo1Preview = url;
      this.removePhoto1 = false;
      const input = document.getElementById('pl-file-1') as HTMLInputElement | null;
      if (input) {
        input.value = '';
      }
    } else {
      this.photo2File = null;
      this.photo2RemoteUrl = url;
      this.photo2Preview = url;
      this.removePhoto2 = false;
      const input = document.getElementById('pl-file-2') as HTMLInputElement | null;
      if (input) {
        input.value = '';
      }
    }
    this.closeImageCatalog();
  }

  saveItem(): void {
    if (!this.canEdit() || this.saving || this.form.invalid || !this.activeListId) {
      this.form.markAllAsTouched();
      return;
    }

    const listId = this.activeListId;
    const fd = new FormData();
    fd.append('code', String(this.form.value.code).trim());
    fd.append('product_name', String(this.form.value.product_name).trim());
    fd.append('price', String(this.form.value.price));
    fd.append('currency', String(this.form.value.currency || 'EGP').trim().toUpperCase());

    if (this.photo1File) {
      fd.append('photo1', this.photo1File);
    } else if (this.photo1RemoteUrl) {
      fd.append('photo1_url', this.photo1RemoteUrl);
    }
    if (this.photo2File) {
      fd.append('photo2', this.photo2File);
    } else if (this.photo2RemoteUrl) {
      fd.append('photo2_url', this.photo2RemoteUrl);
    }
    if (this.editingItem) {
      if (this.removePhoto1) {
        fd.append('remove_photo1', '1');
      }
      if (this.removePhoto2) {
        fd.append('remove_photo2', '1');
      }
    }

    this.saving = true;
    const wasEdit = !!this.editingItem;
    const req$ = this.editingItem
      ? this.priceListService.updateItem(listId, this.editingItem.id, fd)
      : this.priceListService.createItem(listId, fd);

    req$.subscribe({
      next: () => {
        this.saving = false;
        this.closeForm();
        Swal.fire({
          icon: 'success',
          title: wasEdit ? 'تم التعديل' : 'تمت الإضافة',
          timer: 1200,
          showConfirmButton: false,
        });
        this.loadDetail();
      },
      error: (err) => {
        this.saving = false;
        const msg =
          err?.error?.message ||
          err?.error?.errors?.code?.[0] ||
          'تعذر الحفظ';
        Swal.fire({ icon: 'error', title: msg });
      },
    });
  }

  deleteItem(item: PriceListItem): void {
    if (!this.canDelete() || this.deletingId != null || !this.activeListId) {
      return;
    }

    Swal.fire({
      title: 'حذف المنتج؟',
      text: `سيتم حذف ${item.product_name} (${item.code}) نهائياً.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم، احذف',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#82225e',
    }).then((result) => {
      if (!result.isConfirmed || !this.activeListId) {
        return;
      }
      this.deletingId = item.id;
      this.priceListService.deleteItem(this.activeListId, item.id).subscribe({
        next: () => {
          this.deletingId = null;
          Swal.fire({ icon: 'success', title: 'تم الحذف', timer: 1200, showConfirmButton: false });
          this.loadDetail();
        },
        error: () => {
          this.deletingId = null;
          Swal.fire({ icon: 'error', title: 'تعذر الحذف' });
        },
      });
    });
  }

  trackById(_index: number, item: PriceListItem | PriceListSummary): number {
    return item.id;
  }

  formatPriceDisplay(item: PriceListItem): string {
    const n = Number(item?.price);
    const amount = Number.isFinite(n)
      ? (Number.isInteger(n) ? String(n) : n.toFixed(2))
      : '';
    const currency = (item?.currency || 'EGP').toUpperCase();
    return amount ? `${amount} ${currency}` : currency;
  }

  normalizeCurrency(): void {
    const raw = String(this.form.get('currency')?.value || '').trim().toUpperCase();
    this.form.patchValue({ currency: raw || 'EGP' }, { emitEvent: false });
  }

  async exportPdf(): Promise<void> {
    if (this.exportingPdf || this.loading || !this.items.length) {
      return;
    }
    this.exportingPdf = true;
    try {
      await this.priceListExport.openPrintPreview(this.title, this.items);
    } catch {
      try {
        await this.priceListExport.downloadPdf(this.title, this.items);
      } catch {
        Swal.fire({ icon: 'error', title: 'تعذر إنشاء ملف PDF' });
      }
    } finally {
      this.exportingPdf = false;
    }
  }

  private resetFormMedia(): void {
    this.photo1File = null;
    this.photo2File = null;
    this.photo1Preview = null;
    this.photo2Preview = null;
    this.photo1RemoteUrl = null;
    this.photo2RemoteUrl = null;
    this.removePhoto1 = false;
    this.removePhoto2 = false;
  }
}
