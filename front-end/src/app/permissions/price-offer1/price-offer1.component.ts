import { Component, ViewChild } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { OrderService } from 'src/app/shipping/services/order.service';
import { OfferService } from '../services/offer.service';
import { ActivatedRoute, Router } from '@angular/router';
import { MatDialog } from '@angular/material/dialog';
import { AngularEditorComponent } from '@kolkov/angular-editor';

@Component({
  selector: 'app-price-offer1',
  templateUrl: './price-offer1.component.html',
  styleUrls: ['./price-offer1.component.css']
})
export class PriceOffer1Component {
  id;

  rows: { category_name: string; category_quantity: number; new_category_price: number; old_category_price: number; total_price: number }[] = [
    { category_name: 'category', category_quantity: 0,  old_category_price:0, new_category_price: 0, total_price: 0 },
  ];

  addRow() {
    this.rows.push({ category_name: 'category', category_quantity: 0, old_category_price:0, new_category_price: 0, total_price: 0 });
  }

  updateTotal(row: any) {
    row.total_price = row.category_quantity * row.new_category_price;
    this.calc(arguments)
  }

  errorform:boolean= false;
  errorMessage!:string;
  dateFrom!:any;
  dateTo!:any;
  note!:any;

  data:object={};
  phone_number:string="+201118127345";
  email:string="info@magalis-egypt.com";
  quote!:string;

  constructor(private offerService:OfferService ,private router:Router, private ActivatedRoute:ActivatedRoute ){
  }

  ngOnInit(){
    this.ActivatedRoute.queryParams.subscribe({
      next: (params) =>{
        this.id = params['id'];
      }
    })
    if (this.id) {
      this.offerService.getOfferById(this.id).subscribe((res:any)=>{
        this.rows = res.category;
        this.dateFrom = res.dateFrom;
        this.dateTo = res.dateTo;
        if (res.note) {
          this.note = res.note;
        }
        this.quote = res.quote;
        this.phone_number = res.phone_number;
        this.email = res.email;
        this.transportation = res.transportation;
        this.vat = res.vat;
        this.calc(arguments);
      });
    }
  }

  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateFrom = target.value;
  }
  onDateToChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateTo = target.value;
  }

  subtotal:number=0;
  vat:number=0;
  vatPercent:number=14;
  total:number= 0;
  transportation:number= 0;
  clearTransportation:boolean = true;
  cleardiv:boolean = true;
  changedVat:boolean=false;

  calc(e){
    this.subtotal = 0;
    this.rows.forEach(elm=> this.subtotal += elm.total_price);
    if (e?.target?.id == 'vat') {
      this.changedVat = true;
    }
    if (!this.changedVat) {
      this.vat = this.subtotal * this.vatPercent/100  + (this.transportation * this.vatPercent/100);
    }
    this.total = this.subtotal + this.vat + this.transportation;
  }
  clearinp(){
    this.vat = 0;
    this.cleardiv = false;
    this.changedVat = true;
    this.calc(arguments);
  }
  clearTransportationFn(){
    this.transportation = 0;
    this.clearTransportation = false;
    this.calc(arguments);
  }

  submitform(){
    if (this.id) {
      this.data['id'] = this.id;
    }
    this.data['dateFrom'] = this.dateFrom;
    if (this.dateTo) {
      this.data['dateTo'] = this.dateTo;
    } else {
      this.data['dateTo'] = this.dateFrom;
    }
    this.data['quote'] = this.quote;
    this.data['offer'] = 'offer1';
    this.data['categories']=this.rows;
    this.data['subtotal']=this.subtotal;
    this.data['vat']=this.vat;
    this.data['transportation']=this.transportation;
    this.data['total']=this.total;
    this.data['email']=this.email;
    this.data['note']=this.note;
    this.data['phone_number']=this.phone_number;
    this.offerService.addOffer(this.data).subscribe(result=> {
      if(result){
        this.router.navigateByUrl('/dashboard/permissions/priceoffer');
      }

    })
  }

}
