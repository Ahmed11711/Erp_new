import { Component, Input, SimpleChanges } from '@angular/core';
import * as html2pdf from 'html2pdf.js';
import { saveAs } from 'file-saver';

@Component({
  selector: 'app-print-invoice',
  templateUrl: './print-invoice.component.html',
  styleUrls: ['./print-invoice.component.css']
})
export class PrintInvoiceComponent {
  @Input() data: any = {};

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.data && this.data) {
      if (Object.keys(this.data).length === 0 && this.data.constructor === Object) {

      } else {
        this.downloadPDF();
      }
    }
  }

  downloadPDF() {
    const element = document.getElementById('capture');
    if (element) {
      const options = {
        filename: this.data?.id +'-'+ this.data.size +'.pdf',
        image: { type: 'png' },
        html2canvas: { scale:4 },
        jsPDF: { unit: 'mm', format: this.data.size, orientation: 'portrait', autoPrint: { variant: 'non-conform' } }
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
            window.location.reload();
          } else {
            console.error('Error opening print window.');
          }
        })
        .catch((error: any) => {
          console.error('Error generating PDF:', error);
        });
    }
  }

}
