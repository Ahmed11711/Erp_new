import { Component, OnInit } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { AuthService } from 'src/app/auth/auth.service';
import { ExcelService } from 'src/app/excel.service';
import { PdfService } from 'src/app/pdf.service';
import Swal from 'sweetalert2';
import { BankMovementCustodyDialogComponent } from '../bank-movement-custody-dialog/bank-movement-custody-dialog.component';
import { BankMovementDetailsDialogComponent } from '../bank-movement-details-dialog/bank-movement-details-dialog.component';
import { FactoryBankMovementsService } from '../services/factory-bank-movements.service';
import { IncomeListService } from '../services/income-list.service';

@Component({
  selector: 'app-income-list',
  templateUrl: './income-list.component.html',
  styleUrls: ['./income-list.component.css']
})
export class IncomeListComponent implements OnInit{
  month;

  data: any = {};
  incomeSales!:number;
  costSales!:number;
  totalWin!:number;
  totalWinBeforeVat!:number;

  user!:string;
  constructor( private IncomeListService:IncomeListService , private authService:AuthService,
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
    this.month = target.value;
    this.getData();
  }

  getData(){
    this.IncomeListService.get(this.filter).subscribe((data:any)=>{
      this.data = data;
      this.incomeSales = 0;
      this.costSales = 0;
      this.totalWin = 0;
      this.totalWinBeforeVat = 0;
      if (data.sales) {
        this.incomeSales = data?.sales   - data?.sales_returns  ;

        this.costSales = (
            data?.opening_raw_materials   +
            data?.opening_under_processing   +
            data?.opening_finished_goods   +
            data?.purchases   +
            data?.purchase_expenses   +
            data?.operating_expenses   +
            data?.sales_expenses
          ) -
          (
            data?.closing_raw_materials   +
            data?.closing_under_processing   +
            data?.closing_finished_goods   +
            data?.last_storage
          )

        this.totalWin = this.incomeSales - this.costSales;


        this.totalWinBeforeVat = this.totalWin + data?.other_revenues   - (data?.setup_expenses + data?.depreciation + data?.admin_expenses + data?.depreciation_reserves) ;
      }


    });
  }


  editAmount(key, elm){
    let amount = elm;
    Swal.fire({
      title: 'ادخل المبلغ ؟',
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
          month:this.month,
          [key]:amount
        };
        this.IncomeListService.add(data).subscribe(res => {
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


}
