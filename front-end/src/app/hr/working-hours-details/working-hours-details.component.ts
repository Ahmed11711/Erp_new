import { Component, EventEmitter, Input, OnInit, Output, SimpleChanges } from '@angular/core';
import { EmployeeService } from '../services/employee.service';
import { ActivatedRoute, Router } from '@angular/router';
import Swal from 'sweetalert2';
import { AuthService } from 'src/app/auth/auth.service';

@Component({
  selector: 'app-working-hours-details',
  templateUrl: './working-hours-details.component.html',
  styleUrls: ['./working-hours-details.component.css']
})
export class WorkingHoursDetailsComponent implements OnInit {
  @Input() dateFromEmp!: string;
  @Output() dataEvent = new EventEmitter<{ tableData: any[], holidayDays: any[], totalHours: string, actualHours: string, hoursDifferenceStr: string, fixedSalary: number, hourPrice: number, totalActualHoursSalary: number, differnceSalary: number }>();
  id: any;
  currentMonthValue!: any
  month!: any
  year!: any
  name!: any;
  tableData: any[] = [];
  holidayDays: any[] = [];
  filterDay!: string;
  url!: string;
  user!: string;
  is_overTime_removed!: boolean;

  constructor(private employeeService: EmployeeService, private route: ActivatedRoute, private authService: AuthService) {
    const today = new Date();
    // Get previous month as default
    const previousMonth = new Date(today.getFullYear(), today.getMonth() - 1);
    this.year = previousMonth.getFullYear();
    this.month = previousMonth.getMonth() + 1; // getMonth() returns 0-11, so add 1
    this.currentMonthValue = `${this.year}-${this.month.toString().padStart(2, '0')}`;
    this.id = this.route.snapshot.params['id'];
    this.holidayDaysFn(this.currentMonthValue);
    this.url = this.route.url['_value'][0]['path'];

  }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    this.getEmpDataPerMonth();
  }

  ngOnChanges(changes: SimpleChanges): void {

    if (changes.dateFromEmp) {
      this.currentMonthValue = changes.dateFromEmp.currentValue;
      this.holidayDaysFn(this.currentMonthValue);
      this.getEmpDataPerMonth();
    }
  }
  changedSalary!: number;
  salaryType!: string;
  totalHours!: string;
  actualHours!: string;
  hoursDifferenceStr!: string;
  dayHours!: number;
  getEmpDataPerMonth() {
    this.tableData = [];
    let param = {
      month: this.currentMonthValue
    }
    if (this.filterDay) {
      param['filterDay'] = this.filterDay;
      param['dayHours'] = '08:00';
      if (this.dayHours == 9) {
        param['dayHours'] = '09:00';
      }
    }
    this.employeeService.getEmpDataPerMonth(this.id, param).subscribe(res => {
      console.log('getEmpDataPerMonth details response:', res);
      console.log('finger_print sample:', res?.finger_print?.slice?.(0, 5));
      this.tableData = [];
      this.name = res.name;
      this.fixedSalary = res.fixed_salary;
      let workingHourPerDay = 8;
      let hour = '08:00';
      if (res.working_hours) {
        workingHourPerDay = res.working_hours;
        hour = '09:00';
      }
      this.salaryType = res.salary_type;
      
      // Check if there are any fingerprints for this month
      const hasFingerPrints = res.finger_print && res.finger_print.length > 0;
      
      if (res.salary_type == "متباين") {
        if (res.merits) {
          this.is_overTime_removed = res.finger_print.some(elm => elm.is_overTime_removed == null && elm.hours > hour);
          this.changedSalary = res.merits.filter(elm => elm.type === "الراتب المتغير").reduce((acc, elm) => acc + elm.amount, 0);
        }
      }
      this.dayHours = workingHourPerDay;
      let totalHours = workingHourPerDay * 60;
      let totalHoursPerMonth = workingHourPerDay * 26 * 60;
      let actualTotalMinutesPerMonth = 0;
      this.totalHours = this.convertMinutesToHours(totalHoursPerMonth);
      this.hourPrice = this.fixedSalary / 30 / this.dayHours

      // --- Generate All Days of Month Logic ---
      const [yearStr, monthStr] = this.currentMonthValue.split('-');
      const year = parseInt(yearStr, 10);
      const monthIndex = parseInt(monthStr, 10) - 1;
      const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

      for (let d = 1; d <= daysInMonth; d++) {
        const currentDate = new Date(year, monthIndex, d);
        // Format date as YYYY-MM-DD manually to avoid timezone issues or use a helper
        const dString = String(d).padStart(2, '0');
        const mString = String(monthIndex + 1).padStart(2, '0');
        const dateStr = `${year}-${mString}-${dString}`;

        // Try to find existing record
        let elm = res.finger_print.find(r => r.date === dateStr);

        // If not found, create a default "missing" object
        if (!elm) {
          elm = {
            id: null, // No ID yet
            date: dateStr,
            check_in: '08:00 AM',
            check_out: '08:00 AM',
            hours: '00:00',
            times: '[]', // mocked JSON string
            employee_id: this.id, // Assuming this.id is correct employee ID
            // Add other fields needed by template to avoid creating undefined errors
            hours_permission: null,
            absence_deduction: null,
            vacation: false,
            reviewed: false,
            is_overTime_removed: false
          };
        }

        // --- Existing Logic Processing (Adapted) ---

        // Ensure times is parsed if it's a string (API or our mock)
        if (typeof elm.times === 'string') {
          try {
            elm.times = JSON.parse(elm.times.replace(/\\/g, ''));
          } catch (e) {
            elm.times = [];
          }
        }

        elm['working_hours'] = workingHourPerDay;

        let holiday = this.holidayDays.find(hDate => hDate == elm.date);
        if (holiday) {
          elm['holiday'] = true;
          // Ensure check_in/check_out equal for holiday visual logic if missing
          if (elm.hours === '00:00') {
            elm.check_in = elm.check_in || '08:00 AM';
            elm.check_out = elm.check_in;
          }
        } else {
          elm['holiday'] = false;
        }

        this.isReviewed = elm.reviewed;

        // Normalize vacation
        if (elm.vacation === 1 || elm.vacation === '1' || elm.vacation === 'true') {
          elm.vacation = true;
        }

        if (elm.vacation) {
          elm.vacation_reason = elm.vacation_reason || elm.vacation_reason_en || 'أجازة';
          elm.check_in = elm.check_in || '08:00 AM';
          elm.check_out = elm.check_out || elm.check_in || '08:00 AM';
          elm.hours = '00:00';
          elm.hoursDifference = '00:00';
        }

        // Normalize absence/missing data
        if (!elm.holiday && !elm.vacation && (!elm.hours || elm.hours === '00:00')) {
          elm.check_in = elm.check_in || '08:00 AM';
          elm.check_out = elm.check_out || elm.check_in || '08:00 AM';
          elm.hours = '00:00';
        }

        // Calculate minutes - only if there are fingerprints
        if (hasFingerPrints) {
          let [hours, minutes] = (elm.hours || '00:00').split(':').map(Number);

          // Accumulate totals
          actualTotalMinutesPerMonth += hours * 60 + minutes;

          if (elm.is_overTime_removed) {
            actualTotalMinutesPerMonth -= (hours * 60 + minutes) - (60 * this.dayHours);
          }

          if (elm.hours_permission) {
            let [hours2, minutes2] = elm.hours_permission.split(':').map(Number);
            actualTotalMinutesPerMonth += hours2 * 60 + minutes2;
          }

          if (elm.absence_deduction) {
            actualTotalMinutesPerMonth -= this.dayHours * 60 * Number(elm.absence_deduction - 1);
          }

          let actualTotalMinutes = hours * 60 + minutes;
          let hoursDifference = actualTotalMinutes - totalHours;
          let hoursDifferenceStr: string;

          if (hoursDifference >= 0) {
            hoursDifferenceStr = this.convertMinutesToHours(hoursDifference);
            let salary = hoursDifference / 60 * this.hourPrice * 1.5;
            elm['salary_type'] = salary;
            elm['salary_type2'] = 'حافز';
          } else {
            hoursDifferenceStr = "-" + this.convertMinutesToHours(-hoursDifference);
            let salary = hoursDifference / 60 * this.hourPrice;

            if (elm.absence_deduction) {
              salary = salary * Number(elm.absence_deduction);
            }
            if (elm.hours_permission) {
              let [hp, mp] = elm.hours_permission.split(':').map(Number);
              salary += ((hp * 60 + mp) / 60 * this.hourPrice);
            }

            elm['salary_type'] = salary * -1;
            if (salary == 0) {
              elm['salary_type'] = salary;
            }

            elm['salary_type2'] = 'خصم';

            if (elm.holiday && elm.check_in !== elm.check_out) {
              let workedMins = hours * 60 + minutes;
              salary = workedMins / 60 * this.hourPrice * 1.5;
              elm['salary_type'] = salary;
              elm['salary_type2'] = 'حافز';
            }
          }

          elm['hoursDifference'] = hoursDifferenceStr;
          if (elm.holiday && elm.check_in !== elm.check_out) {
            elm['hoursDifference'] = elm.hours;
          }
        }

        this.tableData.push(elm);
      } // end for loop

      // total for month logic ...
      if (this.tableData.length > 0 && (!param['filterDay'] || param['filterDay'] == 'all')) {
        // Adjust logic if necessary based on new fully populated tableData
        // Previously: actualTotalMinutesPerMonth = actualTotalMinutesPerMonth - ((this.tableData.length-this.holidayDays.length-26) * this.dayHours*60);
        // The previous logic seems to assume 26 days is standard. 
        // With full days + logic, let's keep it as is for now unless user complains about totals.
        actualTotalMinutesPerMonth = actualTotalMinutesPerMonth - ((this.tableData.length - this.holidayDays.length - 26) * this.dayHours * 60);
      }

      let totalDiff = actualTotalMinutesPerMonth - totalHoursPerMonth;

      if (totalDiff >= 0) {
        this.hoursDifferenceStr = this.convertMinutesToHours(totalDiff);
      } else {
        this.hoursDifferenceStr = "-" + this.convertMinutesToHours(-totalDiff);
      }

      // If no fingerprints, leave actualHours and hoursDifferenceStr empty
      if (hasFingerPrints) {
        this.actualHours = this.convertMinutesToHours(actualTotalMinutesPerMonth);
      } else {
        this.actualHours = ''; // Empty string instead of calculated value
        this.hoursDifferenceStr = ''; // Empty string instead of calculated value
      }

      this.calcSalary();
    });
  }

  fixedSalary: number = 0;
  hourPrice: number = 0;
  totalActualHoursSalary: number = 0;
  differnceSalary: number = 0;
  calcSalary() {
    // If no actual hours (no fingerprints), set values to 0
    if (!this.actualHours) {
      this.totalActualHoursSalary = 0;
      this.differnceSalary = 0;
      this.dataEvent.emit({ tableData: this.tableData, holidayDays: this.holidayDays, totalHours: this.totalHours, actualHours: this.actualHours, hoursDifferenceStr: this.hoursDifferenceStr, fixedSalary: this.fixedSalary, hourPrice: this.hourPrice, totalActualHoursSalary: this.totalActualHoursSalary, differnceSalary: this.differnceSalary });
      return;
    }
    
    // this.hourPrice = this.fixedSalary/30/this.dayHours;
    let [hours, minutes] = this.actualHours.split(':').map(Number);
    let actualHours = hours * 60 + minutes;
    this.totalActualHoursSalary = this.hourPrice * actualHours / 60;
    if (actualHours !== 0) {
      this.totalActualHoursSalary = this.totalActualHoursSalary + (this.hourPrice * (this.dayHours * 4));
    }
    let [hours2, minutes2] = this.totalHours.split(':').map(Number);
    if (actualHours > hours2 * 60 + minutes2) {
      this.totalActualHoursSalary = this.fixedSalary + ((actualHours - hours2 * 60 + minutes2) / 60 * this.hourPrice * 1.5);
    }
    this.differnceSalary = this.totalActualHoursSalary - this.fixedSalary;
    if (this.is_overTime_removed && this.changedSalary > 0) {
      this.autoRemoveOverTime();
    }
    this.dataEvent.emit({ tableData: this.tableData, holidayDays: this.holidayDays, totalHours: this.totalHours, actualHours: this.actualHours, hoursDifferenceStr: this.hoursDifferenceStr, fixedSalary: this.fixedSalary, hourPrice: this.hourPrice, totalActualHoursSalary: this.totalActualHoursSalary, differnceSalary: this.differnceSalary });
  }

  convertMinutesToHours(minutes: number): string {
    let h = Math.floor(minutes / 60);
    let m = minutes % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
  }

  onMonthChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.currentMonthValue = target.value;
    const [year, month] = this.currentMonthValue.split('-');
    this.month = month;
    this.year = +year;
    this.holidayDaysFn(this.currentMonthValue);
    this.getEmpDataPerMonth();
  }

  permission(e) {
    Swal.fire({
      title: 'عدد ساعات الاذن',
      input: 'text',
      inputValue: e.hoursDifference.replace('-', ''),
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة';
        }
        const regex = /^(0?[0-9]|1[0-9]|2[0-3]):([0-5]?[0-9])$/;
        if (!regex.test(value)) {
          return 'يجب أن تكون القيمة في صيغة "HH:mm"';
        }
        const [inputHours, inputMinutes] = value.split(':');
        const [initialHours, initialMinutes] = e.hoursDifference.replace('-', '').split(':');

        if (parseInt(inputHours, 10) > parseInt(initialHours, 10) ||
          (parseInt(inputHours, 10) === parseInt(initialHours, 10) && parseInt(inputMinutes, 10) > parseInt(initialMinutes, 10))) {
          return '  يجب ألا يتجاوز عدد الساعات المدخلة  ' + e.hoursDifference.replace('-', '');
        }

        const formattedHours = inputHours.length === 1 ? '0' + inputHours : inputHours;
        const formattedMinutes = inputMinutes.length === 1 ? '0' + inputMinutes : inputMinutes;
        const formattedValue = formattedHours + ':' + formattedMinutes;
        console.log(formattedValue);
        this.employeeService.empHoursPermision({ data: { hours_permission: formattedValue, id: e.id } }).subscribe(res => {
          if (res) {
            this.getEmpDataPerMonth();
            if (this.user == 'Admin') {
              Swal.fire({
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              })
            } else {
              Swal.fire({
                icon: 'success',
                title: 'في انتظار موافقة الادمن',
                timer: 2000,
                showConfirmButton: false
              })
            }

          }

        })
        return undefined;
      }
    });
  }

  withOutPermission(e) {
    Swal.fire({
      title: 'نوع الخصم',
      input: 'select',
      inputOptions: {
        '1.5': '1.5',
        '2': '2',
        '3': '3',
      },
      customClass: {
        input: 'text-center w-75 form-control',
      },
      inputPlaceholder: 'اختر قيمة الخصم',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة';
        }
        if (value) {
          this.employeeService.absenceDeduction({ data: { id: e.id, absence_deduction: value } }).subscribe(res => {
            if (res) {
              this.getEmpDataPerMonth();
              if (this.user == 'Admin') {
                Swal.fire({
                  icon: 'success',
                  timer: 2000,
                  showConfirmButton: false
                })
              } else {
                Swal.fire({
                  icon: 'success',
                  title: 'في انتظار موافقة الادمن',
                  timer: 2000,
                  showConfirmButton: false
                })
              }

            }
          });
        }
        return null;
      }
    });


  }

  holidayDaysFn(month) {
    this.holidayDays = [];
    const [year, monthStr] = month.split('-');
    const yearInt = parseInt(year, 10);
    const monthInt = parseInt(monthStr, 10) - 1; // JavaScript months are 0-based
    const daysInMonth = new Date(yearInt, monthInt + 1, 0).getDate();

    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(yearInt, monthInt, day);

      if (date.toDateString().startsWith('Fri')) {
        const day = ('0' + date.getDate()).slice(-2);
        const month = ('0' + (date.getMonth() + 1)).slice(-2);
        const year = date.getFullYear();
        const formattedDate = `${year}-${month}-${day}`;
        this.holidayDays.push(formattedDate);
      }
    }
  }

  filter(e) {
    this.filterDay = e.target.value;
    this.getEmpDataPerMonth();
  }

  isReviewed: boolean = false;
  reviewMonth() {
    this.employeeService.reviewMonth(this.currentMonthValue, this.id).subscribe(res => {
      if (res) {
        this.getEmpDataPerMonth();
        Swal.fire({
          icon: 'success',
          timer: 1500,
          showConfirmButton: false
        })
      }
    })
  }

  isEmpSelected: boolean = false;
  selectEmp(e) {
    if (e.target.id == 'selectAll') {
      this.tableData.forEach(elm => {
        elm.selected = e.target.checked;
      })
    }
    if (Number(e.target.id) >= 0) {
      this.tableData[e.target.id].selected = e.target.checked;
    }
    this.isEmpSelected = this.tableData.some(elm => elm.selected);
  }

  permissionAll() {
    let data = this.tableData.filter(elm => elm.selected == true).map(elm => {
      let hours_permission = elm.hoursDifference.split('-')[1];
      return { hours_permission, id: elm.id }
    });
    Swal.fire({
      title: ' تاكيد ؟',
      showCancelButton: true,
      confirmButtonText: 'نعم',
      cancelButtonText: 'لا',
    }).then((result: any) => {
      if (result.isConfirmed) {
        this.employeeService.empHoursPermisionAll({ data }).subscribe(res => {
          this.isEmpSelected = false;
          if (res) {
            this.getEmpDataPerMonth();
            if (this.user == 'Admin') {
              Swal.fire({
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              })
            } else {
              Swal.fire({
                icon: 'success',
                title: 'في انتظار موافقة الادمن',
                timer: 2000,
                showConfirmButton: false
              })
            }
          }
        })

      }
    })
  }

  removeOverTime() {
    let data = this.tableData.filter(elm => elm.hoursDifference > '00:00').map(elm => {
      let hours_permission = '-' + elm.hoursDifference;
      return { hours_permission, id: elm.id, is_overTime_removed: true }
    });
    Swal.fire({
      title: ' تاكيد ؟',
      showCancelButton: true,
      confirmButtonText: 'نعم',
      cancelButtonText: 'لا',
    }).then((result: any) => {
      if (result.isConfirmed) {
        this.employeeService.empHoursPermisionAll({ data }).subscribe(res => {
          if (res) {
            this.getEmpDataPerMonth();
            this.isEmpSelected = false;
          }
        })

      }
    })
  }

  autoRemoveOverTime() {
    let data = this.tableData.filter(elm => elm.hoursDifference > '00:00').map(elm => {
      let hours_permission = '-' + elm.hoursDifference;
      return { hours_permission, id: elm.id, is_overTime_removed: true }
    });
    this.employeeService.empHoursPermisionAll({ data }).subscribe(res => {
      if (res) {
        this.getEmpDataPerMonth();
        this.isEmpSelected = false;
      }
    })
  }

  setAmount(differnceSalary) {
    if (differnceSalary < 0) {
      Swal.fire({
        title: 'ادخل مبلغ الخصم',
        input: 'number',
        inputPlaceholder: 'المبلغ',
        showCancelButton: true,
        inputValidator: (value: any) => {
          if (!value) {
            return 'يجب ادخال قيمة'
          }
          if (value > Math.abs(differnceSalary)) {
            return ' لا يمكنك ادخال مبلغ اكبر من الخصم الحالى ' + Math.abs(differnceSalary).toFixed(3)
          }
          if (value !== '') {
            // let totalMinutes = Math.floor((Math.abs(differnceSalary) - value) / this.hourPrice * 60);
            let totalMinutes = (Math.abs(differnceSalary) - value) / this.hourPrice * 60;
            let data = this.tableData.filter(elm => elm.hoursDifference < '00:00' && !elm.holiday && elm.salary_type !== 0);
            let changedData: any[] = [];
            data.forEach(elm => {
              const [hours, min] = elm.hoursDifference.split('-')[1].split(':').map(Number);
              let minutesAvailable = hours * 60 + min;

              let currentPermissionMinutes = 0;
              if (elm.hours_permission) {
                const [permHours, permMin] = elm.hours_permission.split(':').map(Number);
                currentPermissionMinutes = permHours * 60 + permMin;
              }

              if (totalMinutes > 0) {
                let newDistribution = Math.min(minutesAvailable - currentPermissionMinutes, totalMinutes);
                currentPermissionMinutes += newDistribution;
                elm.hours_permission = this.convertMinutesToHours(currentPermissionMinutes);
                totalMinutes -= newDistribution;
                changedData.push({ hours_permission: elm.hours_permission, id: elm.id })

              }
            });

            if (totalMinutes > 0) {
              console.log(`Remaining minutes that could not be distributed: ${totalMinutes}`);
            }

            this.employeeService.empHoursPermisionAll({ data: changedData }).subscribe(res => {
              if (res) {
                this.getEmpDataPerMonth();
                this.isEmpSelected = false;
              }
            })
          }
          return undefined
        }
      })
    }
  }

  addCheckOut(e: any) {
    const check_in = e.check_in;
    const inputValue = this.addHoursToTime(check_in, this.dayHours);
    console.log(inputValue);

    Swal.fire({
      html: `<input type="time" id="time-input-${e.id}" value="${inputValue}" class="swal2-input" required>`,
      showCancelButton: true,
      title: `${check_in} وقت الحضور <br> اختر وقت الانصراف؟ `,
      preConfirm: () => {
        const timeInputElement = Swal.getPopup()?.querySelector(`#time-input-${e.id}`) as HTMLInputElement | null;
        if (!timeInputElement || !timeInputElement.value) {
          Swal.showValidationMessage('يجب ادخال قيمة');
          return null;
        }
        return timeInputElement.value;
      }
    }).then((result) => {
      if (result.isConfirmed && result.value) {
        const check_out = result.value; // check_out is in "HH:mm" format

        const [checkInTime, checkInPeriod] = check_in.split(' ');
        const [checkInHour, checkInMinute] = checkInTime.split(':').map(Number);

        // Convert check_in to 24-hour format
        let checkInHour24 = checkInHour % 12; // Convert 12-hour to 24-hour format
        if (checkInPeriod === 'PM') {
          checkInHour24 += 12;
        }

        // Parse check_out (in "HH:mm" format)
        const [checkOutHour, checkOutMinute] = check_out.split(':').map(Number);

        // Create Date objects for check_in and check_out
        const now = new Date();
        const checkInDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), checkInHour24, checkInMinute);
        const checkOutDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), checkOutHour, checkOutMinute);

        if (checkOutDate <= checkInDate) {
          Swal.fire({
            icon: 'error',
            text: 'تاكد من وقت الانصراف',
          });
          return;
        }

        // Calculate the difference in milliseconds
        const diffMs = checkOutDate.getTime() - checkInDate.getTime();

        // Calculate the difference in hours and minutes
        const hours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

        // Format the output as "hh:mm"
        const formattedDifference = `${hours < 10 ? '0' : ''}${hours}:${diffMinutes < 10 ? '0' : ''}${diffMinutes}`;

        // Add the difference to time_in
        const timeInDate = new Date(e.time_in); // time_in in "YYYY-MM-DDTHH:mm:ss.sssZ" format
        const timeOutDate = new Date(timeInDate.getTime() + diffMs);

        // Format time_out as "YYYY-MM-DDTHH:mm:ss.sssZ"
        // const time_out = `${checkOutHour % 12 || 12}:${checkOutMinute < 10 ? '0' : ''}${checkOutMinute} ${checkOutHour >= 12 ? 'PM' : 'AM'}`;
        const time_out = `${(checkOutHour % 12 || 12).toString().padStart(2, '0')}:${checkOutMinute.toString().padStart(2, '0')} ${checkOutHour >= 12 ? 'PM' : 'AM'}`;

        const time_out_iso = timeOutDate.toISOString();

        console.log('Hours between check-in and check-out:', formattedDifference);
        console.log('Check-out in 12-hour format:', time_out);
        console.log('Time out:', time_out_iso);
        const data = {
          check_out: time_out,
          hours: formattedDifference,
          time_out: time_out_iso,
          hours_permission: null
        }
        this.employeeService.addCheckOut(e.id, { data }).subscribe(res => {
          if (res) {
            this.getEmpDataPerMonth();
            if (this.user == 'Admin') {
              Swal.fire({
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              })
            } else {
              Swal.fire({
                icon: 'success',
                title: 'في انتظار موافقة الادمن',
                timer: 2000,
                showConfirmButton: false
              })
            }

          }
        })
      }
    });
  }

  addHoursToTime(check_in, hoursToAdd) {
    // Split the check_in time into components
    const [time, modifier] = check_in.split(' ');
    let [hours, minutes] = time.split(':').map(Number);

    // Convert hours to 24-hour format if necessary
    if (modifier === 'PM' && hours !== 12) {
      hours += 12;
    } else if (modifier === 'AM' && hours === 12) {
      hours = 0;
    }

    // Create a new Date object and set the hours and minutes
    const date = new Date();
    date.setHours(hours, minutes);

    // Add the specified number of hours
    date.setHours(date.getHours() + hoursToAdd);

    // // Format the new time back into the 12-hour format
    let newHours = date.getHours();
    const newMinutes = date.getMinutes().toString().padStart(2, '0');

    return `${newHours}:${newMinutes}`;
  }

  selectCheckIn(e) {
    if (e.times.length > 2) {
      let iso_times = e.times;
      let checkOuts: any[] = [];
      iso_times.forEach(elm => {
        let iso_date: any = new Date(elm);
        let time_out: any = new Date(e.time_out);
        let check_in = new Date(elm).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
        let time = new Date(elm).toLocaleString();
        let differenceInMilliseconds = time_out - iso_date;
        let differenceInMinutes = Math.floor(differenceInMilliseconds / (1000 * 60));
        let hours = this.convertMinutesToHours(differenceInMinutes);
        checkOuts.push({ check_in, time_in: elm, hours, time });
      });
      checkOuts.pop();
      const options = checkOuts.map(elm => elm.time);
      Swal.fire({
        input: 'select',
        inputOptions: options,
        inputPlaceholder: 'اختر وقت الحضور',
        showCancelButton: true,
        inputValidator: (value) => {
          if (!value) {
            return 'يجب ادخال قيمة';
          }
          if (value) {
            let data = checkOuts[value];
            delete data['time'];
            this.employeeService.changeCheckIn(e.id, { data }).subscribe(res => {
              if (res) {
                this.getEmpDataPerMonth();
                if (this.user == 'Admin') {
                  Swal.fire({
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                  })
                } else {
                  Swal.fire({
                    icon: 'success',
                    title: 'في انتظار موافقة الادمن',
                    timer: 2000,
                    showConfirmButton: false
                  })
                }

              }
            })
          }
          return null;
        }
      });
    }
  }

  editCheckIn(e: any) {
    const check_out = e.check_out;

    Swal.fire({
      html: `<input type="time" id="time-input-${e.id}" value="09:00" class="swal2-input" required>`,
      showCancelButton: true,
      title: `تعديل وقت الحضور`,
      preConfirm: () => {
        const timeInputElement = Swal.getPopup()?.querySelector(`#time-input-${e.id}`) as HTMLInputElement | null;
        if (!timeInputElement || !timeInputElement.value) {
          Swal.showValidationMessage('يجب ادخال قيمة');
          return null;
        }
        return timeInputElement.value;
      }
    }).then((result) => {
      if (result.isConfirmed && result.value) {
        const check_in = result.value;

        const [checkOutTime, checkOutPeriod] = check_out.split(' ');
        const [checkOutHour, checkOutMinute] = checkOutTime.split(':').map(Number);

        let checkOutHour24 = checkOutHour % 12;
        if (checkOutPeriod === 'PM') {
          checkOutHour24 += 12;
        }

        const [checkInHour, checkInMinute] = check_in.split(':').map(Number);

        const now = new Date();
        const checkInDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), checkInHour, checkInMinute);
        const checkOutDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), checkOutHour24, checkOutMinute);

        if (checkOutDate < checkInDate) {
          Swal.fire({
            icon: 'error',
            text: 'تاكد من وقت الحضور ',
          });
          return;
        }

        const diffMs = checkOutDate.getTime() - checkInDate.getTime();

        const hours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

        const formattedDifference = `${hours < 10 ? '0' : ''}${hours}:${diffMinutes < 10 ? '0' : ''}${diffMinutes}`;

        const time_in = `${(checkInHour % 12 || 12).toString().padStart(2, '0')}:${checkInMinute.toString().padStart(2, '0')} ${checkInHour >= 12 ? 'PM' : 'AM'}`;

        const dateParts = e.date.split('-');
        const year = parseInt(dateParts[0], 10);
        const month = parseInt(dateParts[1], 10) - 1;
        const day = parseInt(dateParts[2], 10);
        const time_in_iso = new Date(year, month, day, checkInHour, checkInMinute).toISOString();

        const data = {
          check_in: time_in,
          check_out: e.check_out,
          hours: formattedDifference,
          time_in: time_in_iso,
          time_out: e.time_out,
          hours_permission: null
        }

        this.employeeService.editCheckInOrOut(e.id, { data }).subscribe(res => {
          if (res) {
            this.getEmpDataPerMonth();
            if (this.user == 'Admin') {
              Swal.fire({
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              })
            } else {
              Swal.fire({
                icon: 'success',
                title: 'في انتظار موافقة الادمن',
                timer: 2000,
                showConfirmButton: false
              })
            }

          }
        })
      }
    });
  }

  editCheckOut(e: any) {
    const date = new Date(e.time_in);
    const pad = (number) => (number < 10 ? '0' : '') + number;

    const nextDay = new Date(date);
    nextDay.setDate(nextDay.getDate() + 1);
    const formattedMaxDate = `${nextDay.getFullYear()}-${pad(nextDay.getMonth() + 1)}-${pad(nextDay.getDate())}T${pad(nextDay.getHours())}:${pad(nextDay.getMinutes())}`;
    const formattedDate = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;

    Swal.fire({
      html: `<input type="datetime-local" id="time-input-${e.id}" value="${formattedDate}" min="${formattedDate}" max="${formattedMaxDate}" class="swal2-input" required>`,
      showCancelButton: true,
      title: `تعديل وقت الانصراف`,
      preConfirm: () => {
        const timeInputElement = Swal.getPopup()?.querySelector(`#time-input-${e.id}`) as HTMLInputElement | null;
        if (!timeInputElement || !timeInputElement.value) {
          Swal.showValidationMessage('يجب ادخال قيمة');
          return null;
        }
        return timeInputElement.value;
      }
    }).then((result) => {
      if (result.isConfirmed && result.value) {
        const check_out = result.value;
        const checkInDate = new Date(e.time_in);
        const checkOutDate = new Date(check_out);

        if (checkOutDate < checkInDate) {
          Swal.fire({
            icon: 'error',
            text: 'تاكد من وقت الانصراف ',
          });
          return;
        }

        const diffMs = checkOutDate.getTime() - checkInDate.getTime();

        const hours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

        const formattedDifference = `${hours < 10 ? '0' : ''}${hours}:${diffMinutes < 10 ? '0' : ''}${diffMinutes}`;

        const checkOut = `${(checkOutDate.getHours() % 12 || 12).toString().padStart(2, '0')}:${checkOutDate.getMinutes().toString().padStart(2, '0')} ${checkOutDate.getHours() >= 12 ? 'PM' : 'AM'}`;


        const data = {
          check_in: e.check_in,
          check_out: checkOut,
          hours: formattedDifference,
          time_in: e.time_in,
          time_out: checkOutDate.toISOString(),
          hours_permission: null
        }
        console.log(data);

        this.employeeService.editCheckInOrOut(e.id, { data }).subscribe(res => {
          if (res) {
            this.getEmpDataPerMonth();
            if (this.user == 'Admin') {
              Swal.fire({
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              })
            } else {
              Swal.fire({
                icon: 'success',
                title: 'في انتظار موافقة الادمن',
                timer: 2000,
                showConfirmButton: false
              })
            }

          }
        })
      }
    });
  }


}
