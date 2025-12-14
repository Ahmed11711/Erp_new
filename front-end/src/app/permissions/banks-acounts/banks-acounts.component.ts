import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';

@Component({
  selector: 'app-banks-acounts',
  templateUrl: './banks-acounts.component.html',
  styleUrls: ['./banks-acounts.component.css']
})
export class BanksAcountsComponent {

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
  })

  openForm(){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = true;
    this.addbtn = true;
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
