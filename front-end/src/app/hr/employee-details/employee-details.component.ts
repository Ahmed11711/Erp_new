import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { EmployeeService } from '../services/employee.service';
import { ActivatedRoute } from '@angular/router';

@Component({
  selector: 'app-employee-details',
  templateUrl: './employee-details.component.html',
  styleUrls: ['./employee-details.component.css']
})
export class EmployeeDetailsComponent {

  employee:any={};
  data:any[]=[];
  catword:any="name"
  currentMonthValue!:any
  currentDateValue!:any
  month!:any
  year!:any

  id!:number

  merits:any[]=[];
  subtraction:any[]=[];
  advance_payments:any[]=[];


  constructor(private empService:EmployeeService ,private route:ActivatedRoute){
    const today = new Date();
    this.year = today.getFullYear();
    this.month = today.getMonth();
    const day = today.getDate();
    this.currentMonthValue = `${this.year}-${this.month.toString().padStart(2, '0')}`;
    this.currentDateValue = `${this.year}-${this.month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
    this.route.params
    this.route.params.subscribe(res=> this.id = res.id);
  }

  ngOnInit(): void {
    this.search(arguments);
  }

  form:FormGroup = new FormGroup({
  })

  submitform(){

  }


  search(e:any){

    this.empService.dataPerMonth(this.id,this.month ,this.year).subscribe((result:any)=>{
      this.merits = result['merits'];
      this.subtraction =result['subtraction'];
      this.advance_payments =result['advance_payment'];
        console.log(result);

        let obj = {};
        obj['name'] = result.name;
        obj['acc_no'] = result.acc_no;
        obj['code'] = result.code;
        obj['level'] = result.level;
        obj['created_at'] = result.created_at;
        //استحقاقات
        obj['fixed_salary'] = result.fixed_salary;
        obj['calc_salary'] = result.fixed_salary;
        obj['incentives'] = 0;
        obj['suits'] = 0;
        obj['rewards'] = 0;
        obj['changed_salary'] = 0;
        //استقطاعات
        obj['rival'] = 0;
        obj['absence'] = 0;
        obj['absence_sub'] = 0;
        obj['advance_payment'] = 0;
        if (result.merits.length > 0) {
          let incentives = 0;
          let suits = 0;
          let rewards = 0;
          let changed_salary = 0;
          result.merits.forEach(item=>{
            if (item.type == "حوافز") {
              incentives+=item.amount;
            }
            if (item.type == "بدلات") {
              suits+=item.amount;
            }
            if (item.type == "مكافئات") {
              rewards+=item.amount;
            }
            if (item.type == "الراتب المتغير") {
              changed_salary+=item.amount;
              obj['calc_salary'] = obj['calc_salary']+=item.amount;
            }

          })
          obj['incentives'] = incentives;
          obj['suits'] = suits;
          obj['rewards'] = rewards;
          obj['changed_salary'] = changed_salary;
        }
        if (result.subtraction.length > 0) {
          let rival = 0;
          let absence = 0;
          let absence_sub = 0;
          result.subtraction.forEach(item=>{
            if (item.type == "خصومات") {
              rival+=item.amount;
            }
            if (item.type == "غياب") {
              absence+=item.amount;
              absence_sub += Number( ((obj['fixed_salary']/30)*item.amount).toFixed(2));
            }
          })
          obj['rival'] = rival;
          obj['absence'] = absence;
          obj['absence_sub'] = absence_sub;
        }

        if (result.advance_payment.length > 0) {
          let advance_payment = 0;
          result.advance_payment.forEach(item=>{
            if (item.type == "سلف") {
              advance_payment+=item.amount;
            }
          })
          obj['advance_payment'] = advance_payment;
        }
        console.log(result.fixed_salary);

        obj['total_merit'] = obj['changed_salary']+ obj['incentives']+obj['suits'] +obj['rewards'] + result.fixed_salary;
        obj['total_sub'] = Number((obj['rival']+ obj['absence_sub']+obj['advance_payment']).toFixed(2));
        obj['net_total'] = obj['total_merit']-obj['total_sub'];
        this.employee =obj;

        console.log(this.merits);

    })
  }

  onMonthChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.currentMonthValue = target.value;
    const [year, month] = this.currentMonthValue.split('-');

    this.month = month;
    this.year = +year;
    this.search(arguments);
  }

  dataPrint:any = {};
  print() {
    this.dataPrint = this.employee;
    this.dataPrint.currentMonthValue = this.currentMonthValue;
    this.dataPrint.currentDateValue = this.currentDateValue;
  }

  handleDataEvent(data:any) {
    this.employee['merits']=this.merits;
    this.employee['subtraction']=this.subtraction;
    this.employee['advance_payments']=this.advance_payments;
    this.employee['actualHours']=data.actualHours;
    this.employee['differnceSalary']=data.differnceSalary;
    this.employee['fixedSalary']=data.fixedSalary;
    this.employee['holidayDays']=data.holidayDays;
    this.employee['hourPrice']=data.hourPrice;
    this.employee['tableData']=data.tableData;
    this.employee['totalActualHoursSalary']=data.totalActualHoursSalary;
    this.employee['totalHours']=data.totalHours;
    this.employee['hoursDifferenceStr']=data.hoursDifferenceStr.split('-')[1];
    console.log(this.employee);

    this.employee['total_merit'] = this.employee['changed_salary']+ this.employee['incentives']+this.employee['suits'] +this.employee['rewards'] + this.employee['fixed_salary'];
    if (this.employee.acc_no) {
      if(data.totalActualHoursSalary > this.employee['fixed_salary']){
        this.employee['total_merit'] = this.employee['total_merit'] + (data.totalActualHoursSalary - this.employee['fixed_salary']);
      }
      console.log(data.differnceSalary);

      if (data.differnceSalary > 0) {
        this.employee['total_sub'] = Number((this.employee['rival']+ this.employee['absence_sub']+this.employee['advance_payment']).toFixed(2));
        this.employee['differnceSalary']=0;
      } else {
        this.employee['differnceSalary'] = Math.abs(data.differnceSalary);
        this.employee['total_sub'] = Number((this.employee['rival']+ this.employee['absence_sub']+this.employee['advance_payment']+(data.differnceSalary * -1)).toFixed(2));

      }

      this.employee['net_total'] = this.employee['total_merit']-this.employee['total_sub'];
    }

  }


}
