import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ShippingCompanyService } from '../services/shipping-company.service';
import { AuthService } from 'src/app/auth/auth.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { TreeAccountService } from 'src/app/accounting/services/tree-account.service';

export interface ReceivableAccountOption {
  id: number;
  label: string;
}

@Component({
  selector: 'app-shipping-company',
  templateUrl: './shipping-company.component.html',
  styleUrls: ['./shipping-company.component.css']
})
export class ShippingCompanyComponent implements OnInit {

  openbtn:boolean=true;
  formdiv:boolean=false;
  errorform:boolean= false;
  addForm:boolean =false;
  addbtn:boolean =false;
  editForm:boolean =false;
  editbtn:boolean =false;

  errorMessage!:string;
  data:any[]=[];

  user!:string;

  receivableAccountOptions: ReceivableAccountOption[] = [];
  filteredReceivableAccounts: ReceivableAccountOption[] = [];

  receivableAccountCtrl = new FormControl<string | ReceivableAccountOption>('', { nonNullable: true });

  readonly receivableAutocompleteCap = 400;

  unlinkedCount = 0;
  showUnlinkedOnly = false;
  linkingAll = false;
  linkingId: number | null = null;

  displayReceivableAccount = (value: string | ReceivableAccountOption): string => {
    if (!value) {
      return '';
    }
    if (typeof value === 'string') {
      return value;
    }
    return value.label;
  };

  constructor(
    private shippingCompany: ShippingCompanyService,
    private authService: AuthService,
    public rbac: RbacService,
    private treeAccountService: TreeAccountService,
    private snackBar: MatSnackBar,
  ) {}

  canManageCompanies(): boolean {
    return this.rbac.canAny(['shipping.companies.manage', 'system.rbac']);
  }

  canViewCompanyStatement(): boolean {
    return this.rbac.canAny(['shipping.companies.statement', 'system.rbac']);
  }

  ngOnInit(){
    this.user = this.authService.getUser();
    this.receivableAccountCtrl.valueChanges.subscribe((v) => {
      const searchText = typeof v === 'string' ? v : '';
      this.applyReceivableFilter(searchText);
    });
    this.loadTreeAccounts();
    this.loadUnlinkedSummary();
    this.getData();
  }

  loadUnlinkedSummary(): void {
    if (!this.canManageCompanies()) {
      return;
    }
    this.shippingCompany.unlinkedSummary().subscribe({
      next: (res: any) => {
        this.unlinkedCount = res?.unlinked_count ?? 0;
      },
    });
  }

  isLinked(item: any): boolean {
    return !!(item?.receivable_tree_account_id || item?.receivable_tree_account?.id);
  }

  toggleUnlinkedOnly(): void {
    this.showUnlinkedOnly = !this.showUnlinkedOnly;
    this.getData();
  }

  linkAllUnlinked(): void {
    if (this.unlinkedCount < 1) {
      return;
    }
    if (!confirm(`سيتم ربط ${this.unlinkedCount} شركة/مندوب غير مربوط بحسابات تحت «شركات الشحن والمناديب» في شجرة الحسابات. المتابعة؟`)) {
      return;
    }

    this.linkingAll = true;
    this.shippingCompany.linkUnlinked().subscribe({
      next: (res: any) => {
        this.linkingAll = false;
        this.snackBar.open(res?.message || 'تم الربط بنجاح', 'إغلاق', { duration: 5000 });
        this.loadUnlinkedSummary();
        this.getData();
      },
      error: (err) => {
        this.linkingAll = false;
        this.snackBar.open(err.error?.message || 'فشل ربط الشركات', 'إغلاق', { duration: 6000 });
      },
    });
  }

  linkOne(company: any): void {
    if (this.isLinked(company)) {
      return;
    }
    this.linkingId = company.id;
    this.shippingCompany.linkAccount(company.id).subscribe({
      next: (res: any) => {
        this.linkingId = null;
        const code = res?.receivable_tree_account?.code ? ` (${res.receivable_tree_account.code})` : '';
        this.snackBar.open(`تم ربط ${company.name} بالحساب${code}`, 'إغلاق', { duration: 4000 });
        this.loadUnlinkedSummary();
        this.getData();
      },
      error: (err) => {
        this.linkingId = null;
        this.snackBar.open(err.error?.message || 'فشل ربط الشركة', 'إغلاق', { duration: 5000 });
      },
    });
  }

