import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators, AbstractControl } from '@angular/forms';
import { CimmitmentService } from '../services/cimmitment.service';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/env/env';
import { forkJoin } from 'rxjs';

@Component({
  selector: 'app-add-commitment',
  templateUrl: './add-commitment.component.html',
  styleUrls: ['./add-commitment.component.css']
})
export class AddCommitmentComponent implements OnInit {

  errorform = false;
  dateFrom!: string;
  errorMessage!: string;
  loading = false;

  suppliers: any[] = [];
  treeAccounts: any[] = [];
  expenseAccounts: any[] = [];
  liabilityAccounts: any[] = [];

  payeeTypes = [
    { value: 'supplier', label: 'مورد' },
    { value: 'other', label: 'جهة أخرى' }
  ];

  constructor(
    private cimmitmentService: CimmitmentService,
    private route: Router,
    private http: HttpClient
  ) {
    const today = new Date();
    const year = today.getFullYear();
    const month = (today.getMonth() + 1).toString().padStart(2, '0');
    const day = today.getDate().toString().padStart(2, '0');
    this.dateFrom = `${year}-${month}-${day}`;
  }

  ngOnInit(): void {
    this.form.patchValue({ date: this.dateFrom });
    this.loadData();
  }

  loadData(): void {
    forkJoin({
      suppliers: this.http.get<any>(`${environment.Url}/suppliers/supplier_names`),
      treeAccounts: this.http.get<any>(`${environment.Url}/tree_accounts`)
    }).subscribe({
      next: (res) => {
        this.suppliers = res.suppliers || [];
        const accounts = res.treeAccounts?.data || res.treeAccounts || [];
        this.treeAccounts = this.flattenAccounts(Array.isArray(accounts) ? accounts : []);
        this.expenseAccounts = this.treeAccounts.filter((a: any) => a.type === 'expense');
        this.liabilityAccounts = this.treeAccounts.filter((a: any) => a.type === 'liability');
      },
      error: () => {
        this.treeAccounts = [];
        this.expenseAccounts = [];
        this.liabilityAccounts = [];
      }
    });
  }

  flattenAccounts(accounts: any[], result: any[] = []): any[] {
    accounts.forEach(acc => {
      result.push(acc);
      if (acc.children && acc.children.length) {
        this.flattenAccounts(acc.children, result);
      }
    });
    return result;
  }

  form: FormGroup = new FormGroup({
    name: new FormControl(null, [Validators.required]),
    date: new FormControl(null, [Validators.required]),
    deserved_amount: new FormControl(null, [Validators.required, Validators.min(0)]),
    payee_type: new FormControl('other', [Validators.required]),
    supplier_id: new FormControl(null),
    payee_name: new FormControl(null),
    expense_account_id: new FormControl(null, [Validators.required]),
    liability_account_id: new FormControl(null),
    notes: new FormControl(null)
  }, { validators: this.payeeValidator });

  payeeValidator(control: AbstractControl): { [key: string]: boolean } | null {
    const payeeType = control.get('payee_type')?.value;
    const supplierId = control.get('supplier_id')?.value;
    const payeeName = control.get('payee_name')?.value;
    if (payeeType === 'supplier' && !supplierId) {
      return { payeeRequired: true };
    }
    if (payeeType === 'other' && !payeeName?.trim()) {
      return { payeeRequired: true };
    }
    return null;
  }

  onPayeeTypeChange(): void {
    this.form.get('supplier_id')?.setValue(null);
    this.form.get('payee_name')?.setValue(null);
    this.form.get('supplier_id')?.updateValueAndValidity();
    this.form.get('payee_name')?.updateValueAndValidity();
  }

  submitform(): void {
    if (this.form.invalid) {
      this.errorform = true;
      this.errorMessage = 'من فضلك تأكد من صحة جميع المدخلات';
      return;
    }

    this.loading = true;
    this.errorform = false;

    const value = this.form.value;
    const payload: any = {
      name: value.name,
      date: value.date,
      deserved_amount: value.deserved_amount,
      payee_type: value.payee_type,
      expense_account_id: value.expense_account_id,
      notes: value.notes || null
    };

    if (value.payee_type === 'supplier') {
      payload.supplier_id = value.supplier_id;
    } else {
      payload.payee_name = value.payee_name;
    }

    if (value.liability_account_id) {
      payload.liability_account_id = value.liability_account_id;
    }

    this.cimmitmentService.add(payload).subscribe({
      next: () => {
        this.loading = false;
        this.route.navigate(['/dashboard/financial/discounts']);
      },
      error: (err) => {
        this.loading = false;
        this.errorform = true;
        this.errorMessage = err.error?.message || 'من فضلك تأكد من كل المدخلات';
      }
    });
  }
}
