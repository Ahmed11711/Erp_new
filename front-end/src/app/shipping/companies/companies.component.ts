import { Component } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { DialogAddCompanyComponent } from '../dialog-add-company/dialog-add-company.component';
import { CompaniesService } from '../services/companies.service';
import { DialogCollectFromCustomerCompanyComponent } from '../dialog-collect-from-customer-company/dialog-collect-from-customer-company.component';
import { AuthService } from 'src/app/auth/auth.service';

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

  constructor(private companyService:CompaniesService , private dialog:MatDialog, private authService:AuthService ) { }

  ngOnInit(){
    this.user = this.authService.getUser();
    this.search(arguments);
  }

  openDialog(): void {
    const dialogRef = this.dialog.open(DialogAddCompanyComponent, {
      width: '25%',data: {refreshData: ()=>this.search(arguments)},
    });

    dialogRef.afterClosed().subscribe(result => {
      console.log('The dialog was closed');
    });
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

  param = {};
  search(event:any){


    if(event?.target?.id == 'name'){
      this.param['name']=event.target.value;
    }
    if(event?.target?.id == 'phone'){
      this.param['phone']=event.target.value;
    }
    console.log(this.param);


    this.companyService.search(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      this.data = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

  collectFromCompany(company): void {
    const dialogRef = this.dialog.open(DialogCollectFromCustomerCompanyComponent, {
      width: '30%',data: {company,refreshData: ()=>this.search(arguments)},
    });

    dialogRef.afterClosed().subscribe(result => {
      console.log('The dialog was closed');
    });
  }
}
