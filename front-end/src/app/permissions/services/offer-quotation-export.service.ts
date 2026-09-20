import { Injectable } from '@angular/core';
import { shapeArabicVisual } from 'naqqash';
import { firstValueFrom } from 'rxjs';
import { environment } from 'src/env/env';
import { applyOfferVat } from 'src/app/shared/utils/vat';
import { OfferService } from './offer.service';

/** Arabic letters / presentation forms — used to detect text that needs PDF shaping. */
const ARABIC_RE = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/;
/**
 * Fonts that include Arabic Presentation Forms-B (U+FE70–U+FEFF).
 * Cairo/Google fonts often omit these codepoints → overlapping glyphs in html2canvas.
 */
const PDF_ARABIC_FONT =
  "'Segoe UI', Tahoma, 'Traditional Arabic', 'Arabic Typesetting', Arial, sans-serif";

type Html2PdfFactory = () => {
  set: (options: unknown) => {
    from: (element: HTMLElement) => {
      save: () => Promise<void>;
      toPdf: () => { output: (type: string) => Promise<Blob> };
    };
  };
};

@Injectable({
  providedIn: 'root',
})
export class OfferQuotationExportService {
  constructor(private offerService: OfferService) {}

  /** تحميل html2pdf عند الحاجة فقط حتى لا يفشل lazy-load لموديول permissions */
  private async loadHtml2Pdf(): Promise<Html2PdfFactory> {
    const mod: any = await import('html2pdf.js');
    const factory = mod?.default ?? mod;
    if (typeof factory !== 'function') {
      throw new Error('html2pdf.js failed to load');
    }
    return factory as Html2PdfFactory;
  }

