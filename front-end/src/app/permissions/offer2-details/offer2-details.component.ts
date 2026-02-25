import { Component, OnInit } from '@angular/core';
import { OfferService } from '../services/offer.service';
import { ActivatedRoute } from '@angular/router';
import { environment } from 'src/env/env';

@Component({
  selector: 'app-offer2-details',
  templateUrl: './offer2-details.component.html',
  styleUrls: ['./offer2-details.component.css']
})
export class Offer2DetailsComponent {
  offer: any = {};
  categories: any[] = [];
  imgUrl!: string;
  showOldPrice: boolean = false;

  constructor(private offerService: OfferService, private route: ActivatedRoute) {
    this.imgUrl = environment.imgUrl;

  }

  ngOnInit(): void {
    const id = this.route.snapshot.params['id'];
    this.offerService.getOfferById(id).subscribe((res: any) => {
      this.offer = res;
      console.log(res);
      let oldPrice = res.category.some(elm => elm.old_category_price > 0);
      if (oldPrice) {
        this.showOldPrice = true;
      }

      // this.offer.category_name = res.category[0].category_name;
      // this.offer.new_category_price = res.category[0].new_category_price;
      // this.offer.category_quantity = res.category[0].category_quantity;
      // this.offer.description = res.category[0].description;
      // this.offer.category_image = res.category[0].category_image;
    })

  }

  font: string = 'f-1'

  downloadPDF() {
    const element = document.getElementById('capture');
    if (!element) return;

    const printContent = element.innerHTML;
    const baseUrl = window.location.origin + '/';

    // Collect all styles (Angular component styles + global styles)
    const styleLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
      .map((el: any) => el.outerHTML).join('\n');
    const inlineStyles = Array.from(document.querySelectorAll('style'))
      .map((el: any) => el.outerHTML).join('\n');

    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      console.error('Could not open print window.');
      return;
    }

    printWindow.document.write(`
      <!DOCTYPE html>
      <html lang="ar" dir="ltr">
      <head>
        <meta charset="UTF-8">
        <base href="${baseUrl}">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Quotation #${this.offer?.id}</title>
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
          input { border: none; outline: none; }
          /* ---- toolbar ---- */
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
          .toolbar-btn.download { background: #82225e; color: #fff; border: 2px solid #fff; }
          .toolbar-btn.download:hover { background: #9e2b72; }
          /* ---- page ---- */
          .page-wrapper {
            display: flex;
            justify-content: center;
            padding-top: 20px;
            zoom: 1.3;
          }
          #capture {
            width: 213mm;
            min-height: 296mm;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0,0,0,0.18);
          }
          @page { size: A4; margin: 0; }
          @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            body { margin: 0; padding: 0; background: #fff; }
            .print-toolbar { display: none !important; }
            .page-wrapper { zoom: 1; padding-top: 0; }
            #capture { box-shadow: none; }
          }
        </style>
      </head>
      <body>
        <div class="print-toolbar">
          <span>🧾 عرض الأسعار #${this.offer?.id}</span>
          <button class="toolbar-btn" onclick="window.print()">🖨️ طباعة</button>
        </div>

        <div class="page-wrapper">
          <div id="capture" dir="ltr" class="border m-0 p-0">
            ${printContent}
          </div>
        </div>

        <script>
          function downloadFile() {
            const style = document.createElement('style');
            style.textContent = '@page { size: A4; margin: 0; } body { margin:0; padding:0; } .print-toolbar { display:none!important; }';
            document.head.appendChild(style);
            window.print();
            setTimeout(() => document.head.removeChild(style), 500);
          }
          document.fonts.ready.then(function() {
            window.print();
          });
        <\/script>
      </body>
      </html>
    `);
    printWindow.document.close();
  }

}