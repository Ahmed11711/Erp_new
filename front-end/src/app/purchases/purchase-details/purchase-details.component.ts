import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { purchaseStatusBadgeClass } from 'src/app/shared/utils/purchase-invoice-status.util';
import { resolvePurchaseShippingRep } from 'src/app/shared/utils/purchase-shipping-rep.util';
import { InvoiceService } from '../service/invoice.service';

@Component({
  selector: 'app-purchase-details',
  templateUrl: './purchase-details.component.html',
  styleUrls: ['./purchase-details.component.css', '../purchase-ui.shared.css']
})
export class PurchaseDetailsComponent implements OnInit{

  invoice!:any;
  data:any[]=[];
  tracking:any[]=[];
  id!:any;
  /** رابط طباعة موقّع من الخادم */
  printUrl: string | null = null;

  statusBadgeClass = purchaseStatusBadgeClass;
  shippingRepName = resolvePurchaseShippingRep;


  constructor(private route:ActivatedRoute , private invoiceService:InvoiceService){
    this.id =
      this.route.snapshot.paramMap.get('id') ||
      this.route.snapshot.queryParamMap.get('invoiceId');
  }

  ngOnInit(): void {
    this.invoiceService.getInvoiceById(this.id).subscribe((res) => {
      this.invoice = res['invoice'];
      this.data = res['categories'] ?? [];
      this.tracking = res['tracking'] ?? [];
      this.printUrl = res['print_url'] ?? null;
    });
  }

  getInvoice(id){
    this.invoiceService.getInvoiceById(id).subscribe(res=>{
      this.invoice = res['invoice'];
      this.data = res['categories'];
      this.printUrl = res['print_url'] ?? null;
    });
  }

  openPrint(): void {
    if (!this.printUrl) {
      return;
    }
    window.open(this.printUrl, '_blank', 'noopener,noreferrer');
  }

}
