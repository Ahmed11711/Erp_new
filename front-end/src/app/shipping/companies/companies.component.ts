import { Component } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { MatSnackBar } from '@angular/material/snack-bar';
import { DialogAddCompanyComponent } from '../dialog-add-company/dialog-add-company.component';
import { CompaniesService } from '../services/companies.service';
import { DialogCollectFromCustomerCompanyComponent } from '../dialog-collect-from-customer-company/dialog-collect-from-customer-company.component';
import { AuthService } from 'src/app/auth/auth.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';

@Component({
  selector: 'app-companies',
  templateUrl: './companies.component.html',
  styleUrls: ['./companies.component.css']
})
export class CompaniesComponent {

  data:any[]=[];

  recieveDate!:string
  status!:string

  user!:string;

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  unlinkedCount = 0;
  showUnlinkedOnly = false;
  linkingAll = false;
  linkingId: number | null = null;

  constructor(
    private companyService:CompaniesService,
    private dialog:MatDialog,
    private authService:AuthService,
    public rbac: RbacService,
    private snackBar: MatSnackBar,
  ) { }

  canManageCompanies(): boolean {
    return this.rbac.canAny(['customer_companies.manage', 'system.rbac']);
  }

  canViewStatement(): boolean {
    return this.rbac.canAny(['customer_companies.statement', 'system.rbac']);
  }

  canCollect(): boolean {
    return this.rbac.canAny(['customer_companies.collect', 'system.rbac']);
  }

  ngOnInit(){
    this.user = this.authService.getUser();
    this.loadUnlinkedSummary();
    this.search(arguments);
  }

  loadUnlinkedSummary(){
    if (!this.canManageCompanies()) {
      return;
    }
    this.companyService.unlinkedSummary().subscribe((res:any)=>{
      this.unlinkedCount = res?.unlinked_count ?? 0;
    });
  }

  openDialog(): void {
    const dialogRef = this.dialog.open(DialogAddCompanyComponent, {
      width: '25%',data: {refreshData: ()=>this.refreshList()},
    });

    dialogRef.afterClosed().subscribe(result => {
      console.log('The dialog was closed');
    });
  }

  openEditDialog(company: any): void {
    const dialogRef = this.dialog.open(DialogAddCompanyComponent, {
      width: '25%',
      data: { company, refreshData: () => this.refreshList() },
    });

    dialogRef.afterClosed().subscribe(() => {});
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
  }


  onrecieveDateChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.recieveDate = target.value;
    this.search(event);

  }

  resetInp(){
    if ('supplier_id' in this.param) {
      delete this.param.supplier_id;
    }
    this.search(arguments);
  }

  param: Record<string, string> = {};

  toggleUnlinkedOnly(){
    this.showUnlinkedOnly = !this.showUnlinkedOnly;
    this.page = 0;
    this.search(arguments);
  }

  refreshList(){
    this.loadUnlinkedSummary();
    this.search(arguments);
  }

  search(event:any){


    if(event?.target?.id == 'name'){
      this.param['name']=event.target.value;
    }
    if(event?.target?.id == 'phone'){
      this.param['phone']=event.target.value;
    }
    if (this.showUnlinkedOnly) {
      this.param['unlinked_only'] = '1';
    } else {
      delete this.param['unlinked_only'];
    }
    console.log(this.param);


    this.companyService.search(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      this.data = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

  isLinked(item: any): boolean {
    return !!(item?.tree_account_id || item?.tree_account?.id);
  }

  linkAllUnlinked(){
    if (this.unlinkedCount < 1) {
      return;
    }
    if (!confirm(`سيتم ربط ${this.unlinkedCount} شركة غير مربوطة بحسابات تحت «عملاء شركات» في شجرة الحسابات. المتابعة؟`)) {
      return;
    }

    this.linkingAll = true;
    this.companyService.linkUnlinked().subscribe({
      next: (res: any) => {
        this.linkingAll = false;
        this.snackBar.open(res?.message || 'تم الربط بنجاح', 'إغلاق', { duration: 5000 });
        this.refreshList();
      },
      error: (err) => {
        this.linkingAll = false;
        this.snackBar.open(err.error?.message || 'فشل ربط العملاء', 'إغلاق', { duration: 6000 });
      }
    });
  }

  linkOne(company: any){
    if (this.isLinked(company)) {
      return;
    }
    this.linkingId = company.id;
    this.companyService.linkAccount(company.id).subscribe({
      next: (res: any) => {
        this.linkingId = null;
        const code = res?.tree_account?.code ? ` (${res.tree_account.code})` : '';
        this.snackBar.open(`تم ربط ${company.name} بالحساب${code}`, 'إغلاق', { duration: 4000 });
        this.refreshList();
      },
      error: (err) => {
        this.linkingId = null;
        this.snackBar.open(err.error?.message || 'فشل ربط الشركة', 'إغلاق', { duration: 5000 });
      }
    });
  }

  collectFromCompany(company): void {
    const dialogRef = this.dialog.open(DialogCollectFromCustomerCompanyComponent, {
      width: '30%',data: {company,refreshData: ()=>this.refreshList()},
    });

    dialogRef.afterClosed().subscribe(result => {
      console.log('The dialog was closed');
    });
  }
}
