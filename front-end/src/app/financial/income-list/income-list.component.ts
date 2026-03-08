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
  dateFrom: string | null = null;
  dateTo: string | null = null;

  data: any = {};
  incomeSales: number = 0;
  costSales: number = 0;
  totalWin: number = 0;
  totalWinBeforeVat: number = 0;
  grossMarginPercent: number = 0;
  netMarginPercent: number = 0;
  otherExpensesTotal: number = 0;

  user!: string;
  constructor( private IncomeListService:IncomeListService , private authService:AuthService,
    private pdfService:PdfService, private excelService:ExcelService, private dialog:MatDialog
  ){}

  ngOnInit(): void {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    this.dateFrom = `${y}-${m}-01`;
    this.dateTo = `${y}-${m}-${d}`;
    this.filter['date_from'] = this.dateFrom;
    this.filter['date_to'] = this.dateTo;
    this.getData();
    this.user = this.authService.getUser();
  }
  filter = {}
  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateFrom = target.value;
    delete this.filter['month'];
    this.filter['date_from'] = this.dateFrom;
    if (this.dateTo) {
      this.filter['date_to'] = this.dateTo;
    }
    this.getData();
  }
  onDateToChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateTo = target.value;
    delete this.filter['month'];
    if (this.dateFrom) {
      this.filter['date_from'] = this.dateFrom;
    }
    this.filter['date_to'] = this.dateTo;
    this.getData();
  }

  getData() {
    this.IncomeListService.get(this.filter).subscribe((res: any) => {
      this.data = res || {};
      this.incomeSales = res?.net_sales ?? 0;
      this.costSales = res?.cogs ?? 0;
      this.totalWin = res?.gross_profit ?? 0;
      this.totalWinBeforeVat = res?.net_profit_before_tax ?? 0;
      this.grossMarginPercent = res?.gross_margin_percent ?? 0;
      this.netMarginPercent = res?.profit_margin_percent ?? 0;
      this.otherExpensesTotal = res?.other_expenses_total ?? 0;
    });
  }

  formatNumber(val: number | undefined | null): string {
    if (val == null || val === undefined) return '0.00';
    return Number(val).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  get isProfit(): boolean {
    const val = this.totalWinBeforeVat ?? 0;
    return val >= 0;
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