  /** Opens a print preview window (print + download PDF in toolbar). */
  openPrintPreview(
    element: HTMLElement,
    offerId: number | string,
    clientName?: string | null,
    offerDate?: string | null
  ): void {
    const printWindow = window.open('', '_blank');
    // Mobile browsers often block popups — fall back to direct PDF download.
    if (!printWindow) {
      void this.downloadPdf(element, offerId, clientName, offerDate);
      return;
    }
    const pdfFileName = this.quotationFileName(clientName, offerId, offerDate);

    // Keep logical Arabic in the print window (browser print has real bidi).
    // PDF download shapes in html2canvas onclone via window.__shapeArabicInRoot.
    const printContent = element.innerHTML;
    const baseUrl = window.location.origin + '/';
    const styleLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
      .map((el: Element) => (el as HTMLLinkElement).outerHTML)
      .join('\n');
    const inlineStyles = Array.from(document.querySelectorAll('style'))
      .map((el: Element) => (el as HTMLStyleElement).outerHTML)
      .join('\n');

    printWindow.document.write(`
      <!DOCTYPE html>
      <html lang="ar" dir="ltr">
      <head>
        <meta charset="UTF-8">
        <base href="${baseUrl}">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Quotation #${offerId}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
        ${styleLinks}
        ${inlineStyles}
        <style>
          * { box-sizing: border-box; }
          body {
            margin: 0;
            padding: 70px 30px 30px 30px;
            background: #e8e8e8;
            direction: ltr;
          }
          p, td, th, h2, h3, h4, span, div {
            font-family: 'Cairo', 'Montserrat', Arial, sans-serif !important;
          }
          img { max-width: 100%; }
          .print-toolbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 54px;
            background: #82225e;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 24px;
            z-index: 9999;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
          }
          .print-toolbar span {
            font-family: 'Cairo', Arial, sans-serif !important;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            flex: 1;
          }
          .toolbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            color: #82225e;
            border: none;
            padding: 7px 18px;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Cairo', Arial, sans-serif !important;
            cursor: pointer;
            font-weight: 700;
            transition: background 0.2s;
          }
          .toolbar-btn:hover { background: #f5d5e8; }
          .toolbar-btn.download { background: #6b1a4c; color: #fff; border: 2px solid #fff; }
          .toolbar-btn.download:hover { background: #9e2b72; }
          .toolbar-btn:disabled { opacity: 0.65; cursor: wait; }
          .page-wrapper {
            display: flex;
            justify-content: center;
            padding-top: 20px;
            zoom: 1.3;
            transform-origin: top center;
          }
          #capture {
            width: 213mm;
            min-height: 296mm;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
          }
          .note-section {
            page-break-inside: avoid;
            max-height: 85mm;
            overflow: hidden;
            padding: 8px 12px !important;
            margin-top: 8px !important;
            margin-bottom: 12px !important;
            background: #fff;
          }
          .quotation-desc-ar,
          .quotation-note-text {
            unicode-bidi: plaintext !important;
            text-align: start !important;
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            word-break: normal !important;
            overflow-wrap: break-word !important;
            white-space: normal !important;
            letter-spacing: 0 !important;
            word-spacing: normal !important;
            line-height: 1.45 !important;
            font-family: 'Segoe UI', Tahoma, 'Traditional Arabic', Arial, sans-serif !important;
            font-synthesis: none !important;
          }
          .quotation-desc-ar bdi,
          .quotation-desc-ar p,
          .quotation-note-text bdi {
            writing-mode: horizontal-tb !important;
            word-break: normal !important;
            overflow-wrap: break-word !important;
            letter-spacing: 0 !important;
            font-family: 'Segoe UI', Tahoma, 'Traditional Arabic', Arial, sans-serif !important;
          }
          #capture .table {
            table-layout: fixed !important;
          }
          #capture .table th:first-child,
          #capture .table td:first-child {
            width: 40% !important;
          }
          @page { size: A4; margin: 0; }
          /* Hide client phone only — keep company & customer name on print / PDF */
          .quote-client-phone { display: none !important; }
          @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            body { margin: 0; padding: 0; background: #fff; }
            .print-toolbar { display: none !important; }
            .page-wrapper { zoom: 1; padding-top: 0; }
            #capture { box-shadow: none; }
            .quote-client-phone { display: none !important; }
          }
        </style>
      </head>
      <body>
        <div class="print-toolbar">
          <span>عرض الأسعار #${offerId}</span>
          <button type="button" class="toolbar-btn download" id="btn-download-pdf">تحميل PDF</button>
          <button type="button" class="toolbar-btn" onclick="window.print()">طباعة</button>
        </div>
        <div class="page-wrapper">
          <div id="capture" dir="ltr" class="row border m-0 p-0">
            ${printContent}
          </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"><\/script>
        <script>
          (function () {
            var btn = document.getElementById('btn-download-pdf');
            if (!btn) return;
            btn.addEventListener('click', function () {
              var el = document.getElementById('capture');
              if (!el || typeof html2pdf === 'undefined') {
                window.print();
                return;
              }
              btn.disabled = true;
              btn.textContent = 'جاري التحميل...';
              var run = function () {
                html2pdf().set({
                  margin: 0,
                  filename: '${pdfFileName}',
                  image: { type: 'jpeg', quality: 0.95 },
                  html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff',
                    foreignObjectRendering: false,
                    onclone: function (clonedDoc, cloned) {
                      if (typeof window.__shapeArabicInRoot === 'function') {
                        window.__shapeArabicInRoot(cloned || clonedDoc.body);
                      }
                    }
                  },
                  jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                  pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                }).from(el).save().then(function () {
                  btn.disabled = false;
                  btn.textContent = 'تحميل PDF';
                }).catch(function () {
                  btn.disabled = false;
                  btn.textContent = 'تحميل PDF';
                  window.print();
                });
              };
              if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(run).catch(run);
              } else {
                run();
              }
            });
          })();
        <\/script>
      </body>
      </html>
    `);
    printWindow.document.close();
    // Same-origin print window — reuse app shaper for PDF capture only.
    (printWindow as Window & { __shapeArabicInRoot?: (root: ParentNode) => void }).__shapeArabicInRoot =
      (root: ParentNode) => this.shapeArabicInRoot(root);
  }

  /** Saves the quotation DOM node as a PDF file download. */
  downloadPdf(
    element: HTMLElement,
    offerId: number | string,
    clientName?: string | null,
    offerDate?: string | null
  ): Promise<void> {
    // Capture the live node at full A4 size (off-screen clones render blank in html2canvas).
    return this.withFullSizeCapture(element, () =>
      this.saveHtmlAsPdf(element, this.quotationFileName(clientName, offerId, offerDate))
    );
  }

  /** Fetch offer by id and download quotation PDF without navigating. */
  async downloadByOfferId(offerId: number | string): Promise<void> {
    const offer: any = applyOfferVat(await firstValueFrom(this.offerService.getOfferById(offerId)) || {});
    if (!offer?.id) {
      throw new Error('Offer not found');
    }

    const html =
      offer.offer === 'offer2'
        ? this.buildOffer2Html(offer)
        : this.buildOffer1Html(offer);

    const host = document.createElement('div');
    host.setAttribute('dir', 'ltr');
    // Keep in viewport — far off-screen / opacity:0 / z-index:-1 often yield blank PDFs.
    host.style.cssText =
      'position:fixed;left:0;top:0;width:210mm;background:#fff;opacity:0.01;pointer-events:none;z-index:2147483646;';
    host.innerHTML = html;
    document.body.appendChild(host);

    try {
      await this.waitForFonts();
      await this.waitForImages(host);
      await this.saveHtmlAsPdf(
        host,
        this.quotationFileName(
          this.resolveClientName(offer),
          offer.id,
          this.resolveOfferDate(offer)
        )
      );
    } finally {
      host.remove();
    }
  }

