import { Injectable } from '@angular/core';
import * as jspdf from 'jspdf';
import autoTable from 'jspdf-autotable';
import html2canvas from 'html2canvas';
import { shapeArabicText } from 'naqqash';
import { ReplaySubject } from 'rxjs';
import { LoadingService } from './loading.service';

/** Safe max canvas dimension — browsers fail silently above ~16k px. */
const MAX_CANVAS_PX = 8192;

export interface AccountStatementPdfEntry {
  entryDate: string;
  createdAt: string;
  description: string;
  debit: number;
  credit: number;
  runningBalance: number;
  subAccountCode?: string;
  subAccountName?: string;
}

export interface AccountStatementPdfPayload {
  fileName: string;
  accountCode: string;
  accountName: string;
  dateFrom: string | null;
  dateTo: string | null;
  consolidated: boolean;
  accountsInScope?: number;
  openingBalance: number;
  totalDebit: number;
  totalCredit: number;
  closingBalance: number;
  entries: AccountStatementPdfEntry[];
}

@Injectable({
  providedIn: 'root',
})
export class PdfService extends LoadingService {
  private pdfData = new ReplaySubject<any>();
  currentPDFData = this.pdfData.asObservable();
  private amiriFontLoaded = false;

  private shapeAr(text: string): string {
    if (!text) {
      return '';
    }
    return shapeArabicText(text);
  }

