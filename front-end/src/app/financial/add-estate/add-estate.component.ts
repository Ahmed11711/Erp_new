import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { BanksService } from '../services/banks.service';
import { AssetService } from '../services/asset.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-add-estate',
  templateUrl: './add-estate.component.html',
  styleUrls: ['./add-estate.component.css']
})
export class AddEstateComponent {

  errorform:boolean= false;
  dateFrom!:any
  errorMessage!:string;
  data:any[]=[];

  constructor(private bankService:BanksService , private assetService:AssetService, private route:Router){
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const day = today.getDate();
    this.dateFrom = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
  }

  ngOnInit(){
    this.form.patchValue({
      bank_id:"الخزينة"
    })
    this.bankService.bankSelect().subscribe((result:any)=>this.data=result);
  }


  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const selectedDate = target.value;
    console.log(selectedDate);

  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'asset_date' :new FormControl(null , [Validators.required ]),
    'payment_amount' :new FormControl(null , [Validators.required ]),
    'bank_id' :new FormControl(null , [Validators.required ]),
    'asset_amount' :new FormControl(null , [Validators.required ]),
  })

  submitform(){
    if (this.form.valid) {
      this.assetService.add(this.form.value).subscribe(result=>{
        this.route.navigate(['/dashboard/financial/estates']);
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
