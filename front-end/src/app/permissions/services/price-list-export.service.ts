import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { environment } from 'src/env/env';
import { PriceListItem } from './price-list.service';

type Html2PdfFactory = () => {
  set: (options: unknown) => {
    from: (element: HTMLElement) => {
      save: () => Promise<void>;
    };
  };
};

@Injectable({
  providedIn: 'root',
})
export class PriceListExportService {
  constructor(private http: HttpClient) {}

  private async loadHtml2Pdf(): Promise<Html2PdfFactory> {
    const mod: any = await import('html2pdf.js');
    const factory = mod?.default ?? mod;
    if (typeof factory !== 'function') {
      throw new Error('html2pdf.js failed to load');
    }
    return factory as Html2PdfFactory;
  }

  async downloadPdf(title: string, items: PriceListItem[]): Promise<void> {
    const host = document.createElement('div');
    host.setAttribute('dir', 'ltr');
    host.style.cssText =
      'position:fixed;left:0;top:0;width:210mm;height:297mm;background:#fff;opacity:0.01;pointer-events:none;z-index:2147483646;overflow:hidden;';
    host.innerHTML = await this.buildHtml(title, items || []);
    document.body.appendChild(host);

    try {
      await this.waitForFonts();
      await this.waitForImages(host);
      const html2pdf = await this.loadHtml2Pdf();
      const safeName = this.safeFileName(title || 'price-list');
      await html2pdf()
        .set({
          margin: 0,
          filename: `${safeName}.pdf`,
          image: { type: 'jpeg', quality: 0.95 },
          html2canvas: {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            logging: false,
            backgroundColor: '#ffffff',
            width: host.offsetWidth,
            height: host.offsetHeight,
            windowWidth: host.scrollWidth,
            windowHeight: host.scrollHeight,
          },
          jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
          pagebreak: { mode: ['avoid-all'] },
        })
        .from(host)
        .save();
    } finally {
      host.remove();
    }
  }

  async openPrintPreview(title: string, items: PriceListItem[]): Promise<void> {
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      await this.downloadPdf(title, items);
      return;
    }

    printWindow.document.write(`
      <!DOCTYPE html>
      <html lang="en" dir="ltr">
      <head>
        <meta charset="UTF-8">
        <title>${this.esc(title || 'Price list')}</title>
        <style>
          body { margin:0; padding:70px 20px 24px; background:#e8e8e8; font-family:Arial,Helvetica,sans-serif; }
          .print-toolbar {
            position:fixed; top:0; left:0; right:0; height:54px;
            background:#82225e; display:flex; align-items:center; gap:10px;
            padding:0 24px; z-index:9999; color:#fff; font-weight:600;
          }
          .print-toolbar span { flex:1; }
          .toolbar-btn {
            background:#fff; color:#82225e; border:none; padding:7px 18px;
            border-radius:6px; font-size:14px; cursor:pointer; font-weight:700;
          }
          .toolbar-btn.download { background:#6b1a4c; color:#fff; border:2px solid #fff; }
        </style>
      </head>
      <body>
        <div class="print-toolbar">
          <span>جاري تحضير الصور للطباعة...</span>
        </div>
      </body>
      </html>
    `);
    printWindow.document.close();

