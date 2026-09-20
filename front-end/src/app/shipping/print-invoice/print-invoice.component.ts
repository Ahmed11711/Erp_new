import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import { OrderInvoicePrintService } from '../services/order-invoice-print.service';

@Component({
  selector: 'app-print-invoice',
  templateUrl: './print-invoice.component.html',
  styleUrls: ['./print-invoice.component.css']
})
export class PrintInvoiceComponent implements OnChanges {
  /** Single-order print. */
  @Input() data: any = null;
  /** Batch print from orders list. */
  @Input() orders: any[] | null = null;
  @Input() size = 'A4';
  @Input() showInvoiceDate = true;
  @Input() reloadAfterPrint = false;
  /** Bumped to re-trigger print after each batch request. */
  @Input() printToken = 0;

  constructor(private orderInvoicePrint: OrderInvoicePrintService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.orders && this.orders?.length) {
      this.orderInvoicePrint.openPrintWindow(this.orders, {
        showInvoiceDate: this.showInvoiceDate,
        size: this.size,
      });
      return;
    }

    if (!changes.data && !changes.printToken && !changes.showInvoiceDate) {
      return;
    }

    if (!this.data || !Object.keys(this.data).length) {
      return;
    }

    const pageSize = this.data.size || this.size;
    this.orderInvoicePrint.openPrintWindow(
      [{ ...this.data, size: pageSize }],
      {
        showInvoiceDate: this.showInvoiceDate,
        size: pageSize,
        reloadAfterPrint: this.reloadAfterPrint,
      }
    );
  }
}
