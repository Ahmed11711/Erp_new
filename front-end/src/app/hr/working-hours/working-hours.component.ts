import { Component, OnInit, Renderer2 } from '@angular/core';
import { FormGroup, FormControl } from '@angular/forms';
import * as XLSX from 'xlsx';
import { EmployeeService } from '../services/employee.service';
import Swal from 'sweetalert2';
import { Router } from '@angular/router';
import { DatePipe } from '@angular/common';


@Component({
  selector: 'app-working-hours',
  templateUrl: './working-hours.component.html',
  styleUrls: ['./working-hours.component.css'],
  providers: [DatePipe]
})
export class WorkingHoursComponent implements OnInit{
  sheetData:any[]=[];
  data:any[]=[];
  selectedFile: any;
  employees:any[]=[];
  days:any[]=[];
  monthDays:any[]=[];

  btnShowForm:boolean=false;

  currentMonthValue!:any
  previousMonth!:any
  month!:any
  year!:any

  constructor(private employeeService:EmployeeService , private route:Router, private datePipe: DatePipe , private renderer: Renderer2){
    const today = new Date();
    this.year = today.getFullYear();
    this.month = today.getMonth();
    this.currentMonthValue = `${this.year}-${this.month.toString().padStart(2, '0')}`;
    this.previousMonth = new Date(today.getFullYear(), today.getMonth() - 1);
  }

  ngOnInit(): void {
    this.getEmpDataPerMonth();
  }

  getEmpDataPerMonth(){
    this.employeeService.getEmpsDataPerMonth({ month: `${this.currentMonthValue}` }).subscribe(res => {
      res.forEach(elm => {
        elm['month'] = this.currentMonthValue;
        let hourPerDay = elm.working_hours == 9 ? 9 : 8;
        let totalHours = 26 * hourPerDay * 60;
        let actualTotalMinutes = 0;
        elm['totalHours'] = this.convertMinutesToHours(totalHours);
        if (elm.finger_print.length > 0) {

          elm.finger_print.forEach(elm2 => {
            if (elm2.hours) {
              let [hours, minutes] = elm2.hours.split(':').map(Number);
              actualTotalMinutes += hours * 60 + minutes;
              if (elm2.is_overTime_removed) {
                actualTotalMinutes -= (hours * 60 + minutes )-(60 * hourPerDay);
              }
            }

            if (elm2.hours_permission) {
              let [hours2, minutes2] = elm2.hours_permission.split(':').map(Number);
              actualTotalMinutes += hours2 * 60 + minutes2;
            }
            if (elm2.absence_deduction) {
              actualTotalMinutes -= hourPerDay * 60 * Number(elm2.absence_deduction - 1);
            }
          });

          let hoursDifference = actualTotalMinutes - totalHours;

          let hoursDifferenceStr: string;
          if (hoursDifference >= 0) {
            hoursDifferenceStr = this.convertMinutesToHours(hoursDifference);
          } else {
            hoursDifferenceStr = "-" + this.convertMinutesToHours(-hoursDifference);
          }
          elm['hoursDifference'] = hoursDifferenceStr;
          elm['actualTotalHours'] = this.convertMinutesToHours(actualTotalMinutes);
        }
      });
      res.forEach(elm=>{
        elm['selected']=false;
      })
      this.employees = res;
    });
  }

