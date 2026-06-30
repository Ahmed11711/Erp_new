import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { BanksService } from '../services/banks.service';
import { ExpenseKindService } from '../services/expense-kind.service';
import { ExpenseService } from '../services/expense.service';
import { environment } from 'src/env/env';

@Component({
  selector: 'app-expense-details',
  templateUrl: './expense-details.component.html',
  styleUrls: ['./expense-details.component.css']
})
export class ExpenseDetailsComponent {

  id!:any
  data!:any;
  imgUrl!: string;

  constructor(private expenseKindService:ExpenseKindService, private bankService:BanksService , private expenseService:ExpenseService ,private route:Router , private router:ActivatedRoute){
    this.id = this.router.snapshot.params['id'];
    this.imgUrl = environment.imgUrl;
    }

  ngOnInit(): void {
    this.expenseService.getByID(this.id).subscribe(res=>{
      console.log(res);

      this.data = res;
    })

  }

  resolvePaymentType(row: any): string {
    if (!row) {
      return 'bank';
    }
    if (row.payment_type) {
      return row.payment_type;
    }
    if (row.safe_id) {
      return 'safe';
    }
    if (row.service_account_id) {
      return 'service_account';
    }
    return 'bank';
  }

  paymentPlaceLabel(row: any): string {
    const pt = this.resolvePaymentType(row);
    if (pt === 'safe') {
      return 'خزينة';
    }
    if (pt === 'bank') {
      return 'بنك';
    }
    if (pt === 'service_account') {
      return 'حساب خدمي';
    }
    return '—';
  }

  paymentSourceName(row: any): string {
    const pt = this.resolvePaymentType(row);
    if (pt === 'safe') {
      return row?.safe?.name ?? '—';
    }
    if (pt === 'bank') {
      return row?.bank?.name ?? '—';
    }
    return row?.service_account?.name ?? '—';
  }

  treeAccountLine(acc: { code?: string; name?: string } | null | undefined): string {
    if (!acc) {
      return '—';
    }
    const code = acc.code != null && String(acc.code).trim() !== '' ? String(acc.code) : '';
    const name = acc.name != null && String(acc.name).trim() !== '' ? String(acc.name) : '';
    if (code && name) {
      return `${code} · ${name}`;
    }
    return name || code || '—';
  }

  get isSplit(): boolean {
    return Array.isArray(this.data?.lines) && this.data.lines.length > 1;
  }

  lineDebitTreeLabel(line: any): string {
    const resolved = line?.debit_tree_account ?? line?.tree_account;
    if (resolved) {
      return this.treeAccountLine(resolved);
    }
    const kind = line?.kind;
    const acc = kind?.tree_account ?? kind?.treeAccount;
    return this.treeAccountLine(acc);
  }

  creditTreeFromRow(row: any): { code?: string; name?: string } | null {
    if (!row) {
      return null;
    }
    const pt = this.resolvePaymentType(row);
    if (pt === 'safe') {
      return row.safe?.account ?? null;
    }
    if (pt === 'bank') {
      return row.bank?.asset ?? null;
    }
    return row.service_account?.account ?? null;
  }

}
