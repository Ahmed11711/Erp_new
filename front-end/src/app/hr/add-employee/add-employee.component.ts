import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { EmployeeService } from '../services/employee.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-add-employee',
  templateUrl: './add-employee.component.html',
  styleUrls: ['./add-employee.component.css']
})
export class AddEmployeeComponent {

  errorform:boolean= false;
  errorMessage!:string;
  data:any[]=[];

  constructor(private employeeService:EmployeeService , private route:Router){
    this.form.patchValue({
      salary_type:'نوع الراتب',
      working_hours:'عدد ساعات العمل'
    })
    this.salarytype = 'نوع الراتب';
  }


  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'code' :new FormControl(null ),
    'level' :new FormControl(null , [Validators.required ]),
    'department' :new FormControl(null , [Validators.required ]),
    'fixed_salary' :new FormControl(null),
    'salary_type' :new FormControl(null , [Validators.required ]),
    'working_hours' :new FormControl(null),
    'acc_no' :new FormControl(null),
  })

  salarytype!: string;
  salaryType(e){
    this.salarytype = e?.target?.value;
  }

  submitform(){
    if (this.form.valid) {
      if (this.salarytype == 'متغير') {
        this.form.value.fixed_salary = 0;
      }
      if (this.form.value.working_hours == 'عدد ساعات العمل') {
        this.form.value['working_hours'] = null;
      }
      this.employeeService.add(this.form.value).subscribe(result=>{
        if (result) {
          this.route.navigate(['/dashboard/hr/employee']);
        }

      },
      (error)=>{
        console.log(error);

        if(error.status === 422 && error.error.message === "The code has already been taken."){
          this.errorform = true;
          this.errorMessage = "هذا الكود مستخدم  ";
        }
        if (error.status === 500) {
          this.errorform = true;
          this.errorMessage = " تاكد من البيانات او رقم البصمة موجود بالفعل ";
        }
      }
      )
    }
  }
}
