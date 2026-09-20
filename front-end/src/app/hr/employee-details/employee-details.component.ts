import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { EmployeeService } from '../services/employee.service';
import { ActivatedRoute } from '@angular/router';
import { defaultFingerprintMonthValue, fingerprintPeriodForMonth, parseOvertimeMeritReason, parseYearMonthValue } from '../utils/fingerprint-hours.utils';

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
  dateFrom!: string;
  dateTo!: string;
  currentDateValue!:any
  month!:any
  year!:any

  id!:number
  isLoading = true;
  loadError = '';

  merits:any[]=[];
  subtraction:any[]=[];
  advance_payments:any[]=[];

  /** حوافز بدون أيام/ساعات الإضافي — تظهر في سطور مستقلة */
  get statementMerits(): any[] {
    return (this.merits || []).filter((item) => !parseOvertimeMeritReason(item.reason));
  }


  constructor(private empService:EmployeeService ,private route:ActivatedRoute){
    this.applyMonthValue(defaultFingerprintMonthValue());
    const today = new Date();
    this.currentDateValue = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    this.id = Number(this.route.snapshot.params['id']);
    this.route.params.subscribe((res) => {
      const nextId = Number(res.id);
      if (!nextId || nextId === this.id) {
        return;
      }
      this.id = nextId;
      this.search();
    });
  }

  ngOnInit(): void {
    this.search();
  }

  form:FormGroup = new FormGroup({
  })

  submitform(){

  }


  search(_e?: any){
    if (!this.id || !this.month || !this.year) {
      return;
    }

    this.isLoading = true;
    this.loadError = '';
    this.lastSheetData = null;
    this.empService.dataPerMonth(this.id, this.month, this.year).subscribe({
      next: (result: any) => {
        if (!result) {
          this.isLoading = false;
          this.loadError = 'تعذر تحميل بيانات الموظف';
          return;
        }

        this.merits = result.merits || [];
        this.subtraction = result.subtraction || [];
        this.advance_payments = result.advance_payment || [];

        const obj: any = {};
        obj.name = result.name;
        obj.acc_no = result.acc_no;
        obj.code = result.code;
        obj.level = result.level;
        obj.created_at = result.created_at;
        obj.fixed_salary = result.fixed_salary;
        obj.calc_salary = result.fixed_salary;
        obj.incentives = 0;
        obj.suits = 0;
        obj.rewards = 0;
        obj.changed_salary = 0;
        obj.rival = 0;
        obj.absence = 0;
        obj.absence_sub = 0;
        obj.advance_payment = 0;

        this.merits.forEach((item) => {
          if (item.type == 'حوافز') {
            obj.incentives += item.amount;
          }
          if (item.type == 'بدلات') {
            obj.suits += item.amount;
          }
          if (item.type == 'مكافئات') {
            obj.rewards += item.amount;
          }
          if (item.type == 'الراتب المتغير') {
            obj.changed_salary += item.amount;
            obj.calc_salary += item.amount;
          }
        });

        this.subtraction.forEach((item) => {
          if (item.type == 'خصومات') {
            obj.rival += item.amount;
          }
          if (item.type == 'غياب') {
            obj.absence += item.amount;
            obj.absence_sub += Number(((obj.fixed_salary / 30) * item.amount).toFixed(2));
          }
        });

        this.advance_payments.forEach((item) => {
          if (item.type == 'سلف') {
            obj.advance_payment += item.amount;
          }
        });

        obj.total_merit = obj.changed_salary + obj.incentives + obj.suits + obj.rewards + result.fixed_salary;
        obj.total_sub = Number((obj.rival + obj.absence_sub + obj.advance_payment).toFixed(2));
        obj.net_total = obj.total_merit - obj.total_sub;
        this.employee = obj;
        this.isLoading = false;
        if (this.lastSheetData) {
          this.applySheetData(this.lastSheetData);
        }
      },
      error: () => {
        this.isLoading = false;
        this.loadError = 'تعذر تحميل بيانات الموظف';
      },
    });
  }

  onMonthChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.applyMonthValue(target.value);
    this.search();
  }

  onDateFromChange(event: Event) {
    const val = (event.target as HTMLInputElement)?.value;
    if (!val) {
      return;
    }
    this.dateFrom = val;
  }

  onDateToChange(event: Event) {
    const val = (event.target as HTMLInputElement)?.value;
    if (!val) {
      return;
    }
    this.dateTo = val;
  }

  private applyMonthValue(value: string) {
    if (!value) {
      return;
    }
    this.currentMonthValue = value;
    const parsed = parseYearMonthValue(value);
    if (!parsed) {
      return;
    }
    this.year = parsed.year;
    this.month = parsed.month;
    const period = fingerprintPeriodForMonth(parsed.year, parsed.month);
    this.dateFrom = period.dateFrom;
    this.dateTo = period.dateTo;
  }

  private lastSheetData: any = null;

  dataPrint:any = {};
  print() {
    this.dataPrint = {
      ...this.employee,
      merits: this.merits || [],
      subtraction: this.subtraction || [],
      advance_payments: this.advance_payments || [],
      currentMonthValue: this.currentMonthValue,
      currentDateValue: this.currentDateValue,
    };
  }

  handleDataEvent(data:any) {
    this.lastSheetData = data;
    if (!this.employee?.name && !this.employee?.code) {
      return;
    }
    this.applySheetData(data);
  }

  private applySheetData(data: any) {
    if (!this.employee) {
      this.employee = {};
    }
    this.employee['merits']=this.merits;
    this.employee['subtraction']=this.subtraction;
    this.employee['advance_payments']=this.advance_payments;
    this.employee['actualHours']=data.actualHours;
    this.employee['fingerprintHours']=data.fingerprintHours;
    this.employee['differnceSalary']=data.differnceSalary;
    this.employee['fixedSalary']=data.fixedSalary;
    this.employee['holidayDays']=data.holidayDays;
    this.employee['hourPrice']=data.hourPrice;
    this.employee['dayHours']=data.dayHours;
    this.employee['formulaText']=data.formulaText;
    this.employee['overtimeHourRate']=data.overtimeHourRate;
    this.employee['tableData']=data.tableData;
    this.employee['totalActualHoursSalary']=data.totalActualHoursSalary;
    this.employee['totalHours']=data.totalHours;
    this.employee['hoursDifferenceStr']=data.hoursDifferenceStr?.split('-')[1] || data.hoursDifferenceStr;
    if (Array.isArray(data.merits)) {
      this.merits = data.merits;
    }
    if (Array.isArray(data.subtractions)) {
      this.subtraction = data.subtractions;
    }
    if (Array.isArray(data.advancePayments)) {
      this.advance_payments = data.advancePayments;
    }
    if (data.monthAccount) {
      this.employee['total_merit'] = data.monthAccount.totalMerit;
      this.employee['total_sub'] = data.monthAccount.totalSub;
      this.employee['net_total'] = data.monthAccount.netTotal;
      this.employee['differnceSalary'] = data.monthAccount.hoursDeduction;
      this.employee['overtimeFromHours'] = data.monthAccount.overtimeFromHours;
      this.employee['overtimeHoursLabel'] = data.monthAccount.overtimeHoursLabel;
      this.employee['lateHoursLabel'] = data.monthAccount.lateHoursLabel;
      this.employee['extraDaysCount'] = data.monthAccount.extraDaysCount;
      this.employee['extraDaysAmount'] = data.monthAccount.extraDaysAmount;
      this.employee['extraHoursFromMerits'] = data.monthAccount.extraHoursFromMerits;
      this.employee['extraHoursMeritsAmount'] = data.monthAccount.extraHoursMeritsAmount;
      this.employee['deductionDaysCount'] = data.monthAccount.deductionDaysCount;
      this.employee['deductionDaysAmount'] = data.monthAccount.deductionDaysAmount;
      this.employee['extraDeductionDaysCount'] = data.monthAccount.extraDeductionDaysCount;
      this.employee['extraDeductionDaysAmount'] = data.monthAccount.extraDeductionDaysAmount;
      this.employee['extraDeductionHours'] = data.monthAccount.extraDeductionHours;
      this.employee['extraDeductionHoursAmount'] = data.monthAccount.extraDeductionHoursAmount;
      this.employee['otherRival'] = data.monthAccount.otherRival;
      this.employee['otherIncentives'] = data.monthAccount.otherIncentives;
      this.employee['halfDayBonusCount'] = data.monthAccount.halfDayBonusCount;
      this.employee['halfDayBonusAmount'] = data.monthAccount.halfDayBonusAmount;
      this.employee['monthAccount'] = data.monthAccount;
      return;
    }

    this.employee['total_merit'] = this.employee['changed_salary']+ this.employee['incentives']+this.employee['suits'] +this.employee['rewards'] + this.employee['fixed_salary'];
    if (this.employee.acc_no) {
      if(data.totalActualHoursSalary > this.employee['fixed_salary']){
        this.employee['total_merit'] = this.employee['total_merit'] + (data.totalActualHoursSalary - this.employee['fixed_salary']);
      }

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