  convertMinutesToHours(minutes: number): string {
    let h = Math.floor(minutes / 60);
    let m = minutes % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
  }

  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.readExcel();
  }

  readExcel() {
    this.sheetData = [];
    this.days = [];
    this.data = [];
    if (this.selectedFile) {
      const fileReader = new FileReader();
      fileReader.onload = (e: any) => {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];
        const excelData:any = XLSX.utils.sheet_to_json(worksheet, { raw: true });

        if (excelData[0]['AC-No.'] && excelData[0]['Time']) {
          this.sheetData= excelData.map((item:any) => {
            let dateTime = new Date(item.Time);
            let time = dateTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
            let fullDate = dateTime.toISOString();
            return {
                "acc_no": item["AC-No."],
                "date": fullDate.split('T')[0],
                "hour": time,
                "iso_date": fullDate,
                "state": item['State'],
            };
          });
          this.days = [...new Set(this.sheetData.map((item:any) => item.date))].sort((a: string, b: string) => new Date(a).getTime() - new Date(b).getTime());
          if (this.sheetData[0].date) {
            const year = parseInt(this.sheetData[0].date.split('-')[0]);
            const month = parseInt(this.sheetData[0].date.split('-')[1]);
            const daysInMonth = new Date(year, month, 0).getDate();
            this.monthDays = [];
            for (let day = 1; day <= daysInMonth; day++) {
                const dayString = day < 10 ? `0${day}` : day;
                this.monthDays.push(`${year}-${month < 10 ? '0' + month : month}-${dayString}`);
            }
          }
        } else {
          let input:any = document.getElementById('fileInput');
          input.value = '';
          this.selectedFile = undefined;
          Swal.fire({
            icon:'error',
            title:'ملف غير صحيح'
          });
        }
      };
      fileReader.readAsArrayBuffer(this.selectedFile);
    }
  }

  async submitform(){
    this.data = [];
    this.sheetData = this.sheetData.filter(elm => this.monthDays.includes(elm.date));
    await this.days.forEach(item =>{
      this.employees.forEach(elm => {
        let data = {};
        data['times']=[];
        this.sheetData.forEach(elm2 => {
          if (elm2.date == item) {
            if(elm.acc_no == elm2.acc_no){
              data['acc_no'] = elm.acc_no;
              data['employee_id'] = elm.id;
              let editedNight = false;
              if (!data['check_in']) {
                let night = false;
                let index;
                if (elm2.state == 'C/Out' ) {
                  let dateObj = new Date(elm2.date);
                  dateObj.setDate(dateObj.getDate() - 1);
                  let dateBefore = dateObj.toISOString().split('T')[0];
                  let emp = this.data.find(elm=> elm.acc_no == elm2.acc_no && elm.date == dateBefore);
                  index = this.data.findIndex(elm=> elm.acc_no == elm2.acc_no && elm.date == dateBefore);
                  if (emp) {
                    const empTimeOut:any = new Date(emp.time_out);
                    const elm2IsoDate:any = new Date(elm2.iso_date);
                    const diffMin = (elm2IsoDate - empTimeOut) / (1000 * 60);
                    if (emp.check_in == emp.check_out || diffMin < 60) {
                      night = true;
                    }
                  }
                }

                if (elm2.state == 'C/Out' && night) {
                  this.data[index].check_out = elm2.hour;
                  this.data[index].time_out = elm2.iso_date;
                  let startDate:any = new Date(this.data[index].time_in);
                  let endDate:any = new Date(this.data[index].time_out);
                  let differenceInMilliseconds = endDate - startDate;
                  let differenceInMinutes = Math.floor(differenceInMilliseconds / (1000 * 60));
                  let hours = Math.floor(differenceInMinutes / 60);
                  let minutes = differenceInMinutes % 60;
                  let formattedHours = String(hours).padStart(2, '0');
                  let formattedMinutes = String(minutes).padStart(2, '0');
                  this.data[index].hours = `${formattedHours}:${formattedMinutes}`;
                  editedNight = true;
                  if (!this.data[index].times.includes(elm2.iso_date)) {
                    this.data[index].times.push(elm2.iso_date);
                  }
                }
                if (!editedNight) {
                  data['iso_date'] = elm2.iso_date;
                  data['date'] = elm2.date;
                  data['check_in'] = elm2.hour;
                  data['time_in'] = elm2.iso_date;
                }
              }
              if (!editedNight) {
                if (data['check_in'] && data['iso_date'] >= elm2.iso_date) {
                  data['check_in'] = elm2.hour;
                  data['time_in'] = elm2.iso_date;
                }
                if (!data['check_out']) {
                  data['check_out'] = elm2.hour;
                  data['time_out'] = elm2.iso_date;
                }
                if (data['check_out'] && data['iso_date'] <= elm2.iso_date) {
                  data['check_out'] = elm2.hour;
                  data['time_out'] = elm2.iso_date;
                }
                if (data['check_in'] && data['check_out']) {
                  if (!data['times'].includes(elm2.iso_date)) {
                    data['times'].push(elm2.iso_date);
                  }
                  let startDate:any = new Date(data['time_in']);
                  let endDate:any = new Date(data['time_out']);
                  let differenceInMilliseconds = endDate - startDate;
                  let differenceInMinutes = Math.floor(differenceInMilliseconds / (1000 * 60));
                  let hours = Math.floor(differenceInMinutes / 60);
                  let minutes = differenceInMinutes % 60;
                  let formattedHours = String(hours).padStart(2, '0');
                  let formattedMinutes = String(minutes).padStart(2, '0');
                  data['hours'] = `${formattedHours}:${formattedMinutes}`;
                }
              }
            }
          }
        })
        if (data['employee_id'] && data['check_in']) {
          this.data.push(data)
        }
      })
    });
    await this.monthDays.forEach(elm => {
      this.employees.forEach(emp =>{
        let isExist = this.data.find(item => item.employee_id == emp.id && item.date === elm);
        if (!isExist && emp.acc_no) {
          this.data.push({
            "acc_no": emp.acc_no,
            "employee_id": emp.id,
            "iso_date": elm+"T05:00:00.000Z",
            "date": elm,
            "check_in": "08:00 AM",
            "time_in": elm+"T05:00:00.000Z",
            "check_out": "08:00 AM",
            "time_out": elm+"T05:00:00.000Z",
            "hours": "00:00",
            "times": [],
          });
        }
      })
    });
    await this.data.sort((a, b) => {
      return a.date.localeCompare(b.date);
    });
    this.data.forEach(elm=>{
      elm.times = JSON.stringify(elm.times);
    })
    let data = {data:this.data};
    if (this.data.length > 0) {
      let month = this.monthDays[0].slice(0, 7);
      await this.employeeService.saveExcelData(data , '').subscribe(res=>{
        if (res) {
          this.getEmpDataPerMonth();
          Swal.fire({
            icon:'success',
            timer:1500,
            showConfirmButton:false
          });
          this.btnShowForm = false;
          this.sheetData = [];
          this.days = [];
          this.data = [];
          let input:any = document.getElementById('fileInput');
          input.value = '';
          this.selectedFile = undefined;
        }
      } , (error) =>{
        if (error) {
          Swal.fire({
            text:'تم رفع بيانات شهر'+month+' من قبل ',
            showCancelButton: true,
            confirmButtonText: 'overwrite',
            cancelButtonText: 'replace',
          }).then((result:any) => {
            console.log(result);

            if (result.isConfirmed) {
              this.employeeService.saveExcelData(data,'overwrite').subscribe(res=>{
                console.log(res);

                if(res){
                  this.getEmpDataPerMonth();
                  Swal.fire({
                    icon:'success',
                    timer:1500,
                    showConfirmButton:false
                  });
                }
              })

            }
            if (result.dismiss == "cancel") {
              this.employeeService.saveExcelData(data,'replace').subscribe(res=>{
                console.log(res);

                if(res){
                  this.getEmpDataPerMonth();
                  Swal.fire({
                    icon:'success',
                    timer:1500,
                    showConfirmButton:false
                  });
                  this.isEmpSelected = false;
                }
              })
            }
            this.btnShowForm = false;
            this.sheetData = [];
            this.days = [];
            this.data = [];
            let input:any = document.getElementById('fileInput');
            input.value = '';
            this.selectedFile = undefined;
          })
        }
      }
      );

    }
  }

  onMonthChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.currentMonthValue = target.value;
    const [year, month] = this.currentMonthValue.split('-');
    this.month = month;
    this.year = +year;
    this.getEmpDataPerMonth();
  }

  openForm(){
    this.btnShowForm = true;
  }

  closeForm(){
    this.btnShowForm = false;
    this.sheetData = [];
    this.days = [];
    this.data = [];
    let input:any = document.getElementById('fileInput');
    input.value = '';
    this.selectedFile = undefined;
  }

  employeeDetails(id:number){
    this.route.navigate([`/dashboard/hr/workinghoursdetails/${id}`]);
  }

  selectVacationBoolean:boolean=false;
  selectvacationDays(){
    this.selectVacationBoolean = true;
  }

  reason!:string;

  range = new FormGroup({
    start: new FormControl<Date | null>(null),
    end: new FormControl<Date | null>(null),
  });

  vacationFn() {
    const start = this.range.value.start;
    const end = this.range.value.end;
    let selectedDateRanges: any[] = [];
    if (start && end) {
      const formattedStart = this.datePipe.transform(start, 'yyyy-MM-dd');
      const formattedEnd = this.datePipe.transform(end, 'yyyy-MM-dd');

      if (formattedStart && formattedEnd) {
        const startDate = new Date(start);
        const endDate = new Date(end);
        const datesArray = this.getDatesBetween(startDate, endDate);
        console.log(datesArray);

        datesArray.forEach(date => {
          const formattedDate = this.datePipe.transform(date, 'yyyy-MM-dd');
          selectedDateRanges.push(formattedDate);
        });

        console.log('Selected date ranges:', selectedDateRanges);
      }
    }

    let data:any[] =[];
    let employees = this.employees.filter(elm => elm.selected == true);
    selectedDateRanges = selectedDateRanges.filter(date => new Date(date).getDay() !== 5);
    selectedDateRanges.forEach(date=>{
      employees.forEach(elm=>{
        let obj={};
        let dayHour = 8;
        let check_out = `04:00 PM`;
        if (elm.working_hours == 9) {
          dayHour = 9;
          check_out = `05:00 PM`;
        }
        obj['employee_id']= elm.id,
        obj['date']= date,
        obj['check_in']= "08:00 AM",
        obj['check_out']= check_out ,
        obj['hours']= `0${dayHour}:00`,
        obj['iso_date']= `${date}T05:00:00.000Z`,
        obj['time_in']= `${date}T05:00:00.000Z`,
        obj['time_out']= `${date}T0${5+dayHour}:00:00.000Z`,
        obj['vacation']= true,
        obj['vacation_reason']= this.reason,
        data.push(obj);
      })
    })
    console.log(selectedDateRanges);

    this.employeeService.updateFingerPrintSheet({data}).subscribe(res=>{
      if (res) {
        this.cancelVac();
        this.getEmpDataPerMonth();
        Swal.fire({
          icon:'success',
          timer:1500,
          showConfirmButton:false
        })
      }
    })
  }

  getDatesBetween(startDate: Date, endDate: Date): Date[] {
    const dates:any = [];
    let currentDate = new Date(startDate);

    while (currentDate <= endDate) {
      dates.push(new Date(currentDate));
      currentDate.setDate(currentDate.getDate() + 1);
    }

    return dates;
  }


  isEmpSelected:boolean=false;
  selectEmp(e){
    if (e.target.id == 'selectAll') {
      this.employees.forEach(elm=>{
        elm.selected = e.target.checked;
      })
    }
    if (Number(e.target.id) >= 0) {
      this.employees[e.target.id].selected = e.target.checked;
    }
    this.isEmpSelected = this.employees.some(elm=>elm.selected);
  }

  cancelVac(){
    this.isEmpSelected=false;
    this.selectVacationBoolean=false;
    this.range.value.start = null;
    this.range.value.end = null;
    this.employees.forEach(elm=>{
      elm.selected = false;
    })
    const checkbox = document.getElementById('selectAll') as HTMLInputElement;
    this.renderer.setProperty(checkbox, 'checked', false);
  }
}
