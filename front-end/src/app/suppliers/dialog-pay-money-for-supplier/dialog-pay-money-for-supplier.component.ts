import { Component, Inject } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { BanksService } from 'src/app/financial/services/banks.service';
import { SuppliersService } from '../services/suppliers.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-dialog-pay-money-for-supplier',
  templateUrl: './dialog-pay-money-for-supplier.component.html',
  styleUrls: ['./dialog-pay-money-for-supplier.component.css']
})
export class DialogPayMoneyForSupplierComponent {

  banksData:any[]=[]

  bank!:number;

  constructor(public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private bankService:BanksService,
    private supplierService:SuppliersService,
    private route:Router
  ) {}

  ngOnInit(){
    this.bankService.bankSelect().subscribe((result:any)=>this.banksData=result);
  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

  onBankSelect(e){
    this.bank = e.target.value;
  }

  submit(form){

    this.supplierService.supplierPay(this.data.supplier.id , {bank:this.bank,amount:form.value.amount}).subscribe((res:any)=>{
      console.log(res);

      if (res.message == 'success') {
        console.log('work');
        this.onCloseClick();
        this.data.refreshData();
      }

    })


    }
}
