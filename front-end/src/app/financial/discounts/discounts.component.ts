import { Component, OnInit } from '@angular/core';
import { CimmitmentService } from '../services/cimmitment.service';

@Component({
  selector: 'app-discounts',
  templateUrl: './discounts.component.html',
  styleUrls: ['./discounts.component.css']
})
export class DiscountsComponent implements OnInit {
  data: any[] = [];
  totalDeserved = 0;
  totalPaid = 0;
  totalRemaining = 0;

  constructor(private cimmitmentService: CimmitmentService) {}

  ngOnInit(): void {
    this.cimmitmentService.data().subscribe((result: any) => {
      this.data = Array.isArray(result) ? result : (result?.data || []);
      this.totalDeserved = 0;
      this.totalPaid = 0;
      this.totalRemaining = 0;
      this.data.forEach((elm: any) => {
        this.totalDeserved += parseFloat(elm.deserved_amount) || 0;
        this.totalPaid += parseFloat(elm.paid_amount) || 0;
        const remaining = elm.remaining_amount ?? (parseFloat(elm.deserved_amount) - parseFloat(elm.paid_amount || 0));
        this.totalRemaining += remaining || 0;
      });
    });
  }
}
