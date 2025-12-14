import { Injectable } from '@angular/core';
import * as jspdf from 'jspdf';
import html2canvas from 'html2canvas';
import { ReplaySubject } from 'rxjs';
import { LoadingService } from './loading.service';

@Injectable({
  providedIn: 'root',
})
export class PdfService extends LoadingService {
  private pdfData = new ReplaySubject<any>();
  currentPDFData = this.pdfData.asObservable();


  async generatePdf(htmlContent: any, status: string, fileName:string) {

    const pdf = new jspdf.jsPDF('p', 'pt', 'a4', true);
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();

    try {
      const canvas = await html2canvas(htmlContent, { scale: 2, useCORS: true, backgroundColor: null });
      const imgData = canvas.toDataURL('image/png', 1.0);

      let yOffset = 0;
      while (yOffset < canvas.height) {
        const sectionCanvas = document.createElement('canvas');
        sectionCanvas.width = canvas.width;
        sectionCanvas.height = pdfHeight * (canvas.width / pdfWidth);
        const context = sectionCanvas.getContext('2d');

        if (context) {
          context.drawImage(
            canvas,
            0, yOffset, canvas.width, sectionCanvas.height,
            0, 0, sectionCanvas.width, sectionCanvas.height
          );

          const sectionImgData = sectionCanvas.toDataURL('image/png', 1);
          pdf.addImage(sectionImgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
        }

        yOffset += sectionCanvas.height;

        if (yOffset < canvas.height) {
          pdf.addPage();
        }

        sectionCanvas.remove();
      }

      if (status === 'print') {
        pdf.save(`${fileName}.pdf`);
        const pdfBlob = pdf.output('blob');
        const pdfUrl = URL.createObjectURL(pdfBlob);
        this.triggerPrint(pdfUrl);
      } else {
      }
    } catch (error) {
      console.error('Error generating PDF:', error);
    } finally {
    }
  }

  triggerPrint(pdfUrl: string) {
    const printWindow = window.open(pdfUrl, '_blank');
    if (printWindow) {
      printWindow.addEventListener('load', () => {
        printWindow.print();
      });
    } else {
      console.error('Unable to open print window.');
    }
  }

}
