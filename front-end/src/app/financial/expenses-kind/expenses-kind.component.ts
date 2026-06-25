import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { ExpenseKindService } from '../services/expense-kind.service';
import { ToastService } from '../../shared/toast/toast.service';
import { TreeAccountService } from '../../accounting/services/tree-account.service';

interface AccountOption {
  id: number;
  code: string;
  name: string;
  label: string;
}

@Component({
  selector: 'app-expenses-kind',
  templateUrl: './expenses-kind.component.html',
  styleUrls: ['./expenses-kind.component.css']
})
export class ExpensesKindComponent implements OnInit {

  openbtn:boolean=true;
  formdiv:boolean=false;
  errorform:boolean= false;
  addForm:boolean =false;
  addbtn:boolean =false;
  expenseKind:any[]=[];

  errorMessage!:string;
  data:any[]=[];
  savingRowId: number | null = null;

  /** جميع حسابات شجرة الحسابات */
  accountOptions: AccountOption[] = [];
  filteredFormAccounts: AccountOption[] = [];
  formAccountCtrl = new FormControl<string | AccountOption>('', { nonNullable: true });
  rowAccountCtrls = new Map<number, FormControl<string | AccountOption>>();
  rowFilteredAccounts = new Map<number, AccountOption[]>();
  readonly accountAutocompleteCap = 400;
  readonly autoSentinel: AccountOption = {
    id: -1,
    code: '',
    name: '',
    label: '',
  };

