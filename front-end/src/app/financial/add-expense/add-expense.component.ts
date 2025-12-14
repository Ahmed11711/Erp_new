import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ExpenseKindService } from '../services/expense-kind.service';
import { BanksService } from '../services/banks.service';
import { ExpenseService } from '../services/expense.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-add-expense',
  templateUrl: './add-expense.component.html',
  styleUrls: ['./add-expense.component.css']
})
export class AddExpenseComponent implements OnInit{

  shippingWays:any[]=[];
  orderSources:any[]=[];
  errormessage:boolean=false;
  products:any[]=[];
  banksData:any[]=[];
  expenseKind:any[]=[];
  dateFrom: string = new Date().toISOString().slice(0, 10);
  minDate!: string;
  maxDate!: string;
  time!:string;

  constructor(private expenseKindService:ExpenseKindService,
    private bankService:BanksService ,
    private expenseService:ExpenseService ,
    private route:Router
    ){
      const today = new Date();
      const threeDaysBefore = new Date(today);
      threeDaysBefore.setDate(today.getDate() - 3);

      this.maxDate = today.toISOString().split('T')[0];
      this.minDate = threeDaysBefore.toISOString().split('T')[0];
      this.time = this.getCurrentTime();
    }

    getCurrentTime(): string {
      const now = new Date();
      const hours = now.getHours().toString().padStart(2, '0');
      const minutes = now.getMinutes().toString().padStart(2, '0');
      const seconds = now.getSeconds().toString().padStart(2, '0');
      return `${hours}:${minutes}:${seconds}`;
    }

  ngOnInit(): void {
    this.form.patchValue({
      bank_id:"الخزينة",
      expense_type:"نوع المصروف",
      kind_id:"فئة المصروف",
      created_at:this.dateFrom,
    });

    this.expenseKindService.data().subscribe(result=>this.expenseKind=result);
    this.bankService.bankSelect().subscribe((result:any)=>this.banksData=result);
  }

  form:FormGroup = new FormGroup({
    'bank_id' :new FormControl(null),
    'expense_type' :new FormControl(null, [Validators.required ]),
    'kind_id' :new FormControl(null, [Validators.required ]),
    'expens_statement' :new FormControl(null, [Validators.required ] ),
    'amount' :new FormControl(null, [Validators.required ] ),
    'note' :new FormControl(null, [Validators.required ] ),
    'address' :new FormControl(null, [Validators.required ] ),
    'created_at' :new FormControl(null, [Validators.required ] ),
    'expense_image' :new FormControl(null),
  })

  imgtext:string="صورة "
  fileopend:boolean=false;

  openFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
      this.fileopend=true;
    }
  }

  selectedFile: any;
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.imgtext = this.selectedFile?.name || 'No image selected';
  }


  submitform(){
    if(this.form.valid){
      let data = this.form.value
      const formData = new FormData();
      formData.append('bank_id', data.bank_id);
      formData.append('expense_type', data.expense_type);
      formData.append('kind_id', data.kind_id);
      formData.append('expens_statement', data.expens_statement);
      formData.append('amount', data.amount);
      formData.append('note', data.note);
      formData.append('address', data.address);
      formData.append('created_at', `${data.created_at} ${this.time}`);

      if (this.selectedFile) {
        formData.append('expense_image', this.selectedFile, this.selectedFile.name);
      }

      this.expenseService.add(formData).subscribe(result=>{
        if (result) {
          this.route.navigate(['/dashboard/financial/expenses']);
        }
      })
    }
  }
}
