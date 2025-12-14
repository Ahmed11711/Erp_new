import { Component, OnInit } from '@angular/core';
import { OfferService } from '../services/offer.service';

@Component({
  selector: 'app-price-offers',
  templateUrl: './price-offers.component.html',
  styleUrls: ['./price-offers.component.css']
})
export class PriceOffersComponent implements OnInit{

  offers:any[]=[];

  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  constructor(private offerService:OfferService){}

  ngOnInit(): void {
    this.getOffers();
  }

  getOffers(){
    this.offerService.getOffers(this.pageSize,this.page+1).subscribe((res:any)=>{
      this.offers = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    });

  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.getOffers();
  }

}
