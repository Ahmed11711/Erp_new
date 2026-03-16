import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ExpenseKindService } from '../services/expense-kind.service';
import { ExpenseService } from '../services/expense.service';
import { PaymentSourcesService } from 'src/app/accounting/services/payment-sources.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-add-expense',
  templateUrl: './add-expense.component.html',
  styleUrls: ['./add-expense.component.css']
})
export class AddExpenseComponent implements OnInit{

  errormessage:boolean=false;
  safesData:any[]=[];
  banksData:any[]=[];
  serviceAccountsData:any[]=[];
  expenseKind:any[]=[];
  dateFrom: string = new Date().toISOString().slice(0, 10);
  minDate!: string;
  maxDate!: string;
  time!:string;
  paymentType: 'safe' | 'bank' | 'service_account' = 'safe';

  constructor(
    private expenseKindService: ExpenseKindService,
    private paymentSourcesService: PaymentSourcesService,
    private expenseService: ExpenseService,
    private route: Router
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
      payment_type: 'safe',
      expense_type: 'نوع المصروف',
      kind_id: 'فئة المصروف',
      created_at: this.dateFrom,
    });
    this.paymentType = 'safe';

    this.expenseKindService.data().subscribe(result => this.expenseKind = result);
    this.paymentSourcesService.getPaymentSources().subscribe((res: any) => {
      this.safesData = res.safes || [];
      this.banksData = res.banks || [];
      this.serviceAccountsData = res.service_accounts || [];
    });
  }

  onPaymentTypeChange(): void {
    this.paymentType = this.form.get('payment_type')?.value || 'safe';
    this.form.patchValue({
      safe_id: null,
      bank_id: null,
      service_account_id: null,
    });
  }

  form:FormGroup = new FormGroup({
    'payment_type' : new FormControl('safe'),
    'safe_id' : new FormControl(null),
    'bank_id' : new FormControl(null),
    'service_account_id' : new FormControl(null),
    'expense_type' : new FormControl(null, [Validators.required]),
    'kind_id' : new FormControl(null, [Validators.required]),
    'expens_statement' : new FormControl(null, [Validators.required]),
    'amount' : new FormControl(null, [Validators.required]),
    'note' : new FormControl(null, [Validators.required]),
    'address' : new FormControl(null, [Validators.required]),
    'created_at' : new FormControl(null, [Validators.required]),
    'expense_image' : new FormControl(null),
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


  get isSourceSelected(): boolean {
    const pt = this.form.get('payment_type')?.value;
    if (pt === 'safe') return !!this.form.get('safe_id')?.value;
    if (pt === 'bank') return !!this.form.get('bank_id')?.value;
    if (pt === 'service_account') return !!this.form.get('service_account_id')?.value;
    return false;
  }

  submitform(){
    if(this.form.valid && this.isSourceSelected){
      let data = this.form.value;
      const formData = new FormData();
      formData.append('payment_type', data.payment_type || 'safe');
      formData.append('expense_type', data.expense_type);
      formData.append('kind_id', data.kind_id);
      formData.append('expens_statement', data.expens_statement);
      formData.append('amount', data.amount);
      formData.append('note', data.note);
      formData.append('address', data.address);
      formData.append('created_at', `${data.created_at} ${this.time}`);

      if (data.payment_type === 'safe' && data.safe_id) {
        formData.append('safe_id', data.safe_id);
      } else if (data.payment_type === 'bank' && data.bank_id) {
        formData.append('bank_id', data.bank_id);
      } else if (data.payment_type === 'service_account' && data.service_account_id) {
        formData.append('service_account_id', data.service_account_id);
      }

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
