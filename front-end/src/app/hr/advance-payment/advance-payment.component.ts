import { Component } from '@angular/core';
import { EmployeeService } from '../services/employee.service';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import Swal from 'sweetalert2';
import { BanksService } from 'src/app/financial/services/banks.service';

@Component({
  selector: 'app-advance-payment',
  templateUrl: './advance-payment.component.html',
  styleUrls: ['./advance-payment.component.css']
})
export class AdvancePaymentComponent {

  employees:any[]=[];
  banksData:any[]=[];
  catword:any="name"
  currentMonthValue!:any
  month!:any
  year!:any

  id:number=0;
  errorMessage: any;

  constructor(private empService:EmployeeService, private bankService:BanksService){
    const today = new Date();
    this.year = today.getFullYear();
    this.month = today.getMonth() + 1;
    this.currentMonthValue = `${this.year}-${this.month.toString().padStart(2, '0')}`;
  }



  ngOnInit(): void {
    this.empService.data().subscribe(result=>this.employees=result);
    this.form.patchValue({
      type :'سلف',
      bank_id :'4',

    });
    this.bankService.bankSelect().subscribe((result:any)=>this.banksData=result);
  }

  empChange(e:any){
    this.id = e.id;
    console.log(this.catword);

  }

  resetData(e:any){
    this.id=0;
    this.errorMessage = undefined;

  }


  form:FormGroup = new FormGroup({
    'type' :new FormControl(null , [Validators.required ]),
    'amount' :new FormControl(null , [Validators.required ]),
    'reason' :new FormControl(null),
    'bank_id' :new FormControl(null, [Validators.required ]),
  })

  submitform(){
    if (this.form.valid) {
      this.form.value.employee_id = this.id;
      this.form.value.month = this.month;
      this.form.value.year = this.year;
      this.empService.addAdvancePayment(this.form.value).subscribe(result=>{
        console.log(result);
        if (result) {
          Swal.fire({
            icon : 'success',
            timer:1500,
            showConfirmButton:false,
          }).then(result=>{
            location.reload();
          });
        }


      },
      (error)=>{
        this.errorMessage = error.error.message;
      }
      )
    }
  }

  onMonthChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.currentMonthValue = target.value;
    const [year, month] = this.currentMonthValue.split('-');

    this.month = month;
    this.year = +year;
  }

}
