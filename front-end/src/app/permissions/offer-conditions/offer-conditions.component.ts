import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';

@Component({
  selector: 'app-offer-conditions',
  templateUrl: './offer-conditions.component.html',
  styleUrls: ['./offer-conditions.component.css']
})
export class OfferConditionsComponent {

  openbtn:boolean=true;
  formdiv:boolean=false;
  errorform:boolean= false;
  addForm:boolean =false;
  addbtn:boolean =false;

  errorMessage!:string;
  data:any[]=[];

  ngOnInit(){
    this.getData();
  }

  getData(){
    // return this.shippingCompany.dataLines().subscribe(result=>{
    //   this.data=result;
    // })
  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'type' :new FormControl(null , [Validators.required ])
  })

  openForm(){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = true;
    this.addbtn = true;
    this.form.patchValue({
      type:"النوع"
    })
  }

  submitform(){
    if (this.addForm) {
      if (this.form.valid) {
        // this.shippingCompany.addLine(this.form.value).subscribe(result=>{
        //   if (result) {
        //     this.openbtn = true;
        //     this.formdiv = false;
        //     this.getData();
        //     this.form.reset();
        //   }
        // },
        // (error)=>{
        //   if(error.status === 422 && error.error.message === "The name has already been taken."){
        //     this.errorform = true;
        //     this.errorMessage = "هذا الاسم تم إضافته من قبل";
        //   }
        // }
        // )
      }
    }

  }

  deleteData(id:number){
    // this.shippingCompany.deleteLine(id).subscribe(result=>{
    //   if (result === "deleted") {
    //     this.getData();
    //   }

    // })
  }
}
