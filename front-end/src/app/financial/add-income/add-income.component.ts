import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { IncomeService } from '../services/income.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-add-income',
  templateUrl: './add-income.component.html',
  styleUrls: ['./add-income.component.css']
})
export class AddIncomeComponent {

  errorform:boolean= false;
  dateFrom!:any
  errorMessage!:string;
  data:any[]=[];

  constructor(private incomeService:IncomeService , private route:Router){}

  ngOnInit(){

  }


  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const selectedDate = target.value;
  }

  form:FormGroup = new FormGroup({
    'type' :new FormControl(null , [Validators.required ]),
    'date' :new FormControl(null , [Validators.required ]),
    'income_amount' :new FormControl(null , [Validators.required ]),
  })

  submitform(){
    if (this.form.valid) {
      this.incomeService.add(this.form.value).subscribe(result=>{
        this.route.navigate(['/dashboard/financial/otherincome']);
      },
      (error)=>{
        if(error){
          this.errorform = true;
          this.errorMessage = "من فضلك تاكد من كل المدخلات";
        }
      }
      )
    }
  }

}
