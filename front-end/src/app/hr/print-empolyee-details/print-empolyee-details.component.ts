import { Component, Input, SimpleChanges } from '@angular/core';
import * as html2pdf from 'html2pdf.js';
import { saveAs } from 'file-saver';
import html2canvas from 'html2canvas';


@Component({
  selector: 'app-print-empolyee-details',
  templateUrl: './print-empolyee-details.component.html',
  styleUrls: ['./print-empolyee-details.component.css']
})
export class PrintEmpolyeeDetailsComponent {
  @Input() employee: any = {};
  currentDateValue!:any;
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
      const height = this.employee.acc_no ? 740 : 300;
      const options = {
        filename: this.employee?.name + '-' + this.employee.currentMonthValue + '.pdf',
        image: { type: 'jpeg', quality: 0.85 },
        html2canvas: { scale: 2 },
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


