import { Component, OnInit } from '@angular/core';
import { VoucherService } from '../../accounting/services/voucher.service';

@Component({
   selector: 'app-client-cash-payments',
   template: `
    <div class="container-fluid">
       <div class="d-flex justify-content-between align-items-center mb-3">
          <h2>دفعات نقدية من العملاء</h2>
       </div>
       <div class="card p-3">
          <table class="table table-bordered table-striped">
             <thead>
                <tr>
                   <th>#</th>
                   <th>التاريخ</th>
                   <th>العميل</th>
                   <th>المبلغ</th>
                   <th>طريقة الدفع</th>
                   <th>الملاحظات</th>
                </tr>
             </thead>
             <tbody>
                <tr *ngFor="let v of vouchers">
                   <td>{{v.id}}</td>
                   <td>{{v.date}}</td>
                   <td>{{v.client?.name}}</td>
                   <td>{{v.amount}}</td>
                   <td>{{v.account?.name}}</td>
                   <td>{{v.notes}}</td>
                </tr>
                <tr *ngIf="vouchers.length === 0">
                   <td colspan="6" class="text-center">لا توجد بيانات</td>
                </tr>
             </tbody>
          </table>
       </div>
    </div>
  `,
   styles: []
})
export class ClientCashPaymentsComponent implements OnInit {
   vouchers: any[] = [];
   loading = false;

   constructor(private voucherService: VoucherService) { }

   ngOnInit() {
      this.getData();
   }

   getData() {
      this.loading = true;
      // Fetch Receipts (type=receipt) for Clients (voucher_type=client)
      this.voucherService.getVouchers({ type: 'receipt', voucher_type: 'client' }).subscribe({
         next: (res) => {
            this.vouchers = res.data || (Array.isArray(res) ? res : []);
            this.loading = false;
         },
         error: (err) => {
            console.error(err);
            this.loading = false;
         }
      });
   }
}
