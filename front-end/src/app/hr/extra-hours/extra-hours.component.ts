import { Component } from '@angular/core';
import { EmployeeService } from '../services/employee.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-extra-hours',
  templateUrl: './extra-hours.component.html',
  styleUrls: ['./extra-hours.component.css']
})
export class ExtraHoursComponent {

  employees:any[]=[];
  catword:any="name"
  currentMonthValue!:any
  month!:any
  year!:any

  id:number=0;
  name!:string;
  fixed_salary!:string;
  hourPrice!:number;
  extraHours:number=0;
  errorMessage: any;

  constructor(private empService:EmployeeService){
    const today = new Date();
    this.year = today.getFullYear();
    this.month = today.getMonth() + 1;
    this.currentMonthValue = `${this.year}-${this.month.toString().padStart(2, '0')}`;
  }



  ngOnInit(): void {
    this.empService.data().subscribe(result=>this.employees=result);
  }

  empChange(e:any){
    this.id = e.id;
    this.name = e.name;
    this.fixed_salary = e.fixed_salary;
    this.hourPrice = e.fixed_salary/30/9*1.5;
    this.extraHours = e.extra_hours.reduce((acc, item) => acc + item.hours, 0);
  }


  resetData(e:any){
    this.id=0;
    this.errorMessage = undefined;
  }



  addExtraHours(){
    Swal.fire({
      title: 'اضافات ساعات اضافية',
      input: 'number',
      inputPlaceholder:'عدد الساعات',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة'
        }
        if (value !== '') {
          const data = {
            'employee_id':this.id,
            'month':this.month,
            'year':this.year,
            'hours':value,
          }
          this.empService.addExtraHours(data).subscribe((res)=>{
            if (res) {
              this.employees = res;
              this.extraHours= res.find(item => item.id === this.id).extra_hours.reduce((acc, item) => acc + item.hours, 0);
            }
          })
        }
        return undefined
      }
    })
  }



}
