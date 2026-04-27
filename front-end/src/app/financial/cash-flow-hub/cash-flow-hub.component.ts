import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';

export type CashFlowHubMode = 'in' | 'out';

@Component({
  selector: 'app-cash-flow-hub',
  templateUrl: './cash-flow-hub.component.html',
  styleUrls: ['./cash-flow-hub.component.css']
})
export class CashFlowHubComponent implements OnInit {
  mode: CashFlowHubMode = 'in';
  title = '';
  subtitle = '';
  enLabel = '';

  constructor(private route: ActivatedRoute) {}

  ngOnInit(): void {
    const hub = this.route.snapshot.data['hub'];
    this.mode = hub === 'out' ? 'out' : 'in';
    if (this.mode === 'in') {
      this.title = 'نقد وارد';
      this.subtitle = 'اختر نوع العملية لاستلام أموال إلى الخزينة أو البنك';
      this.enLabel = 'Cash In';
    } else {
      this.title = 'نقد صادر';
      this.subtitle = 'اختر نوع العملية لصرف أموال من الخزينة أو البنك';
      this.enLabel = 'Cash Out';
    }
  }
}
