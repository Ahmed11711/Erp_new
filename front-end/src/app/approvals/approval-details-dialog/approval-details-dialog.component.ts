import { Component, Inject } from '@angular/core';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { DialogPayMoneyForSupplierComponent } from 'src/app/suppliers/dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import { ApproveService } from '../services/approve.service';

@Component({
  selector: 'app-approval-details-dialog',
  templateUrl: './approval-details-dialog.component.html',
  styleUrls: ['./approval-details-dialog.component.css']
})
export class ApprovalDetailsDialogComponent {

  tableData:any[]=[];

  constructor(public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any , private approvalService:ApproveService ) {}

  ngOnInit() {
    console.log(this.data.data);

    let originalDetails = { ...this.data.data.details };
    this.tableData = [originalDetails]
    let column_values = this.data.data.column_values;
    let details = { ...this.data.data.details };

    for (let key in column_values) {
      if (column_values.hasOwnProperty(key)) {
        details[key] = column_values[key];
      }
    }

    if (this.data?.data?.table_name == 'كشف الحضور والانصراف') {
      this.tableData.push(details);
    }

    if (this.data?.data?.table_name == 'كشف الحضور والانصراف') {
      this.fingerPrintSheet(this.tableData);
    }


  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

  sendApprovelStatus(id , status){
    this.approvalService.approvalStatus({id,status}).subscribe((res:any)=>{
      if (res) {
        this.onCloseClick();
        this.data.refreshData();
      }
    })
  }

  fingerPrintSheet(res){
    let formatedData:any =[];
    let fixedSalary = res[0].employee.fixed_salary;
    let workingHourPerDay = 8;
    let hour = '08:00';
    if (res.working_hours) {
      workingHourPerDay = res.working_hours;
      hour = '09:00';
    }

    let dayHours = workingHourPerDay;
    let totalHours = workingHourPerDay * 60;
    let actualTotalMinutesPerMonth = 0;
    let hourPrice = fixedSalary/30/dayHours
    
    // Check if there are any fingerprints
    const hasFingerPrints = this.tableData && this.tableData.length > 0;
    
    this.tableData.forEach(elm=>{
      elm.times = JSON.parse(elm.times.replace(/\\/g, ''));
      elm['working_hours'] = workingHourPerDay;
      let holidayDays = this.holidayDaysFn(res[0].date)
      let holiday = holidayDays.find(elm2 => elm2 == elm.date);
      if (holiday) {
        elm['holiday'] = true;
      }
      
      if (hasFingerPrints) {
        let actualTotalMinutes = 0;
        hour = this.convertMinutesToHours(totalHours);
        let [hours, minutes] = elm.hours.split(':').map(Number);
        actualTotalMinutes += hours * 60 + minutes;
        actualTotalMinutesPerMonth += hours * 60 + minutes;
        if (elm.is_overTime_removed) {
          actualTotalMinutesPerMonth -= (hours * 60 + minutes )-(60 * dayHours);
        }

        if (elm.hours_permission) {
          let [hours2, minutes2] = elm.hours_permission.split(':').map(Number);
          actualTotalMinutesPerMonth += hours2 * 60 + minutes2;
        }

        if (elm.absence_deduction) {
          actualTotalMinutesPerMonth -= dayHours * 60 * Number(elm.absence_deduction - 1);
        }

        let hoursDifference = actualTotalMinutes - totalHours;

        let hoursDifferenceStr: string;

        if (hoursDifference >= 0) {
          hoursDifferenceStr = this.convertMinutesToHours(hoursDifference);
          let salary = hoursDifference/60*hourPrice*1.5;
          elm['salary_type']=salary;
          elm['salary_type2']='حافز';
        } else {
          hoursDifferenceStr = "-" + this.convertMinutesToHours(-hoursDifference);
          let salary = hoursDifference/60*hourPrice;
          if (elm.absence_deduction) {
            salary = salary * Number(elm.absence_deduction);
          }
          if (elm.hours_permission) {
            let [hours, minutes] = elm.hours_permission.split(':').map(Number);
            salary += ((hours * 60 + minutes)/60 * hourPrice);
          }
          elm['salary_type']=salary * -1;
          if (salary == 0) {
            elm['salary_type']=salary;
          }

          elm['salary_type2']='خصم';
          if (holiday && elm.check_in !== elm.check_out) {
            salary = actualTotalMinutes/60*hourPrice*1.5;
            elm['salary_type']=salary;
            elm['salary_type2']='حافز';
          }
        }
        elm['hoursDifference'] = hoursDifferenceStr;
        if (holiday && elm.check_in !== elm.check_out) {
          elm['hoursDifference'] = elm.hours;
        }
      }
      
      formatedData.push(elm);
    });
    this.tableData = formatedData
    console.log(this.tableData);

  }

  holidayDaysFn(month) {
    let holidayDays:any = [];
    const [year, monthStr] = month.split('-');
    const yearInt = parseInt(year, 10);
    const monthInt = parseInt(monthStr, 10) - 1;
    const daysInMonth = new Date(yearInt, monthInt + 1, 0).getDate();

    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(yearInt, monthInt, day);

      if (date.toDateString().startsWith('Fri')) {
        const day = ('0' + date.getDate()).slice(-2);
        const month = ('0' + (date.getMonth() + 1)).slice(-2);
        const year = date.getFullYear();
        const formattedDate = `${year}-${month}-${day}`;
        holidayDays.push(formattedDate);
      }
    }
    return holidayDays;
  }

  convertMinutesToHours(minutes: number): string {
    let h = Math.floor(minutes / 60);
    let m = minutes % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
  }

}
