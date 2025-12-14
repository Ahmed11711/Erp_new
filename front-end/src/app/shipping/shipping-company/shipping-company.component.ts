import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ShippingCompanyService } from '../services/shipping-company.service';
import { AuthService } from 'src/app/auth/auth.service';

@Component({
  selector: 'app-shipping-company',
  templateUrl: './shipping-company.component.html',
  styleUrls: ['./shipping-company.component.css']
})
export class ShippingCompanyComponent {

  openbtn:boolean=true;
  formdiv:boolean=false;
  errorform:boolean= false;
  addForm:boolean =false;
  addbtn:boolean =false;
  editForm:boolean =false;
  editbtn:boolean =false;

  errorMessage!:string;
  data:any[]=[];

  user!:string;

  constructor(private shippingCompany:ShippingCompanyService , private authService:AuthService){}

  ngOnInit(){
    this.user = this.authService.getUser();
    this.getData();
  }

  getData(){
    return this.shippingCompany.shippingCompanies().subscribe(result=>{
      this.data=result;
    })
  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'type' :new FormControl(null , [Validators.required ])
  })

  openForm(){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = true;
    this.editForm = false;
    this.addbtn = true;
    this.editbtn = false;
    this.form.patchValue({
      type:"مندوب"
    })
  }

  submitform(){
    if (this.addForm) {
      if (this.form.valid) {
        this.shippingCompany.addLine(this.form.value).subscribe(result=>{
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
        this.shippingCompany.editLine(this.form.value , this.editId).subscribe(result=>{

          if (result) {
            this.openbtn = true;
            this.formdiv = false;
            this.getData();
            this.form.reset();
          }
        },
        (error)=>{
          console.log(error);

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
    this.shippingCompany.deleteLine(id).subscribe(result=>{
      if (result === "deleted") {
        this.getData();
      }

    })
  }

  editId!:number;
  editData(id:number , name:string , type:string){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = false;
    this.editForm = true;
    this.addbtn = false;
    this.editbtn = true;
    this.editId = id;
    this.form.patchValue({
      name,
      type
    })
  }
}
