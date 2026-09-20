import { HttpClient } from '@angular/common/http';
import { Component, Inject, OnDestroy } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { Subscription } from 'rxjs';
import { TreeAccountService } from 'src/app/accounting/services/tree-account.service';
import { DialogPayMoneyForSupplierComponent } from 'src/app/suppliers/dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import { CompaniesService } from '../services/companies.service';

interface TreeAccountOption {
  id: number;
  label: string;
}

@Component({
  selector: 'app-dialog-add-company',
  templateUrl: './dialog-add-company.component.html',
  styleUrls: ['./dialog-add-company.component.css']
})
export class DialogAddCompanyComponent implements OnDestroy {

  location: any[] = [];
  cities: any[] = [];
  governName = false;

  isEdit = false;
  companyId: number | null = null;

  treeAccountOptions: TreeAccountOption[] = [];
  filteredTreeAccounts: TreeAccountOption[] = [];
  treeAccountCtrl = new FormControl<TreeAccountOption | string | null>('');
  treeAccountAutocompleteCap = 80;
  private treeAccountSub?: Subscription;

  govern(event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    this.governName = value === 'القاهرة';
  }

  constructor(
    public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private companyService: CompaniesService,
    private http: HttpClient,
    private treeAccountService: TreeAccountService,
  ) {}

  ngOnInit() {
    this.isEdit = !!this.data?.company;
    this.companyId = this.data?.company?.id ?? null;

    this.http.get('assets/egypt/governorates.json').subscribe((data: any) => this.location = data);
    this.http.get('assets/egypt/cities.json').subscribe((data: any) => {
      this.cities = data.filter((elem: any) => elem.governorate_id == 1);
    });

    this.treeAccountSub = this.treeAccountCtrl.valueChanges.subscribe((v) => {
      const term = typeof v === 'string' ? v : '';
      this.applyTreeAccountFilter(term);
    });
    this.loadTreeAccounts();

    if (this.isEdit && this.data.company) {
      const c = this.data.company;
      this.governName = c.governorate === 'القاهرة';
      this.form.patchValue({
        name: c.name,
        phone1: c.phone1,
        phone2: c.phone2 === 'null' ? null : c.phone2,
        phone3: c.phone3 === 'null' ? null : c.phone3,
        phone4: c.phone4 === 'null' ? null : c.phone4,
        tel: c.tel === 'null' ? null : c.tel,
        governorate: c.governorate,
        city: c.city === 'null' ? null : c.city,
        address: c.address,
        tree_account_id: c.tree_account_id ?? c.tree_account?.id ?? null,
      });
      this.syncTreeAccountAutocompleteFromForm(c.tree_account);
    } else {
      this.form.patchValue({
        governorate: 'المحافظة',
        city: 'المدينة',
      });
    }
  }

  ngOnDestroy(): void {
    this.treeAccountSub?.unsubscribe();
  }

  form: FormGroup = new FormGroup({
    'name': new FormControl(null, [Validators.required]),
    'phone1': new FormControl(null, [Validators.required, Validators.pattern('^01\\d{9}$')]),
    'phone2': new FormControl(null, [Validators.pattern('^01\\d{9}$')]),
    'phone3': new FormControl(null, [Validators.pattern('^01\\d{9}$')]),
    'phone4': new FormControl(null, [Validators.pattern('^01\\d{9}$')]),
    'tel': new FormControl(null),
    'governorate': new FormControl(null, [Validators.required]),
    'city': new FormControl(null),
    'address': new FormControl(null, [Validators.required]),
    'tree_account_id': new FormControl<number | null>(null),
  });

  displayTreeAccount = (v: TreeAccountOption | string | null): string => {
    if (!v) {
      return '';
    }
    return typeof v === 'string' ? v : v.label;
  };

  onTreeAccountFocus(): void {
    const searchText = typeof this.treeAccountCtrl.value === 'string' ? this.treeAccountCtrl.value : '';
    this.applyTreeAccountFilter(searchText);
  }

  onTreeAccountSelected(event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as TreeAccountOption;
    if (!acc?.id) {
      return;
    }
    this.form.patchValue({ tree_account_id: acc.id });
    this.treeAccountCtrl.setValue(acc, { emitEvent: false });
    this.applyTreeAccountFilter('');
  }

