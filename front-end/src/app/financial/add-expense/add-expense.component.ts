import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators, FormArray } from '@angular/forms';
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
  allExpenseKinds: any[] = [];
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
      created_at: this.dateFrom,
    });
    this.paymentType = 'safe';
    this.addSplitLine();

    this.expenseKindService.data().subscribe((result) => {
      this.allExpenseKinds = Array.isArray(result) ? result : [];
    });
    this.paymentSourcesService.getPaymentSources().subscribe((res: any) => {
      this.safesData = res.safes || [];
      this.banksData = res.banks || [];
      this.serviceAccountsData = res.service_accounts || [];
    });
  }

  get splitLines(): FormArray {
    return this.form.get('lines') as FormArray;
  }

  createSplitLineGroup(): FormGroup {
    return new FormGroup({
      expense_type: new FormControl(null, Validators.required),
      kind_id: new FormControl(null, Validators.required),
      amount: new FormControl(null, [Validators.required, Validators.min(0.01)]),
    });
  }

  addSplitLine(): void {
    this.splitLines.push(this.createSplitLineGroup());
  }

  removeSplitLine(index: number): void {
    if (this.splitLines.length <= 1) {
      return;
    }
    this.splitLines.removeAt(index);
  }

  onPaymentTypeChange(): void {
    this.paymentType = this.form.get('payment_type')?.value || 'safe';
    this.form.patchValue({
      safe_id: null,
      bank_id: null,
      service_account_id: null,
    });
  }

  kindsForType(expenseType: string | null): any[] {
    if (!expenseType) {
      return [];
    }
    return this.allExpenseKinds.filter((k) => k.expense_type === expenseType);
  }

  onLineExpenseTypeChange(index: number): void {
    const row = this.splitLines.at(index) as FormGroup;
    row.patchValue({ kind_id: null });
  }

  get linesTotal(): number {
    return this.splitLines.controls.reduce((sum, ctrl) => {
      const v = parseFloat((ctrl as FormGroup).get('amount')?.value);
      return sum + (isNaN(v) ? 0 : v);
    }, 0);
  }

  get amountMismatch(): boolean {
    const total = parseFloat(this.form.get('amount')?.value);
    if (isNaN(total) || total <= 0) {
      return false;
    }
    return Math.abs(this.linesTotal - total) > 0.009;
  }

  form:FormGroup = new FormGroup({
    'payment_type' : new FormControl('safe'),
    'safe_id' : new FormControl(null),
    'bank_id' : new FormControl(null),
    'service_account_id' : new FormControl(null),
    'expens_statement' : new FormControl(null, [Validators.required]),
    'amount' : new FormControl(null, [Validators.required, Validators.min(0.01)]),
    'note' : new FormControl(null, [Validators.required]),
    'created_at' : new FormControl(null, [Validators.required]),
    'expense_image' : new FormControl(null),
    'lines': new FormArray([]),
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

  get splitLinesValid(): boolean {
    return this.splitLines.length > 0 && this.splitLines.valid;
  }

  get canSubmit(): boolean {
    return this.isSourceSelected
      && this.form.valid
      && this.splitLinesValid
      && !this.amountMismatch
      && this.linesTotal > 0;
  }

  submitform(){
    if (!this.canSubmit) {
      this.errormessage = true;
      this.form.markAllAsTouched();
      this.splitLines.markAllAsTouched();
      return;
    }

    const data = this.form.value;
    const lines = (data.lines || []).map((row: any) => ({
      expense_type: row.expense_type,
      kind_id: Number(row.kind_id),
      amount: Number(row.amount),
    }));

    const formData = new FormData();
    formData.append('payment_type', data.payment_type || 'safe');
    formData.append('expens_statement', data.expens_statement);
    formData.append('amount', String(data.amount));
    formData.append('note', data.note);
    formData.append('address', data.expens_statement || '');
    formData.append('created_at', `${data.created_at} ${this.time}`);
    formData.append('lines', JSON.stringify(lines));

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
    });
  }
}
