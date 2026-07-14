import { Component } from '@angular/core';
import { EmployeeService } from '../services/employee.service';
import { MatDialog } from '@angular/material/dialog';
import { DialogLinkEmployeeAccountComponent } from '../dialog-link-employee-account/dialog-link-employee-account.component';

@Component({
  selector: 'app-employee',
  templateUrl: './employee.component.html',
  styleUrls: ['./employee.component.css']
})
export class EmployeeComponent {

  data:any[]=[];
  tableData:any[]=[];

  length = 50;
  pageSize = 20;
  page = 0;
  pageSizeOptions = [20,50];

  constructor(
    private employeeService: EmployeeService,
    private dialog: MatDialog,
  ) {}

  ngOnInit(){
    this.search(arguments);
    // this.getData();
  }


onPageChange(event:any){
  this.pageSize = event.pageSize;
  this.page = event.pageIndex;
  this.search(arguments);
}

search(e:any){
  const param = {};
  if(e?.target?.id === "name"){
    param['name']=e?.target?.value;
  }
  if(e?.target?.id === "code"){
    param['code']=e?.target?.value;
  }

  this.employeeService.searchEmployee(this.pageSize,this.page+1,param).subscribe((data:any)=>{
    this.data=data.data;
    this.length=data.total;
    this.pageSize=data.per_page;
  })
}


  deleteData(id:number){
    this.employeeService.deleteEmp(id).subscribe(result=>{
      if (result == "deleted sucuessfully") {
        this.search('');
      }
    },
    (error)=>{
      if (error.status == 404 && error.statusText == 'Not Found') {
        alert(error.statusText);
      }
    }
    )
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
