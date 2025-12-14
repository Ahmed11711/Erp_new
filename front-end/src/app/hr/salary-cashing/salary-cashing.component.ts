import { Component, OnInit } from '@angular/core';
import { EmployeeService } from '../services/employee.service';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import Swal from 'sweetalert2';
import { BanksService } from 'src/app/financial/services/banks.service';

@Component({
  selector: 'app-salary-cashing',
  templateUrl: './salary-cashing.component.html',
  styleUrls: ['./salary-cashing.component.css']
})
export class SalaryCashingComponent implements OnInit{

  employees:any[]=[];
  employeeData:any[]=[];
  banksData:any[]=[];
  catword:any="name";
  currentMonthValue!:any;
  minMonthValue!: string;
  month!:any
  year!:any

  id:number=0;

  constructor(private empService:EmployeeService, private bankService:BanksService){
    const today = new Date();
    this.year = today.getFullYear();
    this.month = today.getMonth() + 1;
    this.currentMonthValue = `${this.year}-${this.month.toString().padStart(2, '0')}`;
    this.setMinMonthValue();
  }

  setMinMonthValue() {
    const previousMonth = this.month === 1 ? 12 : this.month - 1;
    const previousYear = this.month === 1 ? this.year - 1 : this.year;

    const previousMonthStr = this.formatMonth(previousMonth);

    this.minMonthValue = `${previousYear}-${previousMonthStr}`;
    this.month = previousMonth;
    this.year = previousYear;
  }

  formatMonth(month: number): string {
    return month < 10 ? `0${month}` : `${month}`;
  }



  ngOnInit(): void {
    this.empService.data().subscribe(result=>this.employees=result);
    this.form.patchValue({
      type :'سلف',
      bank_id :'4',

    });
    this.bankService.bankSelect().subscribe((result:any)=>this.banksData=result);
  }

  meritAmount:number = 0;
  subtractionAmount:number = 0;
  fixed_salary:number = 0;

  salary:number = 0;

  empChange(e:any){
    this.id = e.id;
    console.log(this.catword);
    this.dataPerMonth();
  }

  dataPerMonth(){
    console.log(this.month);
    console.log(this.year);

    this.empService.dataPerMonth(this.id,this.month,this.year).subscribe((result:any)=>{
      console.log(result);

      this.employeeData=result;
      this.fixed_salary = result.fixed_salary
      this.meritAmount += result.fixed_salary;
      result.merits.forEach(elm=>{
        this.meritAmount += elm.amount;
      });
      result.subtraction.forEach(elm=>{
        if (elm.type == 'غياب') {
          this.subtractionAmount += +(elm.amount * (this.fixed_salary / 30)).toFixed(2);
        } else{
          this.subtractionAmount += elm.amount;
        }
      });
      if (result.advance_payment.length > 0) {
        result.advance_payment.forEach(elm=>{
          this.subtractionAmount += elm.amount;
        });
      }
      this.salary = this.meritAmount - this.subtractionAmount;
      this.form.patchValue({
        amount:this.salary
      })
    });


  }

  resetData(e:any){
    this.id=0;
    this.employeeData = [];
    this.fixed_salary = 0;
    this.meritAmount = 0;
    this.subtractionAmount = 0;
    this.salary = 0;
    this.errorMessage = undefined;

  }


  form:FormGroup = new FormGroup({
    'amount' :new FormControl(null , [Validators.required ]),
    'bank_id' :new FormControl(null, [Validators.required ]),
  })

  errorMessage!:any;
  submitform(){
    if (this.form.valid) {
      this.form.value.employee_id = this.id;
      this.form.value.month = this.month;
      this.form.value.year = this.year;
      this.form.value.amount = this.salary;
      this.empService.addSalaryPayment(this.form.value).subscribe(result=>{
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
    this.errorMessage = undefined;
    if(this.id || this.id >0){
      this.meritAmount = 0;
      this.subtractionAmount=0;
      this.dataPerMonth();
    }
  }

}
