import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';

@Component({
  selector: 'app-category',
  templateUrl: './category.component.html',
  styleUrls: ['./category.component.css']
})
export class CategoryComponent {

  data:any[]=[];
  dateFrom!:any
  dateTo!:any

  constructor(){
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const day = today.getDate();
    this.dateFrom = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
    this.dateTo = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
    this.form.patchValue({
      type:'نوع الراتب'
    })
  }

  ngOnInit(){

  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'type' :new FormControl(null , [Validators.required ]),
  })

  submitform(){

  }

  productChange(e:any){

  }

  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const selectedDate = target.value;
    console.log(selectedDate);

  }
  onDateToChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const selectedDate = target.value;
    console.log(selectedDate);

  }
}