  cashOutParentName = 'النقد الصادر';

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50];

  constructor(
    private expenseKindService: ExpenseKindService,
    private treeAccountService: TreeAccountService,
    private toast: ToastService,
  ) {}

  ngOnInit(){
    this.autoSentinel.label = this.autoAccountLabel();
    this.expenseKindService.data().subscribe(result=>this.expenseKind=result);
    this.loadTreeAccounts();
    this.loadCashOutParentName();
    this.getData();

    this.formAccountCtrl.valueChanges.subscribe((v) => {
      this.filteredFormAccounts = this.filterAccountOptions(typeof v === 'string' ? v : '');
    });
  }

  autoAccountLabel(): string {
    return `— تلقائي (تحت ${this.cashOutParentName}) —`;
  }

  displayAccountOption = (value: string | AccountOption | null): string => {
    if (!value) {
      return '';
    }
    if (typeof value === 'string') {
      return value;
    }
    if (value.id === this.autoSentinel.id) {
      return this.autoAccountLabel();
    }
    return value.label;
  };

  accountOptionLabel(acc: AccountOption): string {
    return acc.label;
  }

  private loadCashOutParentName(): void {
    this.expenseKindService.ledgerAccounts().subscribe({
      next: (res) => {
        this.cashOutParentName = res?.parent?.name || 'النقد الصادر';
        this.autoSentinel.label = this.autoAccountLabel();
      },
      error: () => {},
    });
  }

  private loadTreeAccounts(): void {
    this.treeAccountService.getAll().subscribe({
      next: (res) => {
        let list: any[] = [];
        if (res?.data) {
          list = Array.isArray(res.data) ? res.data : [res.data];
        } else if (Array.isArray(res)) {
          list = res;
        }
        this.accountOptions = list
          .filter((a) => a?.id != null && a?.name)
          .map((a) => {
            const code = a.code != null && String(a.code) !== '' ? String(a.code) : '';
            const name = String(a.name);
            return {
              id: Number(a.id),
              code,
              name,
              label: code ? `${code} — ${name}` : name,
            };
          })
          .sort((a, b) => a.label.localeCompare(b.label, 'ar', { numeric: true }));
        this.filteredFormAccounts = this.filterAccountOptions('');
        this.syncRowAccountControls();
      },
      error: () => {
        this.accountOptions = [];
        this.filteredFormAccounts = [];
      },
    });
  }

  private filterAccountOptions(term: string): AccountOption[] {
    const raw = String(term ?? '').trim();
    const q = raw.toLowerCase();
    let list = this.accountOptions;
    if (q) {
      list = list.filter((a) => {
        if (String(a.id).includes(raw)) {
          return true;
        }
        if (a.code && (a.code.includes(raw) || a.code.toLowerCase().includes(q))) {
          return true;
        }
        return a.label.toLowerCase().includes(q) || a.label.includes(raw) || a.name.includes(raw);
      });
    }
    return list.slice(0, this.accountAutocompleteCap);
  }

  onFormAccountFocus(): void {
    const v = this.formAccountCtrl.value;
    this.filteredFormAccounts = this.filterAccountOptions(typeof v === 'string' ? v : '');
  }

  onFormAccountSelected(event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as AccountOption;
    if (!acc || acc.id === this.autoSentinel.id) {
      this.form.patchValue({ tree_account_id: null });
      this.formAccountCtrl.setValue('', { emitEvent: false });
      return;
    }
    this.form.patchValue({ tree_account_id: acc.id });
    this.formAccountCtrl.setValue(acc, { emitEvent: false });
    this.filteredFormAccounts = this.filterAccountOptions('');
  }

  onFormAccountBlur(): void {
    setTimeout(() => this.syncFormAccountOnBlur(), 150);
  }

  private syncFormAccountOnBlur(): void {
    const v = this.formAccountCtrl.value;
    if (v && typeof v === 'object') {
      if (v.id === this.autoSentinel.id) {
        this.form.patchValue({ tree_account_id: null });
        return;
      }
      this.form.patchValue({ tree_account_id: v.id });
      return;
    }
    const str = typeof v === 'string' ? v.trim() : '';
    if (!str) {
      this.form.patchValue({ tree_account_id: null });
      return;
    }
    const exact = this.accountOptions.find((a) => a.label === str);
    if (exact) {
      this.form.patchValue({ tree_account_id: exact.id });
      this.formAccountCtrl.setValue(exact, { emitEvent: false });
      return;
    }
    const partial = this.filterAccountOptions(str);
    if (partial.length === 1) {
      this.form.patchValue({ tree_account_id: partial[0].id });
      this.formAccountCtrl.setValue(partial[0], { emitEvent: false });
    }
  }

  getRowAccountCtrl(elm: { id: number }): FormControl<string | AccountOption> {
    if (!this.rowAccountCtrls.has(elm.id)) {
      const ctrl = new FormControl<string | AccountOption>('', { nonNullable: true });
      ctrl.valueChanges.subscribe((v) => {
        this.rowFilteredAccounts.set(elm.id, this.filterAccountOptions(typeof v === 'string' ? v : ''));
      });
      this.rowAccountCtrls.set(elm.id, ctrl);
      this.rowFilteredAccounts.set(elm.id, this.filterAccountOptions(''));
      this.syncRowCtrlFromId(elm);
    }
    return this.rowAccountCtrls.get(elm.id)!;
  }

  getRowFilteredAccounts(elm: { id: number }): AccountOption[] {
    return this.rowFilteredAccounts.get(elm.id) ?? this.filterAccountOptions('');
  }

  onRowAccountFocus(elm: { id: number }): void {
    const ctrl = this.getRowAccountCtrl(elm);
    const v = ctrl.value;
    this.rowFilteredAccounts.set(elm.id, this.filterAccountOptions(typeof v === 'string' ? v : ''));
  }

  onRowAccountSelected(elm: any, event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as AccountOption;
    if (!acc || acc.id === this.autoSentinel.id) {
      elm.tree_account_id = null;
      this.getRowAccountCtrl(elm).setValue('', { emitEvent: false });
      return;
    }
    elm.tree_account_id = acc.id;
    this.getRowAccountCtrl(elm).setValue(acc, { emitEvent: false });
    this.rowFilteredAccounts.set(elm.id, this.filterAccountOptions(''));
  }

  onRowAccountBlur(elm: any): void {
    setTimeout(() => this.syncRowAccountOnBlur(elm), 150);
  }

  private syncRowAccountOnBlur(elm: any): void {
    const ctrl = this.getRowAccountCtrl(elm);
    const v = ctrl.value;
    if (v && typeof v === 'object') {
      if (v.id === this.autoSentinel.id) {
        elm.tree_account_id = null;
        return;
      }
      elm.tree_account_id = v.id;
      return;
    }
    const str = typeof v === 'string' ? v.trim() : '';
    if (!str) {
      elm.tree_account_id = null;
      return;
    }
    const exact = this.accountOptions.find((a) => a.label === str);
    if (exact) {
      elm.tree_account_id = exact.id;
      ctrl.setValue(exact, { emitEvent: false });
      return;
    }
    const partial = this.filterAccountOptions(str);
    if (partial.length === 1) {
      elm.tree_account_id = partial[0].id;
      ctrl.setValue(partial[0], { emitEvent: false });
    }
  }

  private syncRowAccountControls(): void {
    const activeIds = new Set(this.data.map((r) => r.id));
    for (const id of Array.from(this.rowAccountCtrls.keys())) {
      if (!activeIds.has(id)) {
        this.rowAccountCtrls.delete(id);
        this.rowFilteredAccounts.delete(id);
      }
    }
    for (const row of this.data) {
      if (!this.rowAccountCtrls.has(row.id)) {
        const ctrl = new FormControl<string | AccountOption>('', { nonNullable: true });
        ctrl.valueChanges.subscribe((v) => {
          this.rowFilteredAccounts.set(row.id, this.filterAccountOptions(typeof v === 'string' ? v : ''));
        });
        this.rowAccountCtrls.set(row.id, ctrl);
        this.rowFilteredAccounts.set(row.id, this.filterAccountOptions(''));
      }
      this.syncRowCtrlFromId(row);
    }
  }

  private syncRowCtrlFromId(row: { id: number; tree_account_id?: number | null }): void {
    const ctrl = this.rowAccountCtrls.get(row.id);
    if (!ctrl) {
      return;
    }
    const id = row.tree_account_id;
    if (id == null) {
      ctrl.setValue('', { emitEvent: false });
      return;
    }
    const opt = this.accountOptions.find((a) => a.id === Number(id));
    if (opt) {
      ctrl.setValue(opt, { emitEvent: false });
    }
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
      this.page = event.pageIndex;
      this.getData();
  }

  search(e:any){

    if(e.target.id == 'type'){
      this.param['type']=e.target.value;
    }
    if(e.target.id == 'state'){
      this.param['state']=e.target.value;
    }
    this.getData();

  }

  param = {};
  getData(){

    this.expenseKindService.search(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      this.data = (res.data || []).map((row: any) => ({
        ...row,
        expense_type: row.expense_type || null,
        tree_account_id: row.tree_account_id != null ? Number(row.tree_account_id) : null,
      }));
      this.length=res.total;
      this.pageSize=res.per_page;
      this.syncRowAccountControls();
    })
  }

  form:FormGroup = new FormGroup({
    'expense_type' :new FormControl(null , [Validators.required ]),
    'expense_kind' :new FormControl(null , [Validators.required ]),
    'tree_account_id': new FormControl<number | null>(null),
  })

  openForm(){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = true;
    this.addbtn = true;
    this.form.patchValue({
      expense_type: null,
      expense_kind: null,
      tree_account_id: null,
    });
    this.formAccountCtrl.setValue('', { emitEvent: false });
    this.filteredFormAccounts = this.filterAccountOptions('');
  }

  submitform(){
    if (this.addForm) {
      if (this.form.valid) {
        const v = this.form.value;
        const payload = {
          expense_type: v.expense_type,
          expense_kind: v.expense_kind,
          tree_account_id: v.tree_account_id != null && v.tree_account_id !== '' ? Number(v.tree_account_id) : null,
        };
        this.expenseKindService.add(payload).subscribe({
          next: () => {
            this.toast.success('تمت إضافة الفئة وربطها بالنوع');
            this.openbtn = true;
            this.formdiv = false;
            this.expenseKindService.data().subscribe((r) => (this.expenseKind = r));
            this.getData();
            this.form.reset({ expense_type: null, expense_kind: null, tree_account_id: null });
            this.formAccountCtrl.setValue('', { emitEvent: false });
          },
          error: (err) => {
            this.toast.error(err.error?.message || 'تعذر الحفظ');
          }
        });
      }
    }
  }

  saveRow(elm: any): void {
    const expense_kind = (elm.expense_kind || '').toString().trim();
    const expense_type = elm.expense_type;
    if (!expense_kind) {
      this.toast.warning('أدخل اسم فئة المصروف');
      return;
    }
    if (!expense_type) {
      this.toast.warning('اختر نوع المصروف الرئيسي');
      return;
    }
    this.savingRowId = elm.id;
    this.expenseKindService.update(elm.id, {
      expense_type,
      expense_kind,
      tree_account_id: elm.tree_account_id != null && elm.tree_account_id !== '' ? Number(elm.tree_account_id) : null,
    }).subscribe({
      next: () => {
        this.toast.success('تم حفظ الربط');
        this.savingRowId = null;
        this.expenseKindService.data().subscribe((r) => (this.expenseKind = r));
        this.getData();
      },
      error: (err) => {
        this.savingRowId = null;
        this.toast.error(err.error?.message || 'تعذر التحديث');
      }
    });
  }

  deleteData(id:number){
    this.expenseKindService.deleteUser(id).subscribe(result=>{
      if (result == "deleted sucuessfully") {
        this.getData();
      }
    },
    (error)=>{
      console.log(error);

      if (error.status == 404 && error.statusText == 'Not Found') {
        alert(error.statusText);
      }
    }
    )
  }

}
