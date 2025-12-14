import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { EmployeeService } from '../services/employee.service';
import { AuthService } from 'src/app/auth/auth.service';

@Component({
  selector: 'app-edit-employee',
  templateUrl: './edit-employee.component.html',
  styleUrls: ['./edit-employee.component.css']
})
export class EditEmployeeComponent {
  user!:string;
  errorform:boolean= false;
  errorMessage!:string;
  data:any[]=[];
  id!:any;

  constructor(private employeeService:EmployeeService , private route:Router , private router:ActivatedRoute, private authService:AuthService){
    this.id = this.router.snapshot.paramMap.get('id');
    this.user = this.authService.getUser();
  }

  ngOnInit(){
    this.employeeService.getById(this.id).subscribe((res : any)=>{
      this.form.patchValue({
        'name': res.name,
        'code': res.code,
        'level': res.level,
        'department': res.department,
        'fixed_salary': res.fixed_salary,
        'salary_type': res.salary_type,
        'working_hours': res.working_hours,
        'acc_no': res.acc_no,
      })
      if(res.working_hours == null){
        this.form.patchValue({
          'working_hours': 'عدد ساعات العمل'
        })

      }
      this.salarytype = res.salary_type;

    })
  }

  salarytype!: string;
  salaryType(e){
    this.salarytype = e?.target?.value;
  }


  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'code' :new FormControl(null , [Validators.required ]),
    'level' :new FormControl(null , [Validators.required ]),
    'department' :new FormControl(null , [Validators.required ]),
    'fixed_salary' :new FormControl(null , [Validators.required ]),
    'salary_type' :new FormControl(null , [Validators.required ]),
    'working_hours' :new FormControl(null),
    'acc_no' :new FormControl(null),
  })

  submitform(){
    if (this.form.valid) {
      if (this.salarytype == 'متغير') {
        this.form.value.fixed_salary = 0;
      }
      if (this.form.value.working_hours == 'عدد ساعات العمل') {
        this.form.value['working_hours'] = null;
      }
      this.employeeService.edit(this.id , this.form.value).subscribe(result=>{
        if (result) {
          this.route.navigate(['/dashboard/hr/employee']);
        }

      },
      (error)=>{
        console.log(error);
        if (error.status === 500) {
          this.errorform = true;
          this.errorMessage = " تاكد من البيانات او رقم البصمة موجود بالفعل ";
        }
      }
      )
    }
  }
}
