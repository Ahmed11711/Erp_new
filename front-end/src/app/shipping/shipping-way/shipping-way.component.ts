import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ShippingWayService } from '../services/shipping-way.service';

@Component({
  selector: 'app-shipping-way',
  templateUrl: './shipping-way.component.html',
  styleUrls: ['./shipping-way.component.css']
})
export class ShippingWayComponent {

  openbtn:boolean=true;
  formdiv:boolean=false;
  errorform:boolean= false;
  errorMessage!:string;
  data:any[]=[];

  constructor(private shippingWay:ShippingWayService){}

  ngOnInit(){
    this.getData();
  }

  getData(){
    return this.shippingWay.data().subscribe(result=>{
      this.data=result;
    })
  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ])
  })

  openForm(){
    this.openbtn = false;
    this.formdiv = true;
  }

  submitform(){
    if (this.form.valid) {
      this.shippingWay.add(this.form.value).subscribe(result=>{
        if (result.message === 'تم اضافة طريقة الشحن بنجاح') {
          this.openbtn = true;
          this.formdiv = false;
          this.getData();
        }
      },
      (error)=>{
        if(error.status === 422 && error.error.message === "The name has already been taken."){
          this.errorform = true;
          this.errorMessage = "هذا الاسم تم إضافته من قبل";
        }
      }
      )
    }
  }
}
