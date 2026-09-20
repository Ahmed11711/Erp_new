import { Injectable } from '@angular/core';
import * as jspdf from 'jspdf';
import autoTable from 'jspdf-autotable';
import html2canvas from 'html2canvas';
import { shapeArabicText, shapeArabicVisual } from 'naqqash';
import { ReplaySubject } from 'rxjs';
import { LoadingService } from './loading.service';

/** Safe max canvas dimension — browsers fail silently above ~16k px. */
const MAX_CANVAS_PX = 8192;
const ARABIC_RE = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/;

export interface AccountStatementPdfEntry {
  entryDate: string;
  createdAt: string;
  description: string;
  userName?: string;
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

  private fmtDate(value: string): string {
    if (!value) {
      return '';
    }
    const d = new Date(String(value));
    if (Number.isNaN(d.getTime())) {
      return String(value);
    }
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}/${month}/${year}`;
  }

  private fmtDateTime(value: string): string {
    if (!value) {
      return '';
    }
    const d = new Date(String(value));
    if (Number.isNaN(d.getTime())) {
      return String(value);
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
    let printWindow: Window | null = null;
    if (status === 'print') {
      printWindow = this.openPrintLoadingWindow(payload.fileName);
      if (!printWindow) {
        console.error('Unable to open print window. Allow popups for this site.');
        return;
      }
    }

    const pdf = new jspdf.jsPDF('p', 'pt', 'a4', true);

    this.showLoading();
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

      const headers: string[] = [this.shapeAr('تاريخ القيد'), this.shapeAr('تاريخ التسجيل')];
      if (payload.consolidated) {
        headers.push(this.shapeAr('الحساب الفرعي'));
      }
      headers.push(
        this.shapeAr('البيان / الشرح'),
        this.shapeAr('المستخدم'),
        this.shapeAr('مدين'),
        this.shapeAr('دائن'),
        this.shapeAr('الرصيد المتحرك')
      );

      const body = payload.entries.map((entry) => {
        const row: string[] = [
          this.fmtDate(entry.entryDate || entry.createdAt),
          this.fmtDateTime(entry.createdAt),
        ];
        if (payload.consolidated) {
          row.push(
            this.shapeAr(
              `${entry.subAccountCode ?? ''} — ${entry.subAccountName ?? ''}`
            )
          );
        }
        row.push(
          this.shapeAr(entry.description ?? ''),
          this.shapeAr(entry.userName ?? '—'),
          entry.debit > 0 ? this.fmtNum(entry.debit) : '-',
          entry.credit > 0 ? this.fmtNum(entry.credit) : '-',
          this.fmtNum(entry.runningBalance)
        );
        return row;
      });

      const totalLabelIndex = payload.consolidated ? 4 : 3;
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

      const numericStart = payload.consolidated ? 5 : 4;
      const columnStyles: Record<number, { halign: 'right' | 'center' | 'left'; cellWidth?: number }> = {
        0: { halign: 'center', cellWidth: 68 },
        1: { halign: 'center', cellWidth: 86 },
        [numericStart]: { halign: 'center', cellWidth: 62 },
        [numericStart + 1]: { halign: 'center', cellWidth: 62 },
        [numericStart + 2]: { halign: 'center', cellWidth: 72 },
      };
      if (payload.consolidated) {
        columnStyles[2] = { halign: 'right', cellWidth: 88 };
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
        const pdfBlob = pdf.output('blob');
        await this.deliverPdfToPrintWindow(printWindow, pdfBlob, payload.fileName);
      } else if (status === 'download') {
        pdf.save(`${payload.fileName}.pdf`);
      }
    } catch (error) {
      console.error('Error generating account statement PDF:', error);
      this.showPrintError(printWindow, 'حدث خطأ أثناء تجهيز الطباعة.');
    } finally {
      this.hideLoading();
    }
  }

  /** Unhide off-screen print wrappers so capture gets real dimensions. */
  private revealPrintAncestors(element: HTMLElement): () => void {
    const restored: Array<{ el: HTMLElement; cssText: string }> = [];
    let current: HTMLElement | null = element;

    while (current) {
      if (current.classList.contains('print-only-table')) {
        restored.push({ el: current, cssText: current.style.cssText });
        current.style.position = 'absolute';
        current.style.left = '-10000px';
        current.style.top = '0';
        current.style.width = '794px';
        current.style.height = 'auto';
        current.style.overflow = 'visible';
        current.style.visibility = 'visible';
        current.style.pointerEvents = 'none';
      }
      current = current.parentElement;
    }

    return () => {
      for (const item of restored) {
        item.el.style.cssText = item.cssText;
      }
    };
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

    let ancestor: HTMLElement | null = clonedRoot.parentElement;
    while (ancestor) {
      if (ancestor.classList.contains('print-only-table')) {
        ancestor.style.position = 'static';
        ancestor.style.left = 'auto';
        ancestor.style.top = 'auto';
        ancestor.style.width = `${captureWidth}px`;
        ancestor.style.height = 'auto';
        ancestor.style.overflow = 'visible';
        ancestor.style.visibility = 'visible';
        ancestor.style.pointerEvents = 'none';
      }
      ancestor = ancestor.parentElement;
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

    // الطباعة عبر صفحة HTML منفصلة (bidi أصلي) — html2canvas يكسّر العربي
    if (status === 'print') {
      this.printHtmlInSeparateWindow(htmlContent as HTMLElement, fileName);
      return;
    }

    const pdf = new jspdf.jsPDF('p', 'pt', 'a4', true);
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();
    const restorePrintAncestors = this.revealPrintAncestors(htmlContent);

    this.showLoading();
    try {
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
      const onclone = (clonedDoc: Document) => {
        this.applyCloneStyles(clonedDoc, htmlContent, captureWidth);
        const clonedRoot = htmlContent.id
          ? clonedDoc.getElementById(htmlContent.id)
          : null;
        this.shapeArabicInRoot(clonedRoot || clonedDoc.body);
      };

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

      if (status === 'download') {
        pdf.save(`${fileName}.pdf`);
      }
    } catch (error) {
      console.error('Error generating PDF:', error);
    } finally {
      restorePrintAncestors();
      this.hideLoading();
    }
  }

  /**
   * طباعة HTML في صفحة منفصلة مع RTL حقيقي — العربي يظهر صحيحاً.
   */
  printHtmlInSeparateWindow(htmlContent: HTMLElement, fileName: string): void {
    const printWindow = this.openPrintLoadingWindow(fileName);
    if (!printWindow) {
      console.error('Unable to open print window. Allow popups for this site.');
      return;
    }

    const restorePrintAncestors = this.revealPrintAncestors(htmlContent);
    try {
      const tableHtml = htmlContent.outerHTML;
      const title = this.escapeHtml(fileName || 'تقرير');

      printWindow.document.open();
      printWindow.document.write(`<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${title}</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 16px;
      background: #fff;
      color: #111827;
      font-family: 'Segoe UI', Tahoma, 'Traditional Arabic', 'Arial Unicode MS', Arial, sans-serif;
      direction: rtl;
      text-align: right;
    }
    h1 {
      margin: 0 0 14px;
      font-size: 18px;
      text-align: center;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      direction: rtl;
      font-size: 11px;
    }
    th, td {
      border: 1px solid #cbd5e1;
      padding: 6px 8px;
      text-align: center;
      vertical-align: middle;
      unicode-bidi: plaintext;
      word-break: break-word;
    }
    thead th {
      background: #1e293b;
      color: #fff;
      font-weight: 700;
    }
    tfoot td {
      background: #f1f5f9;
      font-weight: 700;
    }
    @media print {
      body { padding: 0; }
      @page { size: A4 landscape; margin: 10mm; }
    }
  </style>