  private fmtNum(value: number): string {
    return Number(value || 0).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  private fmtDateTime(entryDate: string, createdAt: string): string {
    const raw = entryDate || createdAt;
    if (!raw) {
      return '';
    }
    const d = new Date(String(raw));
    if (Number.isNaN(d.getTime())) {
      return String(raw);
    }
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const mins = String(d.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${mins}`;
  }

  private async ensureAmiriFont(pdf: jspdf.jsPDF): Promise<void> {
    if (this.amiriFontLoaded) {
      pdf.setFont('Amiri', 'normal');
      return;
    }

    const response = await fetch('assets/fonts/Amiri-Regular.ttf');
    if (!response.ok) {
      throw new Error('Failed to load Arabic font for PDF export.');
    }

    const buffer = await response.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
      binary += String.fromCharCode(bytes[i]);
    }

    pdf.addFileToVFS('Amiri-Regular.ttf', btoa(binary));
    pdf.addFont('Amiri-Regular.ttf', 'Amiri', 'normal');
    pdf.setFont('Amiri', 'normal');
    this.amiriFontLoaded = true;
  }

  async generateAccountStatementPdf(
    payload: AccountStatementPdfPayload,
    status: string
  ): Promise<void> {
    const pdf = new jspdf.jsPDF('p', 'pt', 'a4', true);

    try {
      await this.ensureAmiriFont(pdf);

      const pageWidth = pdf.internal.pageSize.getWidth();
      const marginX = 14;
      let y = 28;

      pdf.setFont('Amiri', 'normal');
      pdf.setFontSize(16);
      pdf.setTextColor(15, 23, 42);
      pdf.text(this.shapeAr('كشف حساب تفصيلي'), pageWidth / 2, y, { align: 'center' });
      y += 22;

      pdf.setFontSize(11);
      pdf.text(
        this.shapeAr(`ملخص الحساب: ${payload.accountName} (${payload.accountCode})`),
        pageWidth - marginX,
        y,
        { align: 'right' }
      );
      y += 16;

      if (payload.consolidated && payload.accountsInScope) {
        pdf.setFontSize(9);
        pdf.setTextColor(100, 116, 139);
        pdf.text(
          this.shapeAr(`عرض مجمّع: ${payload.accountsInScope} حساباً في النطاق`),
          pageWidth - marginX,
          y,
          { align: 'right' }
        );
        y += 14;
        pdf.setTextColor(15, 23, 42);
      }

      pdf.setFontSize(9);
      pdf.text(
        this.shapeAr(
          `الفترة: من ${payload.dateFrom || '—'} إلى ${payload.dateTo || '—'}`
        ),
        pageWidth - marginX,
        y,
        { align: 'right' }
      );
      y += 16;

      pdf.setFontSize(10);
      const summaryLines = [
        `الرصيد الافتتاحي: ${this.fmtNum(payload.openingBalance)}`,
        `إجمالي مدين (وارد): ${this.fmtNum(payload.totalDebit)}`,
        `إجمالي دائن (صادر): ${this.fmtNum(payload.totalCredit)}`,
        `الرصيد الحالي: ${this.fmtNum(payload.closingBalance)}`,
      ];
      for (const line of summaryLines) {
        pdf.text(this.shapeAr(line), pageWidth - marginX, y, { align: 'right' });
        y += 14;
      }
      y += 6;

      const headers: string[] = [this.shapeAr('التاريخ')];
      if (payload.consolidated) {
        headers.push(this.shapeAr('الحساب الفرعي'));
      }
      headers.push(
        this.shapeAr('البيان / الشرح'),
        this.shapeAr('مدين'),
        this.shapeAr('دائن'),
        this.shapeAr('الرصيد المتحرك')
      );

      const body = payload.entries.map((entry) => {
        const row: string[] = [this.fmtDateTime(entry.entryDate, entry.createdAt)];
        if (payload.consolidated) {
          row.push(
            this.shapeAr(
              `${entry.subAccountCode ?? ''} — ${entry.subAccountName ?? ''}`
            )
          );
        }
        row.push(
          this.shapeAr(entry.description ?? ''),
          entry.debit > 0 ? this.fmtNum(entry.debit) : '-',
          entry.credit > 0 ? this.fmtNum(entry.credit) : '-',
          this.fmtNum(entry.runningBalance)
        );
        return row;
      });

      const totalLabelIndex = payload.consolidated ? 2 : 1;
      const footRow = headers.map((_, index) => {
        if (index === 0) {
          return '';
        }
        if (index === totalLabelIndex) {
          return this.shapeAr('الإجمالي');
        }
        if (index === totalLabelIndex + 1) {
          return this.fmtNum(payload.totalDebit);
        }
        if (index === totalLabelIndex + 2) {
          return this.fmtNum(payload.totalCredit);
        }
        if (index === totalLabelIndex + 3) {
          return this.fmtNum(payload.closingBalance);
        }
        return '';
      });

      const numericStart = payload.consolidated ? 3 : 2;
      const columnStyles: Record<number, { halign: 'right' | 'center' | 'left'; cellWidth?: number }> = {
        0: { halign: 'center', cellWidth: 78 },
        [numericStart]: { halign: 'center', cellWidth: 62 },
        [numericStart + 1]: { halign: 'center', cellWidth: 62 },
        [numericStart + 2]: { halign: 'center', cellWidth: 72 },
      };
      if (payload.consolidated) {
        columnStyles[1] = { halign: 'right', cellWidth: 88 };
      }

      autoTable(pdf, {
        startY: y,
        head: [headers],
        body,
        foot: [footRow],
        styles: {
          font: 'Amiri',
          fontStyle: 'normal',
          fontSize: 8.5,
          halign: 'right',
          cellPadding: 3,
          overflow: 'linebreak',
        },
        headStyles: {
          font: 'Amiri',
          fontStyle: 'normal',
          fillColor: [30, 41, 59],
          textColor: [255, 255, 255],
          halign: 'right',
        },
        footStyles: {
          font: 'Amiri',
          fontStyle: 'normal',
          fillColor: [238, 242, 255],
          textColor: [30, 27, 75],
          halign: 'right',
        },
        columnStyles,
        margin: { top: 28, right: marginX, left: marginX },
        didDrawPage: (data) => {
          pdf.setFont('Amiri', 'normal');
          pdf.setFontSize(8);
          pdf.setTextColor(148, 163, 184);
          pdf.text(
            this.shapeAr(`صفحة ${data.pageNumber}`),
            pageWidth / 2,
            pdf.internal.pageSize.getHeight() - 10,
            { align: 'center' }
          );
        },
      });

      if (status === 'print') {
        pdf.save(`${payload.fileName}.pdf`);
        const pdfBlob = pdf.output('blob');
        const pdfUrl = URL.createObjectURL(pdfBlob);
        this.triggerPrint(pdfUrl);
      } else if (status === 'download') {
        pdf.save(`${payload.fileName}.pdf`);
      }
    } catch (error) {
      console.error('Error generating account statement PDF:', error);
    }
  }

  private applyCloneStyles(
    clonedDoc: Document,
    htmlContent: HTMLElement,
    captureWidth: number
  ): void {
    const clonedRoot = htmlContent.id
      ? clonedDoc.getElementById(htmlContent.id)
      : null;

    if (!clonedRoot) {
      return;
    }

    clonedRoot.setAttribute('dir', 'rtl');
    clonedRoot.style.direction = 'rtl';
    clonedRoot.style.textAlign = 'right';
    clonedRoot.style.backgroundColor = '#ffffff';
    clonedRoot.style.color = '#1f2937';
    clonedRoot.style.fontFamily = "'Segoe UI', Tahoma, 'Arial Unicode MS', 'Traditional Arabic', sans-serif";
    clonedRoot.style.position = 'static';
    clonedRoot.style.visibility = 'visible';
    clonedRoot.style.display = 'block';
    clonedRoot.style.overflow = 'visible';
    clonedRoot.style.width = `${captureWidth}px`;
    clonedRoot.style.height = 'auto';

    clonedRoot.querySelectorAll('.report-content-card, .report-table-wrap').forEach((node) => {
      const el = node as HTMLElement;
      el.style.overflow = 'visible';
      el.style.maxHeight = 'none';
    });

    clonedRoot.querySelectorAll('table, th, td').forEach((node) => {
      const el = node as HTMLElement;
      el.style.color = el.style.color || '#1f2937';
    });
  }

  private appendCanvasToPdf(
    pdf: jspdf.jsPDF,
    canvas: HTMLCanvasElement,
    pdfWidth: number,
    pdfHeight: number,
    pdfHasPage: boolean
  ): boolean {
    const pageSliceHeight = pdfHeight * (canvas.width / pdfWidth);
    let yOffset = 0;
    let hasPage = pdfHasPage;

    while (yOffset < canvas.height) {
      const sliceHeight = Math.min(pageSliceHeight, canvas.height - yOffset);
      const sectionCanvas = document.createElement('canvas');
      sectionCanvas.width = canvas.width;
      sectionCanvas.height = sliceHeight;
      const context = sectionCanvas.getContext('2d');

      if (context) {
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, sectionCanvas.width, sectionCanvas.height);
        context.drawImage(
          canvas,
          0, yOffset, canvas.width, sliceHeight,
          0, 0, sectionCanvas.width, sliceHeight
        );

        if (hasPage) {
          pdf.addPage();
        }
        hasPage = true;

        const imgHeight = (sliceHeight / canvas.width) * pdfWidth;
        pdf.addImage(
          sectionCanvas.toDataURL('image/png', 1),
          'PNG',
          0,
          0,
          pdfWidth,
          imgHeight
        );
      }

      yOffset += sliceHeight;
      sectionCanvas.remove();
    }

    return hasPage;
  }

  async generatePdf(htmlContent: any, status: string, fileName: string) {
    if (!htmlContent) {
      return;
    }

    const pdf = new jspdf.jsPDF('p', 'pt', 'a4', true);
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();

    try {
      htmlContent.scrollIntoView({ block: 'start' });

      if (document.fonts?.ready) {
        await document.fonts.ready;
      }

      await new Promise<void>((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
      });

      const captureWidth = Math.max(
        htmlContent.offsetWidth || 0,
        htmlContent.scrollWidth || 0,
        htmlContent.clientWidth || 0,
        794
      );

      const totalHeight = Math.max(
        htmlContent.scrollHeight || 0,
        htmlContent.offsetHeight || 0
      );

      if (!totalHeight) {
        console.error('Error generating PDF: element has no height.');
        return;
      }

      let scale = 2;
      if (totalHeight * scale > MAX_CANVAS_PX) {
        scale = Math.max(1, Math.floor(MAX_CANVAS_PX / totalHeight));
      }

      const chunkElementHeight = Math.max(400, Math.floor(MAX_CANVAS_PX / scale));
      const onclone = (clonedDoc: Document) =>
        this.applyCloneStyles(clonedDoc, htmlContent, captureWidth);

      let pdfHasPage = false;

      for (let yStart = 0; yStart < totalHeight; yStart += chunkElementHeight) {
        const chunkHeight = Math.min(chunkElementHeight, totalHeight - yStart);

        const canvas = await html2canvas(htmlContent, {
          scale,
          useCORS: true,
          backgroundColor: '#ffffff',
          logging: false,
          width: captureWidth,
          height: chunkHeight,
          y: yStart,
          scrollX: 0,
          scrollY: -yStart,
          windowHeight: totalHeight,
          onclone,
        });

        if (!canvas.width || !canvas.height) {
          continue;
        }

        pdfHasPage = this.appendCanvasToPdf(
          pdf,
          canvas,
          pdfWidth,
          pdfHeight,
          pdfHasPage
        );
      }

      if (!pdfHasPage) {
        console.error('Error generating PDF: capture produced no pages.');
        return;
      }

      if (status === 'print') {
        pdf.save(`${fileName}.pdf`);
        const pdfBlob = pdf.output('blob');
        const pdfUrl = URL.createObjectURL(pdfBlob);
        this.triggerPrint(pdfUrl);
      } else if (status === 'download') {
        pdf.save(`${fileName}.pdf`);
      }
    } catch (error) {
      console.error('Error generating PDF:', error);
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