  private applyReceivableOptions(flat: ReceivableAccountOption[]): void {
    this.receivableAccountOptions = flat;
    const searchText = typeof this.receivableAccountCtrl.value === 'string' ? this.receivableAccountCtrl.value : '';
    this.applyReceivableFilter(searchText);
    this.syncReceivableAutocompleteFromForm();
  }

  private applyReceivableFilter(searchRaw: string): void {
    const raw = String(searchRaw ?? '').trim();
    const q = raw.toLowerCase();
    if (!q) {
      this.filteredReceivableAccounts = this.receivableAccountOptions.slice(0, this.receivableAutocompleteCap);
      return;
    }
    this.filteredReceivableAccounts = this.receivableAccountOptions.filter((a) => {
      if (String(a.id).includes(raw)) {
        return true;
      }
      const lab = a.label;
      if (lab.toLowerCase().includes(q)) {
        return true;
      }
      return lab.includes(raw);
    });
  }

  /** عند فتح الحقل: إظهار أول دفعة من الخيارات */
  onReceivableAccountFocus(): void {
    const searchText = typeof this.receivableAccountCtrl.value === 'string' ? this.receivableAccountCtrl.value : '';
    this.applyReceivableFilter(searchText);
  }

  onReceivableAccountSelected(event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as ReceivableAccountOption;
    if (!acc?.id) {
      return;
    }
    this.form.patchValue({ receivable_tree_account_id: acc.id });
    this.form.get('receivable_tree_account_id')?.markAsTouched();
    this.receivableAccountCtrl.setValue(acc, { emitEvent: false });
    this.applyReceivableFilter('');
  }

  /**
   * عند الخروج من الحقل: إذا بقي نص غير مطابق لخيار محدد، نفرّغ الاختيار أو نطابق اسماً كاملاً
   * تأخير بسيط حتى يُنفَّذ (optionSelected) قبل الـ blur عند الاختيار من القائمة
   */
  onReceivableAccountBlur(): void {
    setTimeout(() => {
      const v = this.receivableAccountCtrl.value;
      if (v && typeof v === 'object') {
        return;
      }
      const str = typeof v === 'string' ? v.trim() : '';
      const id = this.form.get('receivable_tree_account_id')?.value;

      if (str === '') {
        this.form.patchValue({ receivable_tree_account_id: null });
        return;
      }

      const byId = id != null ? this.receivableAccountOptions.find((x) => x.id === Number(id)) : undefined;
      if (byId && str === byId.label) {
        return;
      }

      const exact = this.receivableAccountOptions.find((x) => x.label === str);
      if (exact) {
        this.form.patchValue({ receivable_tree_account_id: exact.id });
        this.receivableAccountCtrl.setValue(exact, { emitEvent: false });
        return;
      }

      this.form.patchValue({ receivable_tree_account_id: null });
      this.receivableAccountCtrl.setValue(str, { emitEvent: false });
    }, 150);
  }

  private syncReceivableAutocompleteFromForm(): void {
    const id = this.form.get('receivable_tree_account_id')?.value;
    if (id == null) {
      this.receivableAccountCtrl.setValue('', { emitEvent: false });
      this.applyReceivableFilter('');
      return;
    }
    const opt = this.receivableAccountOptions.find((x) => x.id === Number(id));
    if (opt) {
      this.receivableAccountCtrl.setValue(opt, { emitEvent: false });
    } else {
      this.receivableAccountCtrl.setValue('', { emitEvent: false });
    }
    this.applyReceivableFilter('');
  }

  private loadTreeAccounts(): void {
    this.treeAccountService.getAll().subscribe({
      next: (res) => {
        const raw = (res as any)?.data;
        const arr = Array.isArray(raw) ? raw : raw ? [raw] : [];
        const flat = this.flattenAccounts(arr);
        if (flat.length > 0) {
          this.applyReceivableOptions(flat);
          return;
        }
        this.treeAccountService.getTree().subscribe({
          next: (treeRes: any) => {
            const t = treeRes?.data ?? treeRes;
            const tArr = Array.isArray(t) ? t : t ? [t] : [];
            this.applyReceivableOptions(this.flattenAccounts(tArr));
          },
          error: () => {
            this.applyReceivableOptions([]);
          },
        });
      },
      error: () => {
        this.applyReceivableOptions([]);
      },
    });
  }

