import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { OrderService } from '../services/order.service';
import { PaymentSourcesService, PaymentSourceItem } from 'src/app/accounting/services/payment-sources.service';

@Component({
  selector: 'app-part-collect',
  templateUrl: './part-collect.component.html',
  styleUrls: ['./part-collect.component.css']
})
export class PartCollectComponent {
  line!: string;
  company!: string;
  paymentType: 'safe' | 'bank' | 'service_account' = 'bank';
  safes: PaymentSourceItem[] = [];
  banks: PaymentSourceItem[] = [];
  serviceAccounts: PaymentSourceItem[] = [];
  sourceId: number | null = null;
  total_balance !: number;
  order_type !: string;

  constructor(
    private order: OrderService,
    private route: ActivatedRoute,
    private router: Router,
    private paymentSources: PaymentSourcesService
  ) {}

  ngOnInit(): void {
    const id = this.route.snapshot.params['id'];
    this.order.getOrderById(id).subscribe((res: any) => {
      this.total_balance = res.net_total;
      this.order_type = res.order_type;
    });

    this.paymentSources.getPaymentSources().subscribe((res: any) => {
      this.safes = res.safes || [];
      this.banks = res.banks || [];
      this.serviceAccounts = res.service_accounts || [];
    });
  }

  get sourceList(): PaymentSourceItem[] {
    if (this.paymentType === 'safe') return this.safes;
    if (this.paymentType === 'service_account') return this.serviceAccounts;
    return this.banks;
  }

  amount!: number;
  collectOrder(form: any) {
    const id = this.route.snapshot.params['id'];
    const body: any = { amount: this.amount, note: form.value.note, payment_type: this.paymentType };
    if (this.paymentType === 'safe') body.safe_id = this.sourceId;
    else if (this.paymentType === 'service_account') body.service_account_id = this.sourceId;
    else body.bank_id = this.sourceId;

    this.order.partcollectOrder(id, body).subscribe((res: any) => {
      if (res.message === 'success') {
        this.router.navigate(['/dashboard/shipping/listorders']);
      }
    });
  }
}
