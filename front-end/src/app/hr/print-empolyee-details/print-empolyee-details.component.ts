import { Component, Input, SimpleChanges } from '@angular/core';
import * as html2pdf from 'html2pdf.js';
import { parseOvertimeMeritReason } from '../utils/fingerprint-hours.utils';


@Component({
  selector: 'app-print-empolyee-details',
  templateUrl: './print-empolyee-details.component.html',
  styleUrls: ['./print-empolyee-details.component.css']
})
export class PrintEmpolyeeDetailsComponent {
  @Input() employee: any = {};
  currentDateValue!:any;

  isOvertimeMerit(item: { reason?: string | null }): boolean {
    return !!parseOvertimeMeritReason(item?.reason);
  }

  printReasonArabic(reason: string | null | undefined): string {
    if (!reason) {
      return '';
    }
    return String(reason)
      .replace(/\s*\(?\d{4}[-/]\d{2}[-/]\d{2}\)?\s*/g, ' ')
      .replace(/\s*\(?\d{2}[-/]\d{2}[-/]\d{4}\)?\s*/g, ' ')
      .trim();
  }

  printReasonDate(reason: string | null | undefined): string {
    if (!reason) {
      return '';
    }
    const iso = String(reason).match(/(\d{4}[-/]\d{2}[-/]\d{2})/);
    if (iso) {
      return iso[1];
    }
    const dmy = String(reason).match(/(\d{2}[-/]\d{2}[-/]\d{4})/);
    return dmy ? dmy[1] : '';
  }

  constructor(){
    const today = new Date();
    let year = today.getFullYear();
    let month = today.getMonth() + 1;
    const day = today.getDate();
    this.currentDateValue = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
  }

  ngOnChanges(changes: SimpleChanges): void {

    if (changes.employee && this.employee) {

      if (Object.keys(this.employee).length === 0 && this.employee.constructor === Object) {

      } else {
        this.downloadPDF();
        console.log('here');

      }
    }
  }

  downloadPDF() {
    const element = document.getElementById('capture');
    if (element) {
      const detailRows = (this.employee.merits?.length || 0)
        + (this.employee.subtraction?.length || 0)
        + (this.employee.advance_payments?.length || 0);
      const height = (this.employee.acc_no ? 740 : 300) + 140 + Math.max(detailRows, 1) * 8;
      const options = {
        filename: this.employee?.name + '-' + this.employee.currentMonthValue + '.pdf',
        image: { type: 'jpeg', quality: 0.85 },
        html2canvas: {
          scale: 2,
          onclone: (doc: Document) => {
            doc.documentElement.setAttribute('dir', 'rtl');
            doc.body?.setAttribute('dir', 'rtl');
            const root = doc.getElementById('capture');
            if (!root) {
              return;
            }
            root.setAttribute('dir', 'rtl');
            (root as HTMLElement).style.direction = 'rtl';
            root.querySelectorAll('input').forEach((el) => {
              const input = el as HTMLInputElement;
              if (input.classList.contains('print-reason-date')) {
                input.setAttribute('dir', 'ltr');
                input.style.direction = 'ltr';
                input.style.unicodeBidi = 'isolate';
                return;
              }
              if (!input.getAttribute('dir')) {
                input.setAttribute('dir', 'rtl');
              }
            });
          },
        },
        jsPDF: { unit: 'mm', format: [297, height], orientation: 'portrait', autoPrint: { variant: 'non-conform' } }
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