  /** قائمة مسطحة لاختيار حساب الذمم (شجرة الحسابات) */
  private flattenAccounts(nodes: any[]): ReceivableAccountOption[] {
    const out: ReceivableAccountOption[] = [];
    const walk = (list: any[]) => {
      for (const n of list || []) {
        if (n?.id != null && n?.name) {
          const code = n.code != null && n.code !== '' ? String(n.code) : '';
          out.push({
            id: Number(n.id),
            label: code ? `${code} — ${n.name}` : String(n.name),
          });
        }
        if (Array.isArray(n?.children) && n.children.length) {
          walk(n.children);
        }
      }
    };
    walk(nodes);
    return out.sort((a, b) => a.label.localeCompare(b.label, 'ar'));
  }

  getData(){
    return this.shippingCompany.shippingCompanies().subscribe(result=>{
      const rows = result || [];
      this.data = this.showUnlinkedOnly
        ? rows.filter((item: any) => !this.isLinked(item))
        : rows;
    })
  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'type' :new FormControl(null , [Validators.required ]),
    'receivable_tree_account_id': new FormControl<number | null>(null),
  })

  openForm(){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = true;
    this.editForm = false;
    this.addbtn = true;
    this.editbtn = false;
    this.errorform = false;
    this.form.reset();
    this.form.patchValue({
      type:"مندوب",
      receivable_tree_account_id: null,
    });
    this.receivableAccountCtrl.setValue('', { emitEvent: false });
    this.applyReceivableFilter('');
  }

  submitform(){
    if (!this.form.valid) {
      this.form.markAllAsTouched();
    }
    if (this.addForm) {
      if (this.form.valid) {
        const payload = this.buildPayload();
        this.shippingCompany.addLine(payload).subscribe(result=>{
          if (result) {
            this.openbtn = true;
            this.formdiv = false;
            this.loadUnlinkedSummary();
            this.getData();
            this.form.reset();
            this.receivableAccountCtrl.setValue('', { emitEvent: false });
            this.applyReceivableFilter('');
          }
        },
        (error)=>{
          this.applySubmitError(error);
        }
        )
      }
    } else if (this.editForm) {
      if (this.form.valid) {
        const payload = this.buildPayload();
        this.shippingCompany.editLine(payload , this.editId).subscribe(result=>{

          if (result) {
            this.openbtn = true;
            this.formdiv = false;
            this.loadUnlinkedSummary();
            this.getData();
            this.form.reset();
            this.receivableAccountCtrl.setValue('', { emitEvent: false });
            this.applyReceivableFilter('');
          }
        },
        (error)=>{
          this.applySubmitError(error);
        }
        )
      }
    }

  }

  private buildPayload(): { name: string; type: string; receivable_tree_account_id: number | null } {
    const v = this.form.getRawValue();
    const accountId = v.receivable_tree_account_id;
    return {
      name: v.name,
      type: v.type,
      receivable_tree_account_id: accountId != null && accountId !== '' ? Number(accountId) : null,
    };
  }

  private applySubmitError(error: any): void {
    this.errorform = true;
    if (error?.status === 422 && error.error?.message === 'The name has already been taken.') {
      this.errorMessage = 'هذا الاسم تم إضافته من قبل';
      return;
    }
    const errs = error?.error?.errors;
    if (errs && typeof errs === 'object') {
      const first = Object.values(errs).flat()[0];
      if (first != null) {
        this.errorMessage = String(first);
        return;
      }
    }
    if (error?.error?.message) {
      this.errorMessage =
        typeof error.error.message === 'string'
          ? error.error.message
          : JSON.stringify(error.error.message);
      return;
    }
    this.errorMessage = 'تعذر الحفظ. تحقق من البيانات.';
  }

  deleteData(id:number){
    this.shippingCompany.deleteLine(id).subscribe(result=>{
      if (result === "deleted") {
        this.getData();
      }

    })
  }

  editId!:number;
  editData(id:number , name:string , type:string, receivableTreeAccountId?: number | null){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = false;
    this.editForm = true;
    this.addbtn = false;
    this.editbtn = true;
    this.editId = id;
    this.errorform = false;
    this.form.patchValue({
      name,
      type,
      receivable_tree_account_id: receivableTreeAccountId != null ? Number(receivableTreeAccountId) : null,
    });
    this.syncReceivableAutocompleteFromForm();
    setTimeout(() => {
      document.getElementById('shipping-company-form-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 0);
  }
}
