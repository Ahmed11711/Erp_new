import { Component, OnDestroy } from '@angular/core';
import { Subject, Subscription } from 'rxjs';
import { debounceTime, takeUntil } from 'rxjs/operators';
import { EmployeeService } from '../services/employee.service';
import { MatDialog } from '@angular/material/dialog';
import { DialogLinkEmployeeAccountComponent } from '../dialog-link-employee-account/dialog-link-employee-account.component';

@Component({
  selector: 'app-employee',
  templateUrl: './employee.component.html',
  styleUrls: ['./employee.component.css']
})
export class EmployeeComponent implements OnDestroy {

  data:any[]=[];
  tableData:any[]=[];

  length = 0;
  pageSize = 20;
  page = 0;
  pageSizeOptions = [20,50];
  listLoading = false;
  listError = false;
  param: any = {};
  private listRequest?: Subscription;
  private readonly searchInput$ = new Subject<void>();
  private readonly destroy$ = new Subject<void>();

  constructor(
    private employeeService: EmployeeService,
    private dialog: MatDialog,
  ) {}

  ngOnInit(){
    this.searchInput$.pipe(debounceTime(300), takeUntil(this.destroy$)).subscribe(() => this.fetchEmployees());
    this.search(arguments);
    // this.getData();
  }

  ngOnDestroy(): void {
    this.listRequest?.unsubscribe();
    this.destroy$.next();
    this.destroy$.complete();
  }

  trackByEmployeeId(_index: number, elm: any): number | string {
    return elm?.id ?? _index;
  }


onPageChange(event:any){
  this.pageSize = event.pageSize;
  this.page = event.pageIndex;
  this.search(arguments);
}

onSearchField(e: Event) {
  const target = e?.target as HTMLInputElement | null;
  const id = target?.id;
  const value = String(target?.value ?? '').trim();
  if (id === 'name') {
    if (value) {
      this.param['name'] = value;
    } else {
      delete this.param['name'];
    }
  }
  if (id === 'code') {
    if (value) {
      this.param['code'] = value;
    } else {
      delete this.param['code'];
    }
  }
  this.page = 0;
  this.searchInput$.next();
}

private employeeQueryParams(): any {
  const params: any = {};
  const name = String(this.param['name'] || '').trim();
  const code = String(this.param['code'] || '').trim();
  if (name) {
    params.name = name;
  }
  if (code) {
    params.code = code;
  }
  return params;
}

search(e:any){
  if(e?.target?.id === "name" || e?.target?.id === "code"){
    this.onSearchField(e);
    return;
  }
  this.fetchEmployees();
}

private fetchEmployees(){
  this.listLoading = true;
  this.listError = false;
  this.listRequest?.unsubscribe();
  this.listRequest = this.employeeService.searchEmployee(this.pageSize,this.page+1,this.employeeQueryParams()).subscribe({
    next: (data:any)=>{
      this.data = Array.isArray(data?.data) ? data.data : [];
      this.length = Number(data?.total ?? 0);
      this.pageSize = Number(data?.per_page ?? this.pageSize);
      this.listLoading = false;
    },
    error: () => {
      this.data = [];
      this.length = 0;
      this.listLoading = false;
      this.listError = true;
    },
  });
}


  deleteData(id:number){
    if (!confirm('هل أنت متأكد من حذف هذا الموظف؟ سيتم حذف الموظف وسجلات البصمة فقط، مع الإبقاء على المرتبات والسلف والخصومات باسمه.')) {
      return;
    }

    this.employeeService.deleteEmp(id).subscribe({
      next: (result) => {
        const ok =
          result === 'deleted sucuessfully' ||
          result?.message === 'deleted sucuessfully' ||
          result?.message === 'تم الحذف بنجاح';
        if (ok) {
          this.search('');
        }
      },
      error: (error) => {
        const message =
          error?.error?.message ||
          (error.status === 403 ? 'غير مسموح بحذف الموظف' : null) ||
          (error.status === 404 ? 'الموظف غير موجود' : null) ||
          'تعذر حذف الموظف';
        alert(message);
      },
    });
  }

  accountLabel(elm: any): string {
    const acc = elm?.payable_tree_account;
    if (!acc) {
      return '';
    }
    const code = acc.code != null ? String(acc.code) : '';
    return code ? `${code} - ${acc.name}` : String(acc.name ?? '');
  }

  openLinkAccountDialog(elm: any): void {
    const ref = this.dialog.open(DialogLinkEmployeeAccountComponent, {
      width: '480px',
      maxHeight: '90vh',
      panelClass: 'link-employee-account-dialog',
      autoFocus: false,
      data: {
        employeeId: elm.id,
        employeeName: elm.name,
        payableTreeAccountId: elm.payable_tree_account_id ?? null,
      },
    });
    ref.afterClosed().subscribe((result) => {
      if (result) {
        elm.payable_tree_account_id = result.payable_tree_account_id;
        elm.payable_tree_account = result.payable_tree_account ?? null;
      }
    });
  }

}
