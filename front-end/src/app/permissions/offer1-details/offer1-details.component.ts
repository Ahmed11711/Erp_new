import { Component, OnInit } from '@angular/core';
import { OfferService } from '../services/offer.service';
import { ActivatedRoute } from '@angular/router';
import * as html2pdf from 'html2pdf.js';
import { saveAs } from 'file-saver';

@Component({
  selector: 'app-offer1-details',
  templateUrl: './offer1-details.component.html',
  styleUrls: ['./offer1-details.component.css']
})
export class Offer1DetailsComponent implements OnInit {
  offer:any={};
  categories:any[]=[];
  showOldPrice:boolean = false;

  constructor(private offerService:OfferService , private route:ActivatedRoute){}

  ngOnInit(): void {
    const id = this.route.snapshot.params['id'];
    console.log(id);

    this.offerService.getOfferById(id).subscribe((res:any)=>{
      this.offer = res;
      this.categories = res.category;
      let oldPrice = this.categories.some(elm => elm.old_category_price > 0);
      if(oldPrice){
        this.showOldPrice = true;
      }
      console.log(res);

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
