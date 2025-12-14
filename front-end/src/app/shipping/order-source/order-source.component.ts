import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { OrderSourceService } from '../services/order-source.service';

@Component({
  selector: 'app-order-source',
  templateUrl: './order-source.component.html',
  styleUrls: ['./order-source.component.css']
})
export class OrderSourceComponent implements OnInit{

  openbtn:boolean=true;
  formdiv:boolean=false;
  errorform:boolean= false;
  errorMessage!:string;
  data:any[]=[];

  constructor(private oderSource:OrderSourceService){}

  ngOnInit(){
    this.getData();
  }

  getData(){
    return this.oderSource.data().subscribe(result=>{
      this.data=result;
      console.log(result);
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
      this.oderSource.add(this.form.value).subscribe(result=>{
        if (result.message === 'تم اضافة مصدر الطلب بنجاح') {
          this.openbtn = true;
          this.formdiv = false;
          this.getData();
        }
      },
      (error)=>{
        if(error.status === 422 && error.error.message === "The name has already been taken."){
          this.errorform = true;
          this.errorMessage = "هذا المصدر تم إضافته من قبل";
        }
      }
      )
    }
  }

}
