import { Component, Inject } from '@angular/core';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { Router } from '@angular/router';
import { BanksService } from 'src/app/financial/services/banks.service';
import { SuppliersService } from 'src/app/suppliers/services/suppliers.service';
import { CompaniesService } from '../services/companies.service';

@Component({
  selector: 'app-dialog-collect-from-customer-company',
  templateUrl: './dialog-collect-from-customer-company.component.html',
  styleUrls: ['./dialog-collect-from-customer-company.component.css']
})
export class DialogCollectFromCustomerCompanyComponent {

  banksData:any[]=[]

  bank!:number;

  constructor(public dialogRef: MatDialogRef<DialogCollectFromCustomerCompanyComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private bankService:BanksService,
    private companyService:CompaniesService,
    private route:Router
  ) {}

  ngOnInit(){
    console.log(this.data);

    this.bankService.bankSelect().subscribe((result:any)=>this.banksData=result);
  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

  onBankSelect(e){
    this.bank = e.target.value;
  }

  submit(form){

    this.companyService.companyCollect(this.data.company.id , {bank:this.bank,amount:form.value.amount}).subscribe((res:any)=>{
      if (res.message == 'success') {
        this.onCloseClick();
        this.data.refreshData();
      }
    })
  }
}
