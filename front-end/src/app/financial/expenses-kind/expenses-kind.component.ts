import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ExpenseKindService } from '../services/expense-kind.service';
import { ToastService } from '../../shared/toast/toast.service';
import { TreeAccountService } from '../../accounting/services/tree-account.service';

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

  /** حسابات مصروف طرفية (للقيد المدين) */
  expenseLeafAccounts: { id: number; code?: string; name: string }[] = [];

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50];

  constructor(
    private expenseKindService: ExpenseKindService,
    private toast: ToastService,
    private treeAccountService: TreeAccountService
  ) {}

  ngOnInit(){
    this.expenseKindService.data().subscribe(result=>this.expenseKind=result);
    this.loadExpenseAccounts();
    this.getData();
  }

  private loadExpenseAccounts(): void {
    this.treeAccountService.getAll().subscribe({
      next: (res: any) => {
        const raw = res?.data ?? res ?? [];
        const list = Array.isArray(raw) ? raw : [];
        const flat = this.flattenAccounts(list);
        this.expenseLeafAccounts = flat
          .filter((a: any) => a?.type === 'expense' && (!a.children || a.children.length === 0))
          .map((a: any) => ({
            id: a.id,
            code: a.code,
            name: a.name,
          }))
          .sort((a, b) => String(a.code).localeCompare(String(b.code), undefined, { numeric: true }));
      },
      error: () => {
        this.expenseLeafAccounts = [];
      },
    });
  }

  private flattenAccounts(accounts: any[], result: any[] = []): any[] {
    (accounts || []).forEach((acc) => {
      result.push(acc);
      if (acc.children && acc.children.length) {
        this.flattenAccounts(acc.children, result);
      }
    });
    return result;
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
    })
  }

  // getData(){
  //   this.expenseService.data().subscribe(result=>this.data=result)
  // }

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