  /** Export a tabular list of offers as PDF. */
  async downloadOffersListPdf(offers: any[], fileName = 'price-offers'): Promise<void> {
    const rows = (offers || [])
      .map((o) => `
        <tr>
          <td>${this.esc(o?.id)}</td>
          <td>${this.esc(o?.offer === 'offer2' ? 'عرض 2' : 'عرض 1')}</td>
          <td>${this.esc(o?.quote)}</td>
          <td>${this.esc(o?.customer_company?.name || '—')}</td>
          <td>${this.esc(o?.dateFrom)}</td>
          <td dir="ltr">${this.fmt(o?.subtotal)}</td>
          <td dir="ltr">${this.fmt(o?.total)}</td>
          <td>${o?.converted_order_id ? '#' + this.esc(o.converted_order_id) : '—'}</td>
        </tr>
      `)
      .join('');

    const html = `
      <div style="font-family:Cairo,Tahoma,sans-serif;direction:rtl;padding:16px;color:#0f172a;">
        <h2 style="margin:0 0 6px;color:#82225e;text-align:center;">عروض الأسعار</h2>
        <p style="margin:0 0 14px;text-align:center;color:#64748b;font-size:12px;">
          عدد السجلات: ${(offers || []).length} — ${new Date().toLocaleDateString('ar-EG')}
        </p>
        <table style="width:100%;border-collapse:collapse;font-size:11px;text-align:center;">
          <thead>
            <tr style="background:#82225e;color:#fff;">
              <th style="padding:8px;border:1px solid #6b1a4c;">#</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">النوع</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">العميل</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">عميل الشركة</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">التاريخ</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">السعر</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">الكلي</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">الطلب</th>
            </tr>
          </thead>
          <tbody>${rows || '<tr><td colspan="8">لا توجد بيانات</td></tr>'}</tbody>
        </table>
      </div>
    `;

    await this.renderAndSave(html, `${fileName}.pdf`, 'rtl');
  }

  /** Export a tabular list of offer-orders as PDF. */
  async downloadOfferOrdersListPdf(orders: any[], fileName = 'offer-orders'): Promise<void> {
    const rows = (orders || [])
      .map((o) => `
        <tr>
          <td>${this.esc(o?.id)}</td>
          <td>#${this.esc(o?.offer_id)}</td>
          <td>${this.esc(o?.customer_name)}</td>
          <td dir="ltr">${this.esc(o?.customer_phone_1 || '—')}</td>
          <td>${this.esc(this.fmtDate(o?.order_date))}</td>
          <td>${this.esc(o?.order_status || '—')}</td>
          <td dir="ltr">${this.fmt(o?.net_total)}</td>
          <td>${o?.offer_debt_posted ? 'على العميل' : '—'}</td>
        </tr>
      `)
      .join('');

    const html = `
      <div style="font-family:Cairo,Tahoma,sans-serif;direction:rtl;padding:16px;color:#0f172a;">
        <h2 style="margin:0 0 6px;color:#82225e;text-align:center;">طلبات عروض الأسعار</h2>
        <p style="margin:0 0 14px;text-align:center;color:#64748b;font-size:12px;">
          عدد السجلات: ${(orders || []).length} — ${new Date().toLocaleDateString('ar-EG')}
        </p>
        <table style="width:100%;border-collapse:collapse;font-size:11px;text-align:center;">
          <thead>
            <tr style="background:#82225e;color:#fff;">
              <th style="padding:8px;border:1px solid #6b1a4c;">رقم الطلب</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">عرض السعر</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">العميل</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">الهاتف</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">التاريخ</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">الحالة</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">الصافي</th>
              <th style="padding:8px;border:1px solid #6b1a4c;">المديونية</th>
            </tr>
          </thead>
          <tbody>${rows || '<tr><td colspan="8">لا توجد بيانات</td></tr>'}</tbody>
        </table>
      </div>
    `;

    await this.renderAndSave(html, `${fileName}.pdf`, 'rtl');
  }