    try {
      const safeTitle = this.esc(title || 'Price list');
      const content = await this.buildHtml(title, items || []);
      const fileName = this.safeFileName(title || 'price-list');

      printWindow.document.open();
      printWindow.document.write(`
      <!DOCTYPE html>
      <html lang="en" dir="ltr">
      <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>${safeTitle}</title>
        <style>
          * { box-sizing: border-box; }
          body {
            margin: 0;
            padding: 70px 20px 24px;
            background: #e8e8e8;
            font-family: Arial, Helvetica, sans-serif;
          }
          .print-toolbar {
            position: fixed; top: 0; left: 0; right: 0; height: 54px;
            background: #82225e; display: flex; align-items: center; gap: 10px;
            padding: 0 24px; z-index: 9999; box-shadow: 0 2px 8px rgba(0,0,0,0.25);
          }
          .print-toolbar span { color: #fff; font-size: 15px; font-weight: 600; flex: 1; }
          .toolbar-btn {
            background: #fff; color: #82225e; border: none; padding: 7px 18px;
            border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 700;
          }
          .toolbar-btn.download { background: #6b1a4c; color: #fff; border: 2px solid #fff; }
          .toolbar-btn:disabled { opacity: 0.65; cursor: wait; }
          .page-wrapper { display: flex; justify-content: center; }
          #capture {
            width: 210mm; height: 297mm; background: #fff;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18); overflow: hidden;
          }
          @page { size: A4; margin: 0; }
          @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            body { margin: 0; padding: 0; background: #fff; }
            .print-toolbar { display: none !important; }
            #capture { box-shadow: none; width: 210mm; height: 297mm; }
          }
        </style>
      </head>
      <body>
        <div class="print-toolbar">
          <span>${safeTitle}</span>
          <button type="button" class="toolbar-btn download" id="btn-download-pdf">تحميل PDF</button>
          <button type="button" class="toolbar-btn" onclick="window.print()">طباعة</button>
        </div>
        <div class="page-wrapper">
          <div id="capture">${content}</div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"><\/script>
        <script>
          (function () {
            var btn = document.getElementById('btn-download-pdf');
            if (!btn) return;
            btn.addEventListener('click', function () {
              var el = document.getElementById('capture');
              if (!el || typeof html2pdf === 'undefined') { window.print(); return; }
              btn.disabled = true;
              btn.textContent = 'جاري التحميل...';
              html2pdf().set({
                margin: 0,
                filename: '${fileName}.pdf',
                image: { type: 'jpeg', quality: 0.95 },
                html2canvas: { scale: 2, useCORS: true, allowTaint: true, logging: false, backgroundColor: '#ffffff' },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['avoid-all'] }
              }).from(el).save().then(function () {
                btn.disabled = false;
                btn.textContent = 'تحميل PDF';
              }).catch(function () {
                btn.disabled = false;
                btn.textContent = 'تحميل PDF';
                window.print();
              });
            });
          })();
        <\/script>
      </body>
      </html>
    `);
      printWindow.document.close();
    } catch (e) {
      printWindow.document.body.innerHTML =
        '<div style="padding:24px;font-family:Arial;direction:rtl">تعذر تجهيز الصور للطباعة.</div>';
      throw e;
    }
  }

  private async buildHtml(title: string, items: PriceListItem[]): Promise<string> {
    const count = Math.max(items.length, 1);
    const rowHeightMm = Math.max(14, Math.min(28, (297 - 34) / count));
    const thumbMm = Math.min(22, Math.max(12, rowHeightMm - 3));

    const logoUrl = await this.toDataUrlFromSameOrigin(this.appAssetUrl('assets/images/logowhite.png'));
    const logoHtml = logoUrl
      ? `<img class="pl-pdf-logo" src="${this.escAttr(logoUrl)}" alt="MAGALIS">`
      : `<div class="pl-pdf-logo-text">MAGALIS</div>`;

    const rowsHtml: string[] = [];
    for (const item of items) {
      const p1Src = item.photo1 ? await this.toDataUrlFromPhoto(item.photo1) : null;
      const p2Src = item.photo2 ? await this.toDataUrlFromPhoto(item.photo2) : null;
      const p1 = p1Src
        ? this.imgTag(p1Src, item.product_name + ' 1', thumbMm)
        : `<div class="pl-empty-photo" style="width:${thumbMm}mm;height:${thumbMm}mm;"></div>`;
      const p2 = p2Src
        ? this.imgTag(p2Src, item.product_name + ' 2', thumbMm)
        : `<div class="pl-empty-photo" style="width:${thumbMm}mm;height:${thumbMm}mm;"></div>`;
      const price = this.formatPrice(item.price, item.currency);
      rowsHtml.push(`
        <tr style="height:${rowHeightMm}mm;">
          <td class="c-code">${this.esc(item.code)}</td>
          <td class="c-name">${this.esc(item.product_name)}</td>
          <td class="c-price"><span class="price-chip">${this.esc(price)}</span></td>
          <td class="c-photo">${p1}</td>
          <td class="c-photo">${p2}</td>
        </tr>
      `);
    }

    const emptyRow = `
      <tr style="height:28mm;">
        <td colspan="5" style="text-align:center;color:#888;font-size:12px;">No products yet</td>
      </tr>
    `;

    return `
      <div class="pl-pdf-root" style="width:210mm;height:297mm;overflow:hidden;background:#fff;font-family:Arial,Helvetica,sans-serif;color:#222;">
        <style>
          .pl-pdf-root * { box-sizing: border-box; }
          .pl-pdf-header {
            background: #82225e; color: #fff; padding: 7mm 6mm 0;
          }
          .pl-pdf-logo { height: 8mm; width: auto; display: block; margin-bottom: 2.5mm; }
          .pl-pdf-logo-text {
            font-size: 12pt; font-weight: 700; letter-spacing: 1px; margin-bottom: 2.5mm;
          }
          .pl-pdf-title {
            margin: 0 0 4mm; font-size: 14pt; font-weight: 600; letter-spacing: 0.2px;
          }
          .pl-pdf-table {
            width: 100%; border-collapse: collapse; table-layout: fixed;
          }
          .pl-pdf-table th {
            background: #82225e; color: #fff; font-weight: 600; font-size: 9pt;
            text-align: center; padding: 2mm 1mm; border: 0.3mm solid rgba(255,255,255,0.22);
            text-transform: uppercase; letter-spacing: 0.3px;
          }
          .pl-pdf-table td {
            border: 0.3mm solid #ddd; text-align: center; vertical-align: middle;
            font-size: 9.5pt; padding: 1mm; overflow: hidden;
          }
          .pl-pdf-table tr:nth-child(even) td { background: #faf8f9; }
          .pl-pdf-table .c-code { width: 12%; font-weight: 700; color: #82225e; }
          .pl-pdf-table .c-name { width: 28%; padding: 1mm 2mm; word-break: break-word; }
          .pl-pdf-table .c-price { width: 16%; }
          .pl-pdf-table .c-photo { width: 22%; background: #fff; }
          .price-chip {
            display: inline-block; padding: 0.8mm 2mm; border-radius: 8mm;
            background: #f7eef3; color: #6a194c; font-weight: 700; font-size: 8.5pt;
            border: 0.25mm solid #ead5e0;
          }
          .pl-pdf-table .c-photo img,
          .pl-empty-photo {
            width: ${thumbMm}mm; height: ${thumbMm}mm; object-fit: cover;
            display: block; margin: 0 auto; border-radius: 1.5mm;
            border: 0.25mm solid #d4ced2; background: #eee;
          }
        </style>
        <div class="pl-pdf-header">
          ${logoHtml}
          <h1 class="pl-pdf-title">${this.esc(title || 'Price list')}</h1>
          <table class="pl-pdf-table" style="margin:0 -6mm; width:calc(100% + 12mm);">
            <thead>
              <tr>
                <th class="c-code">Code</th>
                <th class="c-name">Product Name</th>
                <th class="c-price">Price</th>
                <th class="c-photo">Photo 1</th>
                <th class="c-photo">Photo 2</th>
              </tr>
            </thead>
          </table>
        </div>
        <table class="pl-pdf-table">
          <tbody>
            ${rowsHtml.join('') || emptyRow}
          </tbody>
        </table>
      </div>
    `;
  }

  private appAssetUrl(path: string): string {
    const clean = String(path || '').replace(/^\//, '');
    // استخدم base href للتطبيق وليس مسار الصفحة الحالية (مثل /dashboard/pricelist/1)
    try {
      return new URL(clean, document.baseURI || `${window.location.origin}/`).href;
    } catch {
      return `${window.location.origin}/${clean}`;
    }
  }

  private absolutePhotoUrl(filename: string): string {
    const safe = String(filename || '').trim().replace(/^.*[\\/]/, '');
    return safe ? `${environment.imgUrl}${safe}` : '';
  }

  /**
   * حمّل صورة المنتج عبر API (مع CORS) وحوّلها إلى data URL
   * حتى تظهر في about:blank وفي html2canvas بدون كسر بسبب crossorigin.
   * إن فشل التحويل نرجع الرابط المطلق (يكفي للطباعة العادية).
   */
  private async toDataUrlFromPhoto(filename: string): Promise<string | null> {
    const safe = String(filename || '').trim().replace(/^.*[\\/]/, '');
    if (!safe) {
      return null;
    }
    const absolute = this.absolutePhotoUrl(safe);

    try {
      const blob = await firstValueFrom(
        this.http.get(`${environment.Url}/price-list/photo/${encodeURIComponent(safe)}`, {
          responseType: 'blob',
          headers: { 'X-Skip-Global-Loading': '1' },
        })
      );
      if (blob && blob.size >= 32 && this.isImageBlob(blob)) {
        return await this.blobToDataUrl(blob, safe);
      }
    } catch {
      // continue to fallbacks
    }

    try {
      const res = await fetch(`${environment.Url}/price-list/photo/${encodeURIComponent(safe)}`, {
        mode: 'cors',
        credentials: 'omit',
      });
      if (res.ok) {
        const blob = await res.blob();
        if (blob && blob.size >= 32 && this.isImageBlob(blob)) {
          return await this.blobToDataUrl(blob, safe);
        }
      }
    } catch {
      // continue
    }

    // للمعاينة/الطباعة: الرابط العام يعمل بدون crossorigin
    return absolute || null;
  }

  private isImageBlob(blob: Blob): boolean {
    const type = String(blob.type || '').toLowerCase();
    if (type.startsWith('image/')) {
      return true;
    }
    // بعض السيرفرات ترجع application/octet-stream للصور
    return !type || type === 'application/octet-stream';
  }

  private async toDataUrlFromSameOrigin(url: string): Promise<string> {
    try {
      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) {
        throw new Error('fetch failed');
      }
      const blob = await res.blob();
      if (this.isImageBlob(blob)) {
        return await this.blobToDataUrl(blob);
      }
      return '';
    } catch {
      return '';
    }
  }

  private async blobToDataUrl(blob: Blob, filename?: string): Promise<string> {
    let typed = blob;
    const ext = String(filename || '')
      .split('.')
      .pop()
      ?.toLowerCase();
    const byExt: Record<string, string> = {
      jpg: 'image/jpeg',
      jpeg: 'image/jpeg',
      png: 'image/png',
      gif: 'image/gif',
      webp: 'image/webp',
      bmp: 'image/bmp',
      svg: 'image/svg+xml',
    };
    const hint = ext ? byExt[ext] : '';
    if (hint && blob.type !== hint) {
      typed = new Blob([await blob.arrayBuffer()], { type: hint });
    }

    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result || ''));
      reader.onerror = () => reject(reader.error);
      reader.readAsDataURL(typed);
    });
  }

  private imgTag(src: string, alt: string, sizeMm: number): string {
    // بدون crossorigin حتى لا تُكسر الصور في about:blank عند غياب CORS على /images
    return `<img src="${this.escAttr(src)}" alt="${this.escAttr(alt)}" style="width:${sizeMm}mm;height:${sizeMm}mm;object-fit:cover;display:block;margin:0 auto;border-radius:1.5mm;border:0.25mm solid #d4ced2;">`;
  }

  private formatPrice(price: number | string | null | undefined, currency?: string | null): string {
    const n = Number(price);
    const amount = !Number.isFinite(n)
      ? ''
      : (Number.isInteger(n) ? String(n) : n.toFixed(2));
    const cur = String(currency || 'EGP').trim().toUpperCase() || 'EGP';
    return amount ? `${amount} ${cur}` : cur;
  }

  private safeFileName(title: string): string {
    return (title || 'price-list')
      .trim()
      .replace(/[^\w\u0600-\u06FF\- ]+/g, '')
      .replace(/\s+/g, '-')
      .slice(0, 80) || 'price-list';
  }

  private esc(value: unknown): string {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  private escAttr(value: unknown): string {
    return this.esc(value).replace(/'/g, '&#39;');
  }

  private waitForFonts(): Promise<void> {
    const fonts = (document as any).fonts;
    if (fonts?.ready) {
      return fonts.ready.then(() => undefined).catch(() => undefined);
    }
    return Promise.resolve();
  }

  private waitForImages(root: HTMLElement): Promise<void> {
    const imgs = Array.from(root.querySelectorAll('img'));
    if (!imgs.length) {
      return Promise.resolve();
    }
    return Promise.all(
      imgs.map(
        (img) =>
          new Promise<void>((resolve) => {
            if (img.complete && img.naturalWidth > 0) {
              resolve();
              return;
            }
            img.onload = () => resolve();
            img.onerror = () => resolve();
            setTimeout(() => resolve(), 8000);
          })
      )
    ).then(() => undefined);
  }
}
