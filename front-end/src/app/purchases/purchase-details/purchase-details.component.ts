import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { InvoiceService } from '../service/invoice.service';

@Component({
  selector: 'app-purchase-details',
  templateUrl: './purchase-details.component.html',
  styleUrls: ['./purchase-details.component.css']
})
export class PurchaseDetailsComponent implements OnInit{

  invoice!:any;
  data:any[]=[];
  tracking:any[]=[];
  id!:any;


  constructor(private route:ActivatedRoute , private invoiceService:InvoiceService){
    // this.id = this.route.snapshot.params['id'];
    this.id = sessionStorage.getItem('invoiceId');
  }

  ngOnInit(){
    this.invoiceService.getInvoiceById(this.id).subscribe(res=>{
      this.tracking = res['tracking'];
      if (this.tracking && this.tracking.length > 1) {
        this.invoiceService.getInvoiceById(this.tracking[this.tracking.length - 1]['invoice_number']).subscribe(res=>{
          this.invoice = res['invoice'];
          this.data = res['categories'];
        });
      } else {
        this.invoice = res['invoice'];
        this.data = res['categories'];
      }
    })
  }

  getInvoice(id){
    this.invoiceService.getInvoiceById(id).subscribe(res=>{
      this.invoice = res['invoice'];
      this.data = res['categories'];
    });
  }

  ngOnDestroy(): void {
    sessionStorage.removeItem('invoiceId');
  }
}