  private async renderAndSave(html: string, filename: string, dir: 'rtl' | 'ltr'): Promise<void> {
    const host = document.createElement('div');
    host.setAttribute('dir', dir);
    host.style.cssText =
      'position:fixed;left:0;top:0;width:210mm;background:#fff;opacity:0.01;pointer-events:none;z-index:2147483646;';
    host.innerHTML = html;
    document.body.appendChild(host);
    try {
      await this.saveHtmlAsPdf(host, filename, [8, 8, 8, 8]);
    } finally {
      host.remove();
    }
  }

  /**
   * Temporarily force A4 print size on the live preview so mobile CSS zoom
   * is not baked into the PDF (and avoid off-screen clones that capture blank).
   */
  private async withFullSizeCapture<T>(
    element: HTMLElement,
    run: () => Promise<T>
  ): Promise<T> {
    const wrap = element.closest('.offer-capture-wrap') as HTMLElement | null;
    const prevEl = {
      cssText: element.style.cssText,
    };
    const prevWrap = wrap
      ? { overflow: wrap.style.overflow, overflowX: wrap.style.overflowX }
      : null;

    element.style.setProperty('zoom', '1', 'important');
    element.style.setProperty('transform', 'none', 'important');
    element.style.setProperty('width', '210mm', 'important');
    element.style.setProperty('max-width', '210mm', 'important');
    element.style.setProperty('min-width', '210mm', 'important');
    element.style.setProperty('min-height', '296mm', 'important');
    element.style.setProperty('overflow', 'hidden', 'important');
    element.style.setProperty('box-sizing', 'border-box', 'important');
    element.classList.add('is-pdf-export');
    if (wrap) {
      wrap.style.overflow = 'visible';
      wrap.style.overflowX = 'visible';
    }

    try {
      await this.waitForFonts();
      await new Promise<void>((r) =>
        requestAnimationFrame(() => requestAnimationFrame(() => r()))
      );
      return await run();
    } finally {
      element.classList.remove('is-pdf-export');
      element.style.cssText = prevEl.cssText;
      if (wrap && prevWrap) {
        wrap.style.overflow = prevWrap.overflow;
        wrap.style.overflowX = prevWrap.overflowX;
      }
    }
  }

  private async waitForFonts(): Promise<void> {
    try {
      if (document.fonts?.ready) {
        await Promise.race([
          document.fonts.ready,
          new Promise<void>((r) => setTimeout(r, 2500)),
        ]);
      }
    } catch {
      /* ignore — capture anyway */
    }
  }

  private async saveHtmlAsPdf(
    element: HTMLElement,
    filename: string,
    margin: number | number[] = 0
  ): Promise<void> {
    await this.waitForFonts();

    // Shape in the main document BEFORE html2pdf/html2canvas.
    // onclone alone is unreliable: html2pdf clones first, then html2canvas
    // clones into an iframe — TreeWalker/fonts often miss Arabic there.
    const wrap = document.createElement('div');
    wrap.setAttribute('dir', 'ltr');
    const widthPx = Math.max(element.offsetWidth || 0, 794);
    wrap.style.cssText =
      `position:fixed;left:0;top:0;width:${widthPx}px;background:#fff;` +
      'opacity:0.01;pointer-events:none;z-index:2147483646;';

    const clone = element.cloneNode(true) as HTMLElement;
    clone.style.setProperty('width', `${widthPx}px`);
    clone.style.setProperty('max-width', `${widthPx}px`);
    wrap.appendChild(clone);
    document.body.appendChild(wrap);

    this.shapeArabicInRoot(clone);
    clone.setAttribute('data-ar-shaped', '1');

    const options = {
      margin,
      filename,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: {
        scale: 2,
        useCORS: true,
        logging: false,
        allowTaint: true,
        backgroundColor: '#ffffff',
        scrollX: 0,
        scrollY: 0,
        foreignObjectRendering: false,
        onclone: (clonedDoc: Document, clonedEl: HTMLElement) => {
          const root = clonedEl || clonedDoc.body;
          if (!root) {
            return;
          }
          // Avoid double-shaping (breaks visual order). Only reinforce styles.
          const already =
            (root instanceof HTMLElement && root.getAttribute('data-ar-shaped') === '1') ||
            !!root.querySelector?.('[data-ar-shaped="1"]');
          if (already) {
            this.lockShapedArabicStyles(root);
          } else {
            this.shapeArabicInRoot(root);
          }
        },
      },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      pagebreak: { mode: ['css', 'legacy'] },
    };

    try {
      const html2pdf = await this.loadHtml2Pdf();
      const worker = html2pdf();

      const ua = navigator.userAgent || '';
      const isIOS =
        /iPad|iPhone|iPod/.test(ua) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

      if (isIOS) {
        const blob = await new Promise<Blob>((resolve, reject) => {
          worker
            .set(options)
            .from(clone)
            .toPdf()
            .output('blob')
            .then(
              (b: Blob) => resolve(b),
              (err: unknown) => reject(err)
            );
        });
        this.triggerBlobDownload(blob, filename);
        return;
      }

      await worker.set(options).from(clone).save();
    } finally {
      wrap.remove();
    }
  }

  /** Blob download that works more reliably on mobile (esp. iOS/Android WebView). */
  private triggerBlobDownload(blob: Blob, filename: string): void {
    if (!blob || !(blob instanceof Blob) || blob.size < 100) {
      throw new Error('Generated PDF is empty');
    }
    const url = URL.createObjectURL(blob);
    const ua = navigator.userAgent || '';
    const isIOS =
      /iPad|iPhone|iPod/.test(ua) ||
      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    if (isIOS) {
      const opened = window.open(url, '_blank');
      if (!opened) {
        const a = document.createElement('a');
        a.href = url;
        a.target = '_blank';
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
      }
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
      return;
    }

    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 4000);
  }

  private buildOffer1Html(offer: any): string {
    const cats: any[] = offer.category || [];
    const showOld = cats.some((c) => Number(c?.old_category_price) > 0);
    const rows = cats
      .map(
        (c) => `
      <tr>
        <td class="quotation-desc-ar" dir="auto" style="padding:8px;border-bottom:1px solid #eee;text-align:start;unicode-bidi:plaintext;writing-mode:horizontal-tb;word-break:normal;overflow-wrap:break-word;letter-spacing:0;line-height:1.45;font-family:'Segoe UI',Tahoma,'Traditional Arabic',Arial,sans-serif;"><bdi>${this.esc(c?.category_name)}</bdi></td>
        <td style="padding:8px;border-bottom:1px solid #eee;text-align:center;" dir="ltr">${this.fmt(c?.category_quantity)}</td>
        ${showOld ? `<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;" dir="ltr">${this.fmt(c?.old_category_price)}</td>` : ''}
        <td style="padding:8px;border-bottom:1px solid #eee;text-align:center;" dir="ltr">${this.fmt(c?.new_category_price)}</td>
        <td style="padding:8px;border-bottom:1px solid #eee;text-align:center;" dir="ltr">${this.fmt(c?.total_price)}</td>
      </tr>`
      )
      .join('');

    const logo = new URL('assets/images/logowhite.png', window.location.href).href;

    return `
      <div style="font-family:Cairo,Montserrat,Arial,sans-serif;width:100%;background:#fff;">
        <div style="background:#82225e;color:#fff;padding:20px 40px;">
          <div style="display:flex;justify-content:space-between;align-items:flex-end;">
            <img src="${logo}" width="100" alt="logo">
            <h3 style="font-size:16px;margin:0;">Quotation #${this.esc(offer.id)}</h3>
          </div>
          <hr style="border-color:rgba(255,255,255,.35);margin:12px 0;">
          <div style="display:flex;justify-content:space-between;gap:16px;">
            <div>
              ${offer?.customer_company?.name ? `<div>Company : ${this.esc(offer.customer_company.name)}</div>` : ''}
              ${offer?.quote ? `<div>Quote for : ${this.esc(offer.quote)}</div>` : ''}
              ${offer?.contact_person ? `<div>Contact Person : ${this.esc(offer.contact_person)}</div>` : ''}
            </div>
            <div>
              <div>Date : ${this.esc(offer.dateFrom)}</div>
              ${offer.dateFrom !== offer.dateTo ? `<div>Valid until : ${this.esc(offer.dateTo)}</div>` : ''}
            </div>
          </div>
        </div>
        <div style="padding:24px 40px;">
          <table style="width:100%;border-collapse:collapse;margin-top:40px;">
            <thead>
              <tr style="background:#82225e;color:#fff;">
                <th style="padding:10px;">DESCRIPTION</th>
                <th style="padding:10px;">QUANTITY</th>
                ${showOld ? '<th style="padding:10px;">PRICE Before Discount</th>' : ''}
                <th style="padding:10px;">PRICE After Discount</th>
                <th style="padding:10px;">TOTAL After Discount</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
          <div style="margin-top:24px;max-width:320px;margin-left:auto;">
            <div style="display:flex;justify-content:space-between;"><span>Subtotal :</span><span dir="ltr">${this.fmt(offer.subtotal)}</span></div>
            ${Number(offer.transportation) > 0 ? `<div style="display:flex;justify-content:space-between;"><span>Transportation :</span><span dir="ltr">${this.fmt(offer.transportation)}</span></div>` : ''}
            ${Number(offer.vat) > 0 ? `<div style="display:flex;justify-content:space-between;"><span>VAT :</span><span dir="ltr">${this.fmt(offer.vat)}</span></div>` : ''}
            <hr>
            <div style="display:flex;justify-content:space-between;font-weight:700;"><span>Total :</span><span dir="ltr">${this.fmt(offer.total)}</span></div>
          </div>
          ${
            offer.note
              ? `<div style="margin-top:20px;border:1px solid #ccc;padding:10px;"><strong>Note :</strong><p class="quotation-note-text" dir="auto" style="margin:6px 0 0;unicode-bidi:plaintext;writing-mode:horizontal-tb;letter-spacing:0;word-break:normal;overflow-wrap:break-word;font-family:'Segoe UI',Tahoma,'Traditional Arabic',Arial,sans-serif;"><bdi>${this.esc(offer.note)}</bdi></p></div>`
              : ''
          }
        </div>
        <div style="background:#82225e;color:#fff;padding:28px 40px;margin-top:20px;">
          <p style="font-size:13px;line-height:1.5;">Thank you for considering our products. please let us know if you have any questions or if there is anything else we can do to assist you. We look forward to working with you soon.</p>
          <div style="display:flex;justify-content:space-between;gap:12px;margin-top:16px;font-size:13px;">
            <div><strong>Website</strong><div>www.magalis-egypt.com</div></div>
            <div><strong>Telephone</strong><div>${this.esc(offer.phone_number || '')}</div></div>
            <div><strong>Email</strong><div>${this.esc(offer.email || '')}</div></div>
          </div>
        </div>
      </div>
    `;
  }

  private buildOffer2Html(offer: any): string {
    const cats: any[] = offer.category || [];
    const showOld = cats.some((c) => Number(c?.old_category_price) > 0);
    const imgBase = environment.imgUrl || '';
    const logo = new URL('assets/images/logoo.png', window.location.href).href;

    const detailRows = cats
      .map((c) => {
        const img = c?.category_image ? `${imgBase}${c.category_image}` : '';
        return `
        <tr>
          <td class="quotation-desc-ar" dir="auto" style="border:1px solid #000;padding:8px;text-align:start;unicode-bidi:plaintext;writing-mode:horizontal-tb;word-break:normal;overflow-wrap:break-word;letter-spacing:0;line-height:1.45;font-family:'Segoe UI',Tahoma,'Traditional Arabic',Arial,sans-serif;"><bdi>${this.esc(c?.category_name)}</bdi></td>
          ${showOld ? `<td style="border:1px solid #000;padding:8px;" dir="ltr">${this.fmt(c?.old_category_price)}</td>` : ''}
          <td style="border:1px solid #000;padding:8px;" dir="ltr">${this.fmt(c?.new_category_price)}</td>
          <td class="quotation-desc-ar" dir="auto" style="border:1px solid #000;padding:8px;text-align:start;unicode-bidi:plaintext;writing-mode:horizontal-tb;word-break:normal;overflow-wrap:break-word;letter-spacing:0;line-height:1.45;font-family:'Segoe UI',Tahoma,'Traditional Arabic',Arial,sans-serif;">${c?.description || ''}</td>
          <td style="border:1px solid #000;padding:8px;width:35%;">${img ? `<img src="${img}" style="width:100%;max-height:280px;object-fit:contain;" crossorigin="anonymous">` : ''}</td>
        </tr>`;
      })
      .join('');

    const summaryRows = cats
      .map(
        (c) => `
      <tr>
        <td class="quotation-desc-ar" dir="auto" style="border:1px solid #000;padding:6px;text-align:start;unicode-bidi:plaintext;writing-mode:horizontal-tb;word-break:normal;overflow-wrap:break-word;letter-spacing:0;line-height:1.45;font-family:'Segoe UI',Tahoma,'Traditional Arabic',Arial,sans-serif;"><bdi>${this.esc(c?.category_name)}</bdi></td>
        <td style="border:1px solid #000;padding:6px;" dir="ltr">${this.fmt(c?.new_category_price)}</td>
        <td style="border:1px solid #000;padding:6px;" dir="ltr">${this.fmt(c?.category_quantity)}</td>
        <td style="border:1px solid #000;padding:6px;" dir="ltr">${this.fmt(c?.total_price)}</td>
      </tr>`
      )
      .join('');

    return `
      <div style="font-family:Cairo,Montserrat,Arial,sans-serif;padding:28px;background:#fff;">
        <img src="${logo}" width="220" alt="logo">
        <div style="margin-top:28px;">
          <div>Date : ${this.esc(offer.dateFrom)}</div>
          <div>Valid till : ${this.esc(offer.dateTo)}</div>
          ${offer?.customer_company?.name ? `<div>Company : ${this.esc(offer.customer_company.name)}</div>` : ''}
          ${offer?.quote ? `<div>Quote for : ${this.esc(offer.quote)}</div>` : ''}
          ${offer?.contact_person ? `<div>Contact Person : ${this.esc(offer.contact_person)}</div>` : ''}
          ${offer.title ? `<div>This quotation for ${this.esc(offer.title)}</div>` : ''}
        </div>
        <table style="width:100%;border-collapse:collapse;margin-top:24px;font-size:12px;">
          <tr>
            <td style="border:1px solid #000;padding:6px;">Product</td>
            ${showOld ? '<td style="border:1px solid #000;padding:6px;">Price Before Discount</td>' : ''}
            <td style="border:1px solid #000;padding:6px;">Price</td>
            <td style="border:1px solid #000;padding:6px;">Description</td>
            <td style="border:1px solid #000;padding:6px;"></td>
          </tr>
          ${detailRows}
        </table>
        <table style="width:75%;border-collapse:collapse;margin-top:24px;font-size:12px;">
          <tr>
            <td style="border:1px solid #000;padding:6px;">Product</td>
            <td style="border:1px solid #000;padding:6px;">Price for unit</td>
            <td style="border:1px solid #000;padding:6px;">Quantity</td>
            <td style="border:1px solid #000;padding:6px;">Total Price</td>
          </tr>
          ${summaryRows}
          <tr><td colspan="2" style="border:1px solid #000;padding:6px;">Sub</td><td colspan="2" style="border:1px solid #000;padding:6px;" dir="ltr">${this.fmt(offer.subtotal)}</td></tr>
          <tr><td colspan="2" style="border:1px solid #000;padding:6px;">Vat</td><td colspan="2" style="border:1px solid #000;padding:6px;" dir="ltr">${this.fmt(offer.vat)}</td></tr>
          ${Number(offer.transportation) > 0 ? `<tr><td colspan="2" style="border:1px solid #000;padding:6px;">Transportation</td><td colspan="2" style="border:1px solid #000;padding:6px;" dir="ltr">${this.fmt(offer.transportation)}</td></tr>` : ''}
          <tr><td colspan="2" style="border:1px solid #000;padding:6px;">Net price</td><td colspan="2" style="border:1px solid #000;padding:6px;" dir="ltr">${this.fmt(offer.total)}</td></tr>
        </table>
        ${
          offer.note
            ? `<div style="margin-top:16px;border:1px solid #000;padding:10px;"><strong>Note :</strong><p class="quotation-note-text" dir="auto" style="unicode-bidi:plaintext;writing-mode:horizontal-tb;letter-spacing:0;word-break:normal;overflow-wrap:break-word;font-family:'Segoe UI',Tahoma,'Traditional Arabic',Arial,sans-serif;"><bdi>${this.esc(offer.note)}</bdi></p></div>`
            : ''
        }
      </div>
    `;
  }

  private waitForImages(root: HTMLElement): Promise<void> {
    const images = Array.from(root.querySelectorAll('img'));
    if (!images.length) {
      return Promise.resolve();
    }
    return Promise.all(
      images.map(
        (img) =>
          new Promise<void>((resolve) => {
            if (img.complete) {
              resolve();
              return;
            }
            img.addEventListener('load', () => resolve(), { once: true });
            img.addEventListener('error', () => resolve(), { once: true });
          })
      )
    ).then(() => undefined);
  }

  private resolveClientName(offer: any): string {
    return String(
      offer?.customer_company?.name || offer?.quote || offer?.contact_person || ''
    ).trim();
  }

  /** Prefer quotation / work date, then validity end date. */
  private resolveOfferDate(offer: any): string {
    return String(offer?.dateFrom || offer?.dateTo || '').trim();
  }

  /** Safe download name: Client-Date.pdf; falls back to Quotation-{id}.pdf */
  private quotationFileName(
    clientName: string | null | undefined,
    offerId: number | string,
    offerDate?: string | null
  ): string {
    const safe = this.safeFileName(String(clientName || '').trim());
    const base = safe || `Quotation-${offerId}`;
    const datePart = this.safeDateForFileName(offerDate);
    return datePart ? `${base}-${datePart}.pdf` : `${base}.pdf`;
  }

  private safeDateForFileName(date: string | null | undefined): string {
    const raw = String(date || '').trim();
    if (!raw) {
      return '';
    }
    // Keep digits and separators; normalize to dashes for filesystem safety.
    return raw
      .replace(/[\\/:*?"<>|]+/g, '-')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .slice(0, 32);
  }

  private safeFileName(name: string): string {
    return (name || '')
      .trim()
      .replace(/[^\w\u0600-\u06FF\- ]+/g, '')
      .replace(/\s+/g, '-')
      .slice(0, 80);
  }

  private esc(value: unknown): string {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /**
   * Shape Arabic for LTR html2canvas (join + visual order).
   * Uses naqqash shapeArabicVisual — required because canvas has no bidi engine.
   */
  private shapeForPdf(value: unknown): string {
    const text = String(value ?? '');
    if (!text || !ARABIC_RE.test(text)) {
      return text;
    }
    try {
      return shapeArabicVisual(text);
    } catch {
      return text;
    }
  }

  /**
   * Shape every Arabic text node under root (clone-only).
   * html2canvas paints LTR without bidi — visual presentation forms are required.
   */
  private shapeArabicInRoot(root: ParentNode | null | undefined): void {
    if (!root) {
      return;
    }
    const touched = new Set<HTMLElement>();
    try {
      this.walkTextNodes(root, (node) => {
        const raw = node.nodeValue;
        if (!raw || !ARABIC_RE.test(raw)) {
          return;
        }
        // Skip nodes that already use Presentation Forms (avoid double-shaping).
        if (/[\uFB50-\uFDFF\uFE70-\uFEFF]/.test(raw)) {
          const parent = node.parentElement;
          if (parent) {
            touched.add(parent);
            const cell = parent.closest('.quotation-desc-ar, .quotation-note-text');
            if (cell instanceof HTMLElement) {
              touched.add(cell);
            }
          }
          return;
        }
        const parent = node.parentElement;
        if (parent && /^(SCRIPT|STYLE)$/i.test(parent.tagName)) {
          return;
        }
        node.nodeValue = this.shapeForPdf(raw);
        if (parent) {
          touched.add(parent);
          const cell = parent.closest('.quotation-desc-ar, .quotation-note-text');
          if (cell instanceof HTMLElement) {
            touched.add(cell);
          }
        }
      });
    } catch (err) {
      console.error('Arabic PDF shaping failed:', err);
      return;
    }
    this.applyShapedArabicStyles(touched);
    if (root instanceof HTMLElement) {
      root.setAttribute('data-ar-shaped', '1');
    }
  }

  /** Re-apply LTR + Forms-B font locks after html2canvas re-clones shaped DOM. */
  private lockShapedArabicStyles(root: ParentNode): void {
    const touched = new Set<HTMLElement>();
    root.querySelectorAll?.('.quotation-desc-ar, .quotation-note-text, bdi').forEach((el) => {
      if (el instanceof HTMLElement) {
        touched.add(el);
      }
    });
    this.walkTextNodes(root, (node) => {
      if (!node.nodeValue || !ARABIC_RE.test(node.nodeValue)) {
        return;
      }
      const parent = node.parentElement;
      if (parent) {
        touched.add(parent);
      }
    });
    this.applyShapedArabicStyles(touched);
  }

  private applyShapedArabicStyles(elements: Set<HTMLElement>): void {
    elements.forEach((el) => {
      el.style.setProperty('direction', 'ltr', 'important');
      el.style.setProperty('unicode-bidi', 'bidi-override', 'important');
      el.style.setProperty('writing-mode', 'horizontal-tb', 'important');
      el.style.setProperty('text-align', 'left', 'important');
      el.style.setProperty('letter-spacing', 'normal', 'important');
      el.style.setProperty('word-spacing', 'normal', 'important');
      el.style.setProperty('word-break', 'normal', 'important');
      el.style.setProperty('font-family', PDF_ARABIC_FONT, 'important');
      el.style.setProperty('font-synthesis', 'none', 'important');
      el.setAttribute('dir', 'ltr');
    });
  }

  private walkTextNodes(
    root: Node,
    visit: (node: Text) => void
  ): void {
    // html2canvas clones into an iframe — must use that document's TreeWalker.
    const doc = root.ownerDocument;
    if (!doc) {
      return;
    }
    const walker = doc.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let node = walker.nextNode();
    while (node) {
      visit(node as Text);
      node = walker.nextNode();
    }
  }

  private fmt(value: unknown): string {
    const n = Number(value);
    if (!Number.isFinite(n)) {
      return '0';
    }
    return n.toLocaleString('en-US', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    });
  }

  private fmtDate(value: unknown): string {
    if (!value) {
      return '—';
    }
    const d = new Date(String(value));
    if (Number.isNaN(d.getTime())) {
      return String(value).slice(0, 10);
    }
    return d.toISOString().slice(0, 10);
  }
}
