import { Injectable } from '@angular/core';
import { formatDdMmYyyy, isoStringToDate } from 'src/app/shared/date/date-utils';

export interface OrderInvoicePrintOptions {
  showInvoiceDate?: boolean;
  size?: 'A4' | 'A3' | string;
}

@Injectable({
  providedIn: 'root',
})
export class OrderInvoicePrintService {
  /** Opens a print window with invoice HTML — fast native print, one invoice per page. */
  openPrintWindow(orders: any[], options: OrderInvoicePrintOptions = {}): boolean {
    if (!orders?.length) {
      return false;
    }

    const showInvoiceDate = options.showInvoiceDate !== false;
    const size = options.size || 'A4';
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      return false;
    }

    const html = this.buildDocument(orders, showInvoiceDate, size);
    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();

    void this.triggerPrintWhenReady(printWindow);

    return true;
  }

  private async triggerPrintWhenReady(printWindow: Window): Promise<void> {
    let printed = false;
    const triggerPrint = (): void => {
      if (printed || printWindow.closed) {
        return;
      }
      printed = true;
      printWindow.focus();
      printWindow.print();
    };

    try {
      await this.waitForDocumentReady(printWindow);
      await this.waitForImages(printWindow.document);
      triggerPrint();
    } catch (error) {
      console.error('Error preparing invoice print:', error);
      this.showPrintError(printWindow);
      return;
    }

    setTimeout(triggerPrint, 400);
  }

  private buildDocument(orders: any[], showInvoiceDate: boolean, size: string): string {
    const pages = this.buildInvoicePages(orders, showInvoiceDate, size);

    return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Invoice</title>
  <style>${this.printStyles(size)}</style>
</head>
<body dir="ltr">
  <div id="capture">${pages}</div>
</body>
</html>`;
  }

  private waitForDocumentReady(targetWindow: Window): Promise<void> {
    return new Promise((resolve) => {
      if (targetWindow.document.readyState === 'complete') {
        resolve();
        return;
      }

      targetWindow.addEventListener('load', () => resolve(), { once: true });
      setTimeout(resolve, 300);
    });
  }

  private waitForImages(doc: Document): Promise<void> {
    const images = Array.from(doc.images).filter((img) => !img.complete);
    if (!images.length) {
      return Promise.resolve();
    }

    return Promise.all(
      images.map(
        (img) =>
          new Promise<void>((resolve) => {
            img.addEventListener('load', () => resolve(), { once: true });
            img.addEventListener('error', () => resolve(), { once: true });
          })
      )
    ).then(() => undefined);
  }

  private showPrintError(printWindow: Window): void {
    if (printWindow.closed) {
      return;
    }

    printWindow.document.open();
    printWindow.document.write(
      '<p style="font-family:sans-serif;padding:2rem;margin:0;color:#b00020;">تعذر تحضير الفاتورة للطباعة.</p>'
    );
    printWindow.document.close();
  }

  private buildInvoicePages(orders: any[], showInvoiceDate: boolean, size: string): string {
    return orders
      .map((order, index) =>
        this.buildInvoicePage(order, showInvoiceDate, size, index < orders.length - 1)
      )
      .join('');
  }

  private buildInvoicePage(order: any, showInvoiceDate: boolean, size: string, pageBreak: boolean): string {
    const pageHeight = size === 'A4' ? '1000px' : '800px';
    const address = order?.city
      ? `${this.escape(order?.governorate)} , ${this.escape(order?.city)} , ${this.escape(order?.address)}`
      : `${this.escape(order?.governorate)} , ${this.escape(order?.address)}`;
    const dateHtml = showInvoiceDate
      ? `<p class="mb-0 invoice-date">${this.escape(this.formatInvoiceDate(order?.order_date))}</p>`
      : '';
    const rows = (order?.order_products || []).map((item: any) => `
      <tr>
        <td>${this.escape(item?.category?.category_name)}</td>
        <td>${this.escape(item?.quantity)}</td>
        <td>${this.escape(this.formatNumber(item?.price))}</td>
        <td>${this.escape(this.formatNumber(item?.total_price))}</td>
      </tr>
    `).join('');
    const logoUrl = new URL('assets/images/iconmaga.png', window.location.href).href;

    return `
      <div class="invoice-page container${pageBreak ? ' invoice-page-break' : ''}" style="min-height:${pageHeight}">
        <div>
          <div class="header-row">
            <p class="invoice">Invoice</p>
            <div class="invoice-header-meta">
              <p class="mb-0">${this.escape(order?.id)}</p>
              ${dateHtml}
            </div>
          </div>

          <div class="logo-wrap">
            <img width="150" src="${logoUrl}" alt="">
          </div>

          <div class="row-between">
            <div>
              <span class="mr-1"> Invoice To</span>
              <span><input type="text" readonly value="${this.escapeAttr(order?.customer_name)}"></span>
            </div>
            <div>
              <span>Phone Number</span>
              <span><input type="text" readonly value="${this.escapeAttr(order?.customer_phone_1)}"></span>
            </div>
          </div>
          <br>

          <div class="row-between align-center">
            <span class="addr-label">Shipping Address</span>
            <span class="addr-value"><input type="text" readonly value="${this.escapeAttr(address)}"></span>
          </div>

          <div class="mt-4">
            <table class="table">
              <thead>
                <tr>
                  <th class="w-50"> Item Description</th>
                  <th>Qty</th>
                  <th> price per item</th>
                  <th> Amount </th>
                </tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
          </div>

          <div class="row-between footer">
            <div><p> Discount : ${this.escape(this.formatNumber(order?.discount))} </p></div>
            <div><p>Down payment : ${this.escape(this.formatNumber(order?.prepaid_amount))}</p></div>
          </div>
          <br>
          <div class="row-between footer">
            <div><p> Shipping Fee : ${this.escape(this.formatNumber(order?.shipping_cost))} </p></div>
            <div><p class="bg-invoice">Total Amount : ${this.escape(this.formatNumber(order?.net_total))} </p></div>
          </div>
        </div>

        <div class="bottom">
          <div class="footer2">
            <p>We Hope You Are Satisfied with tour purchase.</p>
            <p>Thank you for putting your trust and confidence in our company.</p>
          </div>
          <div class="row-between connect">
            <p>info@magalis-egypt.com</p>
            <p>+201118127345</p>
          </div>
        </div>
      </div>
    `;
  }

  private printStyles(size: string): string {
    return `
      * {
        box-sizing: border-box;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
      }
      html, body {
        margin: 0;
        font-family: Arial, sans-serif;
        color: #222;
      }
      p { font-size: 1.2rem; margin: 0 0 0.5rem; }
      .mb-0 { margin-bottom: 0 !important; }
      .mr-1 { margin-right: 0.25rem; }
      .container { padding: 3rem; }
      .invoice-page {
        position: relative;
        padding: 3rem;
      }
      .invoice-page-break {
        page-break-after: always;
        break-after: page;
      }
      .header-row {
        display: flex;
        justify-content: space-around;
        align-items: center;
      }
      .invoice {
        padding: 10px 40px;
        border-radius: 10px;
        background-color: #7b2869 !important;
        color: #ffffff !important;
      }
      .invoice-header-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
      }
      .invoice-date { font-size: 1rem; color: #333; }
      .logo-wrap { text-align: center; margin: 3rem 0; }
      .row-between {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
      }
      .align-center { align-items: center; }
      .addr-label { width: 9rem; flex: 0 0 auto; }
      .addr-value { width: 100%; }
      .mt-4 { margin-top: 1.5rem; }
      .table {
        width: 100%;
        border-collapse: collapse;
        text-align: center;
      }
      .table th, .table td {
        border: 1px solid #dee2e6;
        padding: 0.5rem;
        font-size: 0.8rem;
      }
      .table th {
        background-color: #7b2869 !important;
        color: #ffffff !important;
      }
      .w-50 { width: 50%; }
      .footer p {
        font-size: 1rem;
        border: 2px solid #7b2869;
        padding: 10px 0;
        width: 19rem;
        border-radius: 10px;
        text-align: center;
        background-color: #ffffff;
      }
      .bg-invoice {
        background-color: #7b2869 !important;
        color: #ffffff !important;
        font-weight: 600;
        border: 2px solid #7b2869 !important;
      }
      input {
        border: 2px solid #7b2869;
        margin-left: 10px;
        padding: 5px 10px;
        font-size: 12px;
        width: auto;
        background-color: #ffffff;
      }
      .addr-value input { width: 100%; }
      .bottom {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 20px;
        text-align: center;
      }
      .footer2 { margin-top: 2rem; }
      .connect p, .footer2 p {
        font-size: 0.8rem !important;
        margin: 0;
      }
      @media print {
        @page { size: ${size}; margin: 10mm; }
        .invoice-page-break { page-break-after: always; break-after: page; }
        .invoice,
        .bg-invoice,
        .table th {
          background-color: #7b2869 !important;
          color: #ffffff !important;
        }
      }
    `;
  }

  private formatInvoiceDate(value: unknown): string {
    return formatDdMmYyyy(isoStringToDate(String(value ?? '')));
  }

  private formatNumber(value: unknown): string {
    if (value === null || value === undefined || value === '') {
      return '';
    }
    const num = typeof value === 'number' ? value : Number(value);
    if (Number.isNaN(num)) {
      return String(value);
    }
    const formattedNumber = num.toLocaleString('en-US', {
      minimumFractionDigits: Number.isInteger(num) ? 0 : 1,
      maximumFractionDigits: 4,
    });
    return formattedNumber.replace(/(\.[0-9]*[1-9])0+$/, '$1');
  }

  private escape(value: unknown): string {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  private escapeAttr(value: unknown): string {
    return this.escape(value).replace(/"/g, '&quot;');
  }
}