</head>
<body>
  <h1>${title}</h1>
  ${tableHtml}
  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.focus();
        window.print();
      }, 250);
    });
  </script>
</body>
</html>`);
      printWindow.document.close();
    } catch (error) {
      console.error('Error writing print window:', error);
      this.showPrintError(printWindow, 'تعذر تجهيز صفحة الطباعة.');
    } finally {
      restorePrintAncestors();
    }
  }

  /** Shape Arabic text nodes for html2canvas (no bidi engine). */
  private shapeArabicInRoot(root: ParentNode | null | undefined): void {
    if (!root) {
      return;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes: Text[] = [];
    let current = walker.nextNode();
    while (current) {
      nodes.push(current as Text);
      current = walker.nextNode();
    }

    for (const node of nodes) {
      const raw = node.nodeValue;
      if (!raw || !ARABIC_RE.test(raw)) {
        continue;
      }
      if (/[\uFB50-\uFDFF\uFE70-\uFEFF]/.test(raw)) {
        continue;
      }
      const parent = node.parentElement;
      if (parent && /^(SCRIPT|STYLE)$/i.test(parent.tagName)) {
        continue;
      }
      try {
        node.nodeValue = shapeArabicVisual(raw);
      } catch {
        // keep original
      }
    }
  }

  /** صفحة منفصلة تظهر فوراً أثناء تجهيز ملف الطباعة */
  openPrintLoadingWindow(fileName = 'تقرير'): Window | null {
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      return null;
    }

    printWindow.document.write(`<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>جاري تحميل الطباعة — ${this.escapeHtml(fileName)}</title>
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f3f5f9;
      font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
      color: #0f172a;
    }
    .box {
      text-align: center;
      background: #fff;
      border-radius: 16px;
      padding: 36px 42px;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
      max-width: 420px;
    }
    .spinner {
      width: 42px;
      height: 42px;
      border: 3px solid #e2e8f0;
      border-top-color: #2563eb;
      border-radius: 50%;
      margin: 0 auto 16px;
      animation: spin 0.8s linear infinite;
    }
    h1 { margin: 0 0 8px; font-size: 1.15rem; }
    p { margin: 0; color: #64748b; font-size: 0.95rem; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="box">
    <div class="spinner" aria-hidden="true"></div>
    <h1>جاري تحميل الطباعة</h1>
    <p>يتم تجهيز التقرير في صفحة منفصلة… يرجى الانتظار</p>
  </div>
</body>
</html>`);
    printWindow.document.close();
    return printWindow;
  }

  private escapeHtml(value: string): string {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  private showPrintError(printWindow: Window | null, message: string): void {
    if (!printWindow || printWindow.closed) {
      return;
    }
    try {
      printWindow.document.open();
      printWindow.document.write(`<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title>خطأ الطباعة</title></head>
<body style="font-family:Tahoma,Arial,sans-serif;padding:40px;text-align:center;color:#b91c1c;">
  <h2>${this.escapeHtml(message)}</h2>
  <p style="color:#64748b;">يمكنك إغلاق هذه النافذة والمحاولة مرة أخرى.</p>
</body>
</html>`);
      printWindow.document.close();
    } catch {
      // ignore
    }
  }

  /** يحمّل الـ PDF في الصفحة المنفصلة ثم يفتح حوار الطباعة */
  async deliverPdfToPrintWindow(
    printWindow: Window | null,
    pdfBlob: Blob,
    fileName: string
  ): Promise<void> {
    const pdfUrl = URL.createObjectURL(pdfBlob);

    if (!printWindow || printWindow.closed) {
      const opened = window.open(pdfUrl, '_blank');
      if (!opened) {
        this.triggerPrintViaIframe(pdfUrl);
        return;
      }
      printWindow = opened;
    } else {
      try {
        printWindow.location.href = pdfUrl;
      } catch {
        const opened = window.open(pdfUrl, '_blank');
        if (!opened) {
          this.triggerPrintViaIframe(pdfUrl);
          return;
        }
        printWindow = opened;
      }
    }

    const target = printWindow;
    const tryPrint = () => {
      try {
        target.focus();
        target.print();
      } catch (error) {
        console.error('Unable to trigger print dialog:', error);
      }
    };

    await new Promise<void>((resolve) => setTimeout(resolve, 900));
    if (!target.closed) {
      tryPrint();
    }
    setTimeout(() => {
      if (!target.closed) {
        tryPrint();
      }
    }, 1800);

    setTimeout(() => URL.revokeObjectURL(pdfUrl), 180_000);
  }

  triggerPrint(pdfUrl: string) {
    const printWindow = window.open(pdfUrl, '_blank');
    if (printWindow) {
      const tryPrint = () => {
        try {
          printWindow.focus();
          printWindow.print();
        } catch (error) {
          console.error('Unable to print from window:', error);
        }
      };
      printWindow.addEventListener('load', tryPrint);
      setTimeout(tryPrint, 900);
      setTimeout(() => URL.revokeObjectURL(pdfUrl), 180_000);
      return;
    }

    this.triggerPrintViaIframe(pdfUrl);
  }

  private triggerPrintViaIframe(pdfUrl: string) {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.src = pdfUrl;
    document.body.appendChild(iframe);

    const cleanup = () => {
      iframe.remove();
      URL.revokeObjectURL(pdfUrl);
    };

    iframe.onload = () => {
      try {
        iframe.contentWindow?.focus();
        iframe.contentWindow?.print();
      } catch (error) {
        console.error('Unable to print via iframe.', error);
      } finally {
        setTimeout(cleanup, 2000);
      }
    };
  }

}
