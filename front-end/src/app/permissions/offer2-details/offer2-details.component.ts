import { Component, OnInit } from '@angular/core';
import { OfferService } from '../services/offer.service';
import { ActivatedRoute } from '@angular/router';
import * as html2pdf from 'html2pdf.js';
import { saveAs } from 'file-saver';
import { environment } from 'src/env/env';

@Component({
  selector: 'app-offer2-details',
  templateUrl: './offer2-details.component.html',
  styleUrls: ['./offer2-details.component.css']
})
export class Offer2DetailsComponent {
  offer:any={};
  categories:any[]=[];
  imgUrl!:string;
  showOldPrice:boolean = false;

  constructor(private offerService:OfferService , private route:ActivatedRoute){
    this.imgUrl = environment.imgUrl;

  }

  ngOnInit(): void {
    const id = this.route.snapshot.params['id'];
    this.offerService.getOfferById(id).subscribe((res:any)=>{
      this.offer = res;
      console.log(res);
      let oldPrice = res.category.some(elm => elm.old_category_price > 0);
      if(oldPrice){
        this.showOldPrice = true;
      }

      // this.offer.category_name = res.category[0].category_name;
      // this.offer.new_category_price = res.category[0].new_category_price;
      // this.offer.category_quantity = res.category[0].category_quantity;
      // this.offer.description = res.category[0].description;
      // this.offer.category_image = res.category[0].category_image;
    })

  }

  font:string = 'f-1'

  downloadPDF() {
    const element = document.getElementById('capture');
    if (element) {
      const options = {
        filename: `${this.offer?.quote}${this.offer?.id}.pdf`,
        image: { type: 'jpeg', quality: 0.85 },
        html2canvas: { scale: 3, scrollY: 0 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };

      html2pdf()
        .set(options)
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
