import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ShippingLinesService } from '../services/shipping-lines.service';

@Component({
  selector: 'app-shipping-lines',
  templateUrl: './shipping-lines.component.html',
  styleUrls: ['./shipping-lines.component.css']
})
export class ShippingLinesComponent {
  openbtn:boolean=true;
  formdiv:boolean=false;
  errorform:boolean= false;
  addForm:boolean =false;
  addbtn:boolean =false;
  editForm:boolean =false;
  editbtn:boolean =false;

  errorMessage!:string;
  data:any[]=[];

  constructor(private shippingLines:ShippingLinesService){}

  ngOnInit(){
    this.getData();
  }

  getData(){
    return this.shippingLines.dataLines().subscribe(result=>{
      this.data=result;
    })
  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ])
  })

  openForm(){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = true;
    this.editForm = false;
    this.addbtn = true;
    this.editbtn = false;
  }

  submitform(){
    if (this.addForm) {
      if (this.form.valid) {
        this.shippingLines.addLine(this.form.value).subscribe(result=>{
          if (result) {
            this.openbtn = true;
            this.formdiv = false;
            this.getData();
            this.form.reset();
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
    } else if (this.editForm) {
      if (this.form.valid) {
        this.shippingLines.editLine(this.form.value , this.editId).subscribe(result=>{
          if (result) {
            this.openbtn = true;
            this.formdiv = false;
            this.getData();
            this.form.reset();
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

  deleteData(id:number){
    this.shippingLines.deleteLine(id).subscribe(result=>{
      if (result === "Shipping Line Deleted Successfully") {
        this.getData();
      }

    })
  }

  editId!:number;
  editData(id:number , name:string){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = false;
    this.editForm = true;
    this.addbtn = false;
    this.editbtn = true;
    this.editId = id;
    this.form.patchValue({
      name:name
    })
  }
}
