import { Component, Input, ChangeDetectorRef } from '@angular/core';
import * as html2pdf from 'html2pdf.js';

@Component({
  selector: 'app-sticker',
  templateUrl: './sticker.component.html',
  styleUrls: ['./sticker.component.css']
})
export class StickerComponent {
  private _sticker: any = {};
  count: number = 0;
  countArray: number[] = [];

  @Input()
  set sticker(value: any) {
    this._sticker = value;
    if (value && Object.keys(value).length > 0) {
      this.count = value.size;
      this.countArray = Array.from({ length: this.count }, (_, index) => index);
      this.downloadPDF();
    }
  }

  get sticker(): any {
    return this._sticker;
  }

  constructor(private cdr: ChangeDetectorRef) {}

  downloadPDF() {
    const element = document.getElementById('capture2');
    if (element) {
      const options = {
        filename: this.sticker?.id + '.pdf',
        image: { type: 'png' },
        html2canvas: { scale: 4 },
        jsPDF: {
          unit: 'mm',
          orientation: 'portrait',
          autoPrint: { variant: 'non-conform' }
        }
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

    // Manually trigger change detection
    this.cdr.detectChanges();
  }
}
