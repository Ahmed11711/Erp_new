import { Component, Inject } from '@angular/core';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { FactoryBankMovementsService } from '../services/factory-bank-movements.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-bank-movement-details-dialog',
  templateUrl: './bank-movement-details-dialog.component.html',
  styleUrls: ['./bank-movement-details-dialog.component.css']
})
export class BankMovementDetailsDialogComponent {

  totalAmount;

  rows: { description: string; amount: number; id?:number; user?:any;}[] = [
    { description: 'البيان', amount: 0 },
  ];


  addRow() {
    this.rows.push({ description: 'البيان', amount: 0});
  }

  constructor(public dialogRef: MatDialogRef<BankMovementDetailsDialogComponent>, private FactoryBankMovementsService:FactoryBankMovementsService,
    @Inject(MAT_DIALOG_DATA) public data: any
  ) {}

  ngOnInit(){
    this.rows = this.data.data.FactoryBankMovementsDetails;
    this.totalAmount = this.rows.reduce((acc, elm) => acc + elm.amount, 0);
  }

  getData(){
    this.FactoryBankMovementsService.getFactoryBankMovementsDetails().subscribe({
      next: (res) => {
        this.rows = res;
        this.totalAmount = this.rows.reduce((acc, elm) => acc + elm.amount, 0);
      }
    })
  }

  save(){
    let data = {data:this.rows, factoryBankId:this.data.data.id}

    this.FactoryBankMovementsService.addFactoryBankMovementsDetails(data).subscribe({
      next: () => {
        this.getData();
        Swal.fire({
          icon: 'success',
          showConfirmButton: false,
          timer: 1500,
        })
      }
    })

  }

  checkUpdate(): boolean {
    let fixedData = this.data.data.factory_bank_details;
    let data = this.rows;

    if (fixedData?.length !== data.length) {
      return false;
    }

    for (let i = 0; i < fixedData.length; i++) {
      if (
        fixedData[i].amount !== data[i].amount ||
        fixedData[i].description !== data[i].description
      ) {
        return false;
      }
    }

    return true;
  }


  deleteRow(id, i){
    if (id) {
      Swal.fire({
        title: 'تأكيد الحذف ؟',
        showCancelButton: true,
        confirmButtonText: 'حذف',
        cancelButtonText: 'إلغاء',
        }).then((result) => {
          if (result.isConfirmed) {
            this.FactoryBankMovementsService.deleteFactoryBankMovementsDetails(id).subscribe(res => {
              if (res) {
                Swal.fire({
                  icon: 'success',
                  showConfirmButton:false,
                  timer: 1500
                  });
                this.getData();
              }
            });
          }
      });
    } else {
      this.rows.splice(i,1);
    }
  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

}
