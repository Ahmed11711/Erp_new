import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ExpenseService } from '../services/expense.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-expenses',
  templateUrl: './expenses.component.html',
  styleUrls: ['./expenses.component.css']
})
export class ExpensesComponent implements OnInit {

  data:any[]=[];
  tableData:any[]=[];
  dateFrom:string='';
  dateTo:string='';

    length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  constructor(
    private expenseService: ExpenseService,
    private rbac: RbacService,
  ) {
  }

  ngOnInit(){
    // this.getData();
    this.search('');
  }

  form:FormGroup = new FormGroup({
    'start' :new FormControl(null , [Validators.required ]),
    'end' :new FormControl(null , [Validators.required ]),
  })


  param = {};
  search(event:any){

    if(this.dateFrom != ''){
      this.param['date_from']=this.dateFrom;
    }

    if(this.dateTo != ''){
      this.param['date_to']=this.dateTo;
    }
    console.log(this.param);


    this.expenseService.search(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{

      this.data = res.data;
      this.data = this.data.map((elm) => {
        const dateObject = new Date(elm.created_at);
        const year = dateObject.getFullYear();
        const month = String(dateObject.getMonth() + 1).padStart(2, '0');
        const day = String(dateObject.getDate()).padStart(2, '0');

        const formattedDateTime = `${year}-${month}-${day}`;
        elm.created_at = formattedDateTime;
        return elm;
      });

      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
  }



  resetInp(){
    this.form.reset();
    this.param ={};
    this.dateFrom = '';
    this.dateTo = '';
    this.search('');
  }

  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateFrom = target.value;
    console.log(this.dateFrom);

    this.search('');

  }
  onDateToChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateTo = target.value;
    this.search('');

  }

  // filterData(e) {
  //   let filteredData = [...this.data];

  //   if (this.dateFrom && this.dateTo) {
  //     filteredData = filteredData.filter(item => item.created_at >= this.dateFrom && item.created_at <= this.dateTo);
  //   }

  //   this.tableData = filteredData;

  // }

  status(status:any){
    if (status == 1) {
      return 'bg-danger';
    }
    if (status == 0) {
      return 'bg-success';
    }
    return '';
  }

  deleteData(id:number){
    this.expenseService.deleteExpense(id).subscribe(result=>{
      console.log(result);

      if (result) {
        this.search('');
      }
    },
    (error)=>{
      console.log(error);

      if (error.status == 404 && error.statusText == 'Not Found') {
        alert(error.statusText);
      }
    }
    )
  }

  /** سجلات قديمة بلا payment_type */
  resolvePaymentType(row: any): string {
    if (row?.payment_type) {
      return row.payment_type;
    }
    if (row?.safe_id) {
      return 'safe';
    }
    if (row?.service_account_id) {
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

  creditTreeFromRow(row: any): { code?: string; name?: string } | null {
    const pt = this.resolvePaymentType(row);
    if (pt === 'safe') {
      return row?.safe?.account ?? null;
    }
    if (pt === 'bank') {
      return row?.bank?.asset ?? null;
    }
    return row?.service_account?.account ?? null;
  }

  isSplitExpense(row: any): boolean {
    return Array.isArray(row?.lines) && row.lines.length > 1;
  }

  expenseKindLabel(row: any): string {
    if (this.isSplitExpense(row)) {
      return 'تقسيم (' + row.lines.length + ' بنود)';
    }
    return row?.kind?.expense_kind ?? '—';
  }

  expenseTypeLabel(row: any): string {
    if (this.isSplitExpense(row)) {
      return 'متعدد';
    }
    return row?.expense_type ?? '—';
  }

  debitAccountLabel(row: any): string {
    if (row?.debit_tree_accounts_split || this.isSplitExpense(row)) {
      return 'عدة حسابات (مدين)';
    }
    return this.treeAccountLine(row.debit_tree_account);
  }

  truncateNote(text: string | null | undefined, maxLen = 40): string {
    if (text == null || text === '') {
      return '—';
    }
    const s = String(text).trim();
    if (s.length <= maxLen) {
      return s;
    }
    return s.slice(0, maxLen) + '…';
  }

  canPurgeAllExpenses(): boolean {
    return this.rbac.can('expenses.purge_all') || this.rbac.can('system.rbac');
  }

  purgeAllExpenses(): void {
    if (!this.canPurgeAllExpenses()) {
      return;
    }

    this.expenseService.purgePreview().subscribe({
      next: (preview) => {
        const lines = [
          `مصروفات نشطة: <strong>${preview.active_expense_count}</strong>`,
          `إجمالي السجلات: <strong>${preview.total_expense_count}</strong>`,
          `قيود محاسبية: <strong>${preview.gl_entry_count}</strong>`,
        ];
        if (preview.expense_line_count > 0) {
          lines.push(`بنود تقسيم: <strong>${preview.expense_line_count}</strong>`);
        }

        Swal.fire({
          title: 'حذف جميع المصروفات؟',
          html: `
            <p>سيتم حذف جميع المصروفات وقيودها من شجرة الحسابات، واسترداد أرصدة الخزن/البنوك/الحسابات الخدمية للمصروفات النشطة.</p>
            <ul style="text-align:right;list-style:none;padding:0">${lines.map((l) => `<li>${l}</li>`).join('')}</ul>
            <p class="text-danger"><strong>هذه العملية لا يمكن التراجع عنها.</strong></p>
          `,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'نعم، احذف الكل',
          cancelButtonText: 'إلغاء',
          confirmButtonColor: '#d33',
        }).then((result) => {
          if (!result.isConfirmed) {
            return;
          }

          this.expenseService.purgeAll().subscribe({
            next: () => {
              this.search('');
              Swal.fire({ icon: 'success', title: 'تم حذف جميع المصروفات', timer: 3000, showConfirmButton: false });
            },
            error: (err) => {
              const msg =
                err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر تنفيذ الحذف';
              Swal.fire({ icon: 'error', title: 'فشل الحذف', text: String(msg) });
            },
          });
        });
      },
      error: (err) => {
        const msg =
          err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر تحميل المعاينة';
        Swal.fire({ icon: 'error', title: 'خطأ', text: String(msg) });
      },
    });
  }

}
