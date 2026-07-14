import { Component, Input, OnChanges, SimpleChanges } from '@angular/core';
import * as html2pdf from 'html2pdf.js';
import { formatDdMmYyyy, isoStringToDate } from 'src/app/shared/date/date-utils';
import { OrderInvoicePrintService } from '../services/order-invoice-print.service';

@Component({
  selector: 'app-print-invoice',
  templateUrl: './print-invoice.component.html',
  styleUrls: ['./print-invoice.component.css']
})
export class PrintInvoiceComponent implements OnChanges {
  /** Single-order print (legacy). */
  @Input() data: any = null;
  /** Batch print from orders list — handled via native print window (no html2pdf). */
  @Input() orders: any[] | null = null;
  @Input() size = 'A4';
  @Input() showInvoiceDate = true;
  @Input() reloadAfterPrint = false;
  /** Bumped to re-trigger print after each batch request. */
  @Input() printToken = 0;

  invoices: any[] = [];

  constructor(private orderInvoicePrint: OrderInvoicePrintService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.orders && this.orders?.length) {
      this.invoices = [];
      this.orderInvoicePrint.openPrintWindow(this.orders, {
        showInvoiceDate: this.showInvoiceDate,
        size: this.size,
      });
      return;
    }

    if (!changes.data && !changes.printToken && !changes.showInvoiceDate) {
      return;
    }

    this.invoices = this.buildInvoices();
    if (!this.invoices.length) {
      return;
    }

    setTimeout(() => this.downloadPDF(), 150);
  }

  pageHeight(invoice: any): string {
    const pageSize = invoice?.size || this.size;
    return pageSize === 'A4' ? '1000px' : '800px';
  }

  formatInvoiceDate(value: unknown): string {
    return formatDdMmYyyy(isoStringToDate(String(value ?? '')));
  }

  private buildInvoices(): any[] {
    if (this.data && Object.keys(this.data).length > 0) {
      return [{
        ...this.data,
        size: this.data.size || this.size,
      }];
    }

    return [];
  }

  private downloadPDF(): void {
    const element = document.getElementById('capture');
    if (!element) {
      return;
    }

    const first = this.invoices[0];
    const pageSize = first?.size || this.size;
    const filename = `${first?.id}-${pageSize}.pdf`;

    const options = {
      filename,
      image: { type: 'png' },
      html2canvas: { scale: 2 },
      jsPDF: { unit: 'mm', format: pageSize, orientation: 'portrait', autoPrint: { variant: 'non-conform' } },
    };

    html2pdf(element, options)
      .from(element)
      .toPdf()
      .output('blob')
      .then((pdfBlob: Blob) => {
        const url = URL.createObjectURL(pdfBlob);
        const printWindow = window.open(url, '_blank');

        if (printWindow) {
          printWindow.print();
          if (this.reloadAfterPrint) {
            window.location.reload();
          }
        } else {
          console.error('Error opening print window.');
        }
      })
      .catch((error: unknown) => {
        console.error('Error generating PDF:', error);
      });
  }
}
