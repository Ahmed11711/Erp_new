import { ChangeDetectorRef, Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { OfferService } from '../services/offer.service';
import { MatDialog } from '@angular/material/dialog';
import { AngularEditorComponent } from 'src/app/shared/angular-editor/angular-editor.component';

@Component({
  selector: 'app-price-offer2',
  templateUrl: './price-offer2.component.html',
  styleUrls: ['./price-offer2.component.css']
})
export class PriceOffer2Component {
  note!:any;

  rows: {
    category_name: string;
    category_quantity: number;
    new_category_price: number;
    old_category_price: number;
    total_price: number;
    description: string;
    image?: File;
    imageName?: string;
    imageUrl?: string;
    category_image?: string;
  }[] = [
    {
      category_name: 'product',
      description: 'description',
      category_quantity: 0,
      old_category_price: 0,
      new_category_price: 0,
      total_price: 0
    },
  ];

  addRow() {
    this.rows.push({ category_name: 'product', description: 'description', category_quantity: 0, old_category_price:0, new_category_price: 0, total_price: 0 });
  }

  openFileInput(index: number) {
    const fileInput = document.getElementById('fileInput' + index) as HTMLInputElement;
    if (fileInput) {
      fileInput.click();
    }
  }

  onFileChanged(event: any, index: number) {
    const file = event.target.files[0];
    if (file) {
      this.rows[index].image = file;
      this.rows[index].imageName = file.name;

      const reader = new FileReader();
      reader.onload = () => {
        this.rows[index].imageUrl = reader.result as string;
      };
      reader.readAsDataURL(file);
    }
  }

  updateTotal(row: any) {
    row.total_price = row.category_quantity * row.new_category_price;
    this.calc(arguments);
  }

  errorform:boolean= false;
  errorMessage!:string;
  dateFrom!:any;
  dateTo!:any;

  data:object={};
  phone_number:string="+201118127345";
  email:string="info@magalis-egypt.com";
  quote!:string;
  title!:string;

  id;

  constructor(private offerService:OfferService ,private router:Router, private dialog: MatDialog, private cd: ChangeDetectorRef, private ActivatedRoute:ActivatedRoute ){
  }

  openEditorDialog(description , i) {

    const dialogRef = this.dialog.open(AngularEditorComponent, {
      width: '90%',
      data: { htmlContent: description }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.rows[i].description = result;
        this.cd.detectChanges()
      }
    });
  }

  ngOnInit(){
    this.ActivatedRoute.queryParams.subscribe({
      next: (params) =>{
        this.id = Number(params['id'])
      }
    })
    if (this.id) {
      this.offerService.getOfferById(this.id).subscribe((res:any)=>{
        this.rows = res.category;
        this.dateFrom = res.dateFrom;
        this.dateTo = res.dateTo;
        this.quote = res.quote;
        this.phone_number = res.phone_number;
        this.email = res.email;
        this.transportation = res.transportation;
        this.vat = res.vat;
        this.title = res.title;
        this.title = res.title;
        if (res.note) {
          this.note = res.note;
        }
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


  submitform() {
    const formData = new FormData();

    formData.append('dateFrom', this.dateFrom);
    formData.append('dateTo', this.dateTo);
    formData.append('quote', this.quote);
    if (this.note && this.note.length > 0) {
      formData.append('note', this.note);
    }
    if (this.title && this.title.length > 2) {
      formData.append('title', this.title);
    }
    if (this.id) {
      formData.append('id', this.id);
    }
    formData.append('offer', 'offer2');

    this.rows.forEach((row, index) => {
      formData.append(`categories[${index}][category_name]`, row.category_name);
      formData.append(`categories[${index}][description]`, row.description);
      formData.append(`categories[${index}][category_quantity]`, row.category_quantity.toString());
      formData.append(`categories[${index}][old_category_price]`, row.old_category_price.toString());
      formData.append(`categories[${index}][new_category_price]`, row.new_category_price.toString());
      formData.append(`categories[${index}][total_price]`, row.total_price.toString());

      if (row.image) {
        formData.append(`categories[${index}][image]`, row.image, row.imageName || `image${index}.jpg`);
      } else {
        formData.append(`categories[${index}][original_image]`, row.category_image ?? '');
      }
    });

    formData.append('subtotal', this.subtotal.toString());
    formData.append('vat', this.vat.toString());
    formData.append('transportation', this.transportation.toString());
    formData.append('total', this.total.toString());
    formData.append('email', this.email);
    formData.append('phone_number', this.phone_number.toString());

    this.offerService.addOffer(formData).subscribe(
      (result) => {
        if (result) {
          this.router.navigateByUrl('/dashboard/permissions/priceoffer');
        }
      },
      (error) => {
        console.error('Error submitting form:', error);
      }
    );
  }



}
