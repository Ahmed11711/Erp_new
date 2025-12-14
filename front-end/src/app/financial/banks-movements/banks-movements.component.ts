import { Component, OnInit } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { AuthService } from 'src/app/auth/auth.service';
import Swal from 'sweetalert2';
import { DialogComponent } from '../dialog/dialog.component';
import { FactoryBankMovementsService } from '../services/factory-bank-movements.service';
import { ExcelService } from 'src/app/excel.service';
import { PdfService } from 'src/app/pdf.service';
import { BankMovementDetailsDialogComponent } from '../bank-movement-details-dialog/bank-movement-details-dialog.component';
import { BankMovementCustodyDialogComponent } from '../bank-movement-custody-dialog/bank-movement-custody-dialog.component';

@Component({
  selector: 'app-bancks-movements',
  templateUrl: './banks-movements.component.html',
  styleUrls: ['./banks-movements.component.css']
})
export class BanksMovementsComponent implements OnInit{
  month;
  lastBalance;
  totalCustody;
  lastRow;
  data:any[]=[];
  FactoryBankMovementsDetails:any[]=[];
  FactoryBankMovementsCustody:any[]=[];
  user!:string;
  constructor( private FactoryBankMovementsService:FactoryBankMovementsService , private authService:AuthService,
    private pdfService:PdfService, private excelService:ExcelService, private dialog:MatDialog
  ){}

  ngOnInit(): void {
    this.month = new Date().toISOString().slice(0, 7);
    this.filter['month'] = this.month
    this.getData();
    this.user = this.authService.getUser();
  }
  filter = {}
  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.filter['month'] = target.value;
    this.getData();
  }

  getData(){
    this.FactoryBankMovementsService.get(this.filter).subscribe((result:any)=>{
      this.data = result.data;
      this.FactoryBankMovementsDetails = result.FactoryBankMovementsDetails;
      this.FactoryBankMovementsCustody = result.FactoryBankMovementsCustody;
      this.lastBalance = result.lastRow.balance;
      this.lastRow = result.lastRow;
      this.totalCustody = this.FactoryBankMovementsCustody.reduce((acc, elm) => acc + elm.amount, 0);
    });
  }

  openBalanceDetails(data, lastRow): void {
    if (lastRow) {
      data.FactoryBankMovementsDetails = this.FactoryBankMovementsDetails;
      const dialogRef = this.dialog.open(BankMovementDetailsDialogComponent, {
        width: '40%',data: {data,refreshData: ()=>this.getData()},
      });

      dialogRef.afterClosed().subscribe(result => {
        this.getData();
      });
    }
  }

  openCustodyDetails(): void {
    let data = {};
    data['FactoryBankMovementsCustody'] = this.FactoryBankMovementsCustody;

    const dialogRef = this.dialog.open(BankMovementCustodyDialogComponent, {
      width: '40%',data: {data,refreshData: ()=>this.getData()},
    });

    dialogRef.afterClosed().subscribe(result => {
      this.getData();
    });
  }

  editMove(id, elm){
    let amount = elm.amount_in ?? elm.amount_out;
    Swal.fire({
      title: 'تعديل المبلغ ؟',
      html: `
        <input type="number" id="amountInput" value="${amount}" class="swal2-input" placeholder="المبلغ">
      `,
      showCancelButton: true,
      preConfirm: () => {
        const amountValue = (<HTMLInputElement>document.getElementById('amountInput')).value;
        if (!amountValue) {
          return Swal.showValidationMessage('يجب إدخال قيمة المبلغ');
        }
        amount = amountValue;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        let data = {
          id,
          amount,
        };
        this.FactoryBankMovementsService.add(data).subscribe(res => {
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
      return undefined;
    });
  }

  deleteMove(id){
    Swal.fire({
      title: 'تأكيد الحذف ؟',
      showCancelButton: true,
      confirmButtonText: 'حذف',
      cancelButtonText: 'إلغاء',
      }).then((result) => {
        if (result.isConfirmed) {
          this.FactoryBankMovementsService.delete(id).subscribe(res => {
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
  }

  depositBank() {
    let amount_in;
    let description = '';
    let date = '';

    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];

    const twoDaysBefore = new Date();
    twoDaysBefore.setDate(today.getDate() - 2);
    const twoDaysBeforeStr = twoDaysBefore.toISOString().split('T')[0];

    Swal.fire({
      title: 'مبلغ الايداع',
      html: `
        <input type="date" id="dateInput" class="swal2-input" value="${todayStr}" min="${twoDaysBeforeStr}" max="${todayStr}">
        <input type="number" id="amountInput" class="swal2-input" placeholder="المبلغ">
      `,
      showCancelButton: true,
      preConfirm: () => {
        const amountValue = (<HTMLInputElement>document.getElementById('amountInput')).value;
        const dateValue = (<HTMLInputElement>document.getElementById('dateInput')).value;

        if (!amountValue) {
          return Swal.showValidationMessage('يجب إدخال قيمة المبلغ');
        }
        if (!dateValue) {
          return Swal.showValidationMessage('يجب إدخال التاريخ');
        }

        amount_in = amountValue;
        date = dateValue;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({
          title: 'البيان؟',
          input: 'text',
          showCancelButton: true,
          inputValidator: (value) => {
            if (!value) {
              return 'يجب إدخال البيان';
            }
            if (value !== '') {
              description = value;
              let data = {
                date,
                amount_in,
                description
              };
              this.FactoryBankMovementsService.add(data).subscribe(res => {
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
            return undefined;
          }
        });
      }
    });
  }


  withDrawBank() {
    let amount_out;
    let description = '';
    let date = '';

    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];

    const twoDaysBefore = new Date();
    twoDaysBefore.setDate(today.getDate() - 2);
    const twoDaysBeforeStr = twoDaysBefore.toISOString().split('T')[0];

    Swal.fire({
      title: 'مبلغ الصرف',
      html: `
      <input type="date" id="dateInput" class="swal2-input" value="${todayStr}" min="${twoDaysBeforeStr}" max="${todayStr}">
        <input type="number" id="amountInput" class="swal2-input" placeholder="المبلغ">
      `,
      showCancelButton: true,
      preConfirm: () => {
        const amountValue = (<HTMLInputElement>document.getElementById('amountInput')).value;
        const dateValue = (<HTMLInputElement>document.getElementById('dateInput')).value;

        if (!amountValue) {
          return Swal.showValidationMessage('يجب إدخال قيمة المبلغ');
        }
        if (!dateValue) {
          return Swal.showValidationMessage('يجب إدخال التاريخ');
        }

        amount_out = amountValue;
        date = dateValue;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({
          title: 'البيان؟',
          input: 'text',
          showCancelButton: true,
          inputValidator: (value) => {
            if (!value) {
              return 'يجب إدخال البيان';
            }
            if (value !== '') {
              description = value;
              let data = {
                date,
                amount_out,
                description
              };
              this.FactoryBankMovementsService.add(data).subscribe(res => {
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
            return undefined;
          }
        });
      }
    });
  }

  export(status) {
    let fileName = 'movement';
    var element = document.getElementById('capture');
    this.pdfService.generatePdf(element, status, fileName)
  }

  exportTableToExcel() {
    let fileName = 'movement';
    const tableElement:any = document.getElementById('capture');
    this.excelService.generateExcel(fileName, tableElement, 1);
  }



}
