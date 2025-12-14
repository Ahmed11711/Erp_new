import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { CimmitmentService } from '../services/cimmitment.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-add-commitment',
  templateUrl: './add-commitment.component.html',
  styleUrls: ['./add-commitment.component.css']
})
export class AddCommitmentComponent {

  errorform:boolean= false;
  dateFrom!:any
  errorMessage!:string;
  data:any[]=[];

  constructor(private cimmitmentService:CimmitmentService , private route:Router){
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const day = today.getDate();
    this.dateFrom = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
  }

  ngOnInit(){

  }


  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const selectedDate = target.value;
  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'date' :new FormControl(null , [Validators.required ]),
    'deserved_amount' :new FormControl(null , [Validators.required ]),
  })

  submitform(){
    if (this.form.valid) {
      this.cimmitmentService.add(this.form.value).subscribe(result=>{
        this.route.navigate(['/dashboard/financial/discounts']);
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
