import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';

@Component({
  selector: 'app-add-covenant',
  templateUrl: './add-covenant.component.html',
  styleUrls: ['./add-covenant.component.css']
})
export class AddCovenantComponent {

  shippingWays:any[]=[];
  orderSources:any[]=[];
  errormessage:boolean=false;
  products:any[]=[];
  banksData:any[]=[]
  dateFrom!:any

  constructor(){
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const day = today.getDate();
    this.dateFrom = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
  }

  ngOnInit(): void {
    this.form.patchValue({
      bank:"الخزينة",
      type:"نوع العهدة",
      kind:"متحصل العهدة",
    })
  }

  form:FormGroup = new FormGroup({
    'bank' :new FormControl(null),
    'type' :new FormControl(null, [Validators.required ]),
    'kind' :new FormControl(null, [Validators.required ]),
    'exchange_statement' :new FormControl(null, [Validators.required ] ),
    'price' :new FormControl(null, [Validators.required ] ),
    'note' :new FormControl(null, [Validators.required ] ),
    'description' :new FormControl(null, [Validators.required ] ),
    'order_image' :new FormControl(null , [Validators.required ]),
  })

  imgtext:string="صورة الايصال"
  fileopend:boolean=false;

  openFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
      this.fileopend=true;
    }
  }


  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const selectedDate = target.value;
    console.log(selectedDate);

  }

  submitform(){

  }
}