  onTreeAccountBlur(): void {
    setTimeout(() => {
      const v = this.treeAccountCtrl.value;
      if (v && typeof v === 'object') {
        return;
      }
      const str = typeof v === 'string' ? v.trim() : '';
      const id = this.form.get('tree_account_id')?.value;

      if (str === '') {
        this.form.patchValue({ tree_account_id: null });
        return;
      }

      const byId = id != null ? this.treeAccountOptions.find((x) => x.id === Number(id)) : undefined;
      if (byId && str === byId.label) {
        return;
      }

      const exact = this.treeAccountOptions.find((x) => x.label === str);
      if (exact) {
        this.form.patchValue({ tree_account_id: exact.id });
        this.treeAccountCtrl.setValue(exact, { emitEvent: false });
        return;
      }

      this.form.patchValue({ tree_account_id: null });
      this.treeAccountCtrl.setValue(str, { emitEvent: false });
    }, 150);
  }

  private applyTreeAccountFilter(term: string): void {
    const raw = (term || '').toString().trim();
    const q = raw.toLowerCase();
    let list = this.treeAccountOptions;
    if (q) {
      list = list.filter((a) => {
        if (String(a.id).includes(raw)) {
          return true;
        }
        return a.label.toLowerCase().includes(q) || a.label.includes(raw);
      });
    }
    this.filteredTreeAccounts = list.slice(0, this.treeAccountAutocompleteCap);
  }

  private syncTreeAccountAutocompleteFromForm(account?: { id?: number; code?: string; name?: string } | null): void {
    const id = this.form.get('tree_account_id')?.value;
    if (id == null) {
      this.treeAccountCtrl.setValue('', { emitEvent: false });
      this.applyTreeAccountFilter('');
      return;
    }
    const opt = this.treeAccountOptions.find((x) => x.id === Number(id));
    if (opt) {
      this.treeAccountCtrl.setValue(opt, { emitEvent: false });
    } else if (account?.id) {
      const code = account.code != null && account.code !== '' ? String(account.code) : '';
      const label = code ? `${code} — ${account.name}` : String(account.name || id);
      this.treeAccountCtrl.setValue({ id: Number(account.id), label }, { emitEvent: false });
    } else {
      this.treeAccountCtrl.setValue('', { emitEvent: false });
    }
    this.applyTreeAccountFilter('');
  }

  private loadTreeAccounts(): void {
    this.treeAccountService.getAll().subscribe({
      next: (res) => {
        const raw = (res as any)?.data;
        const arr = Array.isArray(raw) ? raw : raw ? [raw] : [];
        const flat = this.flattenAccounts(arr);
        if (flat.length > 0) {
          this.treeAccountOptions = flat;
          this.syncTreeAccountAutocompleteFromForm(this.data?.company?.tree_account);
          return;
        }
        this.treeAccountService.getTree().subscribe({
          next: (treeRes: any) => {
            const t = treeRes?.data ?? treeRes;
            const tArr = Array.isArray(t) ? t : t ? [t] : [];
            this.treeAccountOptions = this.flattenAccounts(tArr);
            this.syncTreeAccountAutocompleteFromForm(this.data?.company?.tree_account);
          },
          error: () => {
            this.treeAccountOptions = [];
            this.applyTreeAccountFilter('');
          },
        });
      },
      error: () => {
        this.treeAccountOptions = [];
        this.applyTreeAccountFilter('');
      },
    });
  }

  private flattenAccounts(nodes: any[]): TreeAccountOption[] {
    const out: TreeAccountOption[] = [];
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

  onCloseClick(): void {
    this.dialogRef.close();
  }

  submitform() {
    if (!this.form.valid) {
      return;
    }

    const data = this.form.value;
    const payload: Record<string, unknown> = {
      name: data.name,
      phone1: data.phone1,
      phone2: data.phone2 ?? '',
      phone3: data.phone3 ?? '',
      phone4: data.phone4 ?? '',
      tel: data.tel ?? '',
      governorate: data.governorate,
      address: data.address,
      tree_account_id: data.tree_account_id != null && data.tree_account_id !== ''
        ? Number(data.tree_account_id)
        : null,
    };
    if (data.city && data.city !== 'المدينة') {
      payload['city'] = data.city;
    }

    const request$ = this.isEdit && this.companyId
      ? this.companyService.updateCompany(this.companyId, payload)
      : this.companyService.addCompany(payload);

    request$.subscribe({
      next: (result) => {
        if (result.message == 'success') {
          this.onCloseClick();
          this.data.refreshData();
        }
      },
      error: (error) => {
        this.errormessage = true;
        this.existName = '';
        this.existPhone = '';
        if (error.error?.errors?.name) {
          this.existName = '.الشركة موجودة بالفعل ';
        }
        if (error.error?.errors?.phone1) {
          this.existPhone = '.الرقم مستخدم من قبل شركة ';
        }
        if (error.error?.message && !error.error?.errors) {
          this.existName = error.error.message;
        }
      },
    });
  }

  existName!: string;
  existPhone!: string;
  errormessage = false;
}
