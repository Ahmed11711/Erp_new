import { Component, EventEmitter, Input, OnInit, Output, SimpleChanges } from '@angular/core';
import { EmployeeService } from '../services/employee.service';
import { ActivatedRoute, Router } from '@angular/router';
import Swal from 'sweetalert2';
import { AuthService } from 'src/app/auth/auth.service';
import {
  applyNormalShiftTimes,
  convertMinutesToHours,
  diffMsBetween,
  fullDayPermissionSavePayload,
  isFullDayPermission,
  normalizeOvernightFingerPrintRecords,
  parseDatetimeLocalValue,
  parseLocalDateTime,
  resolveCheckOutDate,
  resolveWorkDayHours,
  OVERNIGHT_CHECKOUT_CUTOFF_HOUR,
  toDatetimeLocalValue,
  toTimeInputValue
} from '../utils/fingerprint-hours.utils';

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
      if (res.working_hours) {
        workingHourPerDay = res.working_hours;
      }
      if (res.finger_print?.length) {
        res.finger_print.forEach((r: { working_hours?: number }) => {
          r.working_hours = workingHourPerDay;
        });
        res.finger_print = normalizeOvernightFingerPrintRecords(res.finger_print);
      }
      let hour = '08:00';
      if (res.working_hours) {
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

        const fullDayPermissionEarly = isFullDayPermission(elm.hours_permission, workingHourPerDay)
          && !elm.vacation
          && !this.holidayDays.find(hDate => hDate == elm.date);

        if (fullDayPermissionEarly) {
          applyNormalShiftTimes(elm, workingHourPerDay);
        } else if (!elm.vacation && elm.check_in && elm.check_out && elm.check_in !== elm.check_out) {
          elm.hours = resolveWorkDayHours({ ...elm, working_hours: workingHourPerDay });
        }

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
          const fullDayPermission = isFullDayPermission(elm.hours_permission, workingHourPerDay)
            && !elm.vacation
            && !elm.holiday;

          if (fullDayPermission) {
            applyNormalShiftTimes(elm, workingHourPerDay);
            elm.hoursDifference = '00:00';
            elm.salary_type = 0;
            elm.salary_type2 = 'اذن';
            actualTotalMinutesPerMonth += workingHourPerDay * 60;
          } else {
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

  private permissionSaveExtra(hoursPermission: string): Record<string, unknown> {
    return fullDayPermissionSavePayload(hoursPermission, this.dayHours);
  }

  private showFingerprintMutationSuccess(): void {
    Swal.fire({
      icon: 'success',
      title: 'تم الحفظ',
      text: this.user === 'Admin' ? undefined : 'تم إبلاغ الإدارة بالتعديل',
      timer: 2000,
      showConfirmButton: false,
    });
  }

  /** بيانات السجل — ينشئ سجل غياب تلقائياً في الخادم إذا لم يكن id موجوداً */
  private sheetActionPayload(elm: any, extra: Record<string, unknown> = {}): Record<string, unknown> {
    return {
      id: elm.id ?? null,
      employee_id: elm.employee_id ?? this.id,
      date: elm.date,
      ...extra,
    };
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
        this.employeeService.empHoursPermision({
          data: this.sheetActionPayload(e, {
            hours_permission: formattedValue,
            ...this.permissionSaveExtra(formattedValue),
          })
        }).subscribe(res => {
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
          this.employeeService.absenceDeduction({
            data: this.sheetActionPayload(e, { absence_deduction: value })
          }).subscribe(res => {
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
      return this.sheetActionPayload(elm, {
        hours_permission,
        ...this.permissionSaveExtra(hours_permission),
      });
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
        const check_out = result.value;
        const checkInDate = parseLocalDateTime(e.date, check_in);
        if (!checkInDate) {
          Swal.fire({ icon: 'error', text: 'تعذّر قراءة وقت الحضور' });
          return;
        }

        const [checkOutHour, checkOutMinute] = check_out.split(':').map(Number);
        let checkOutDate = new Date(checkInDate);
        checkOutDate.setHours(checkOutHour, checkOutMinute, 0, 0);

        const diffMs = diffMsBetween(checkInDate, checkOutDate);
        checkOutDate = new Date(checkInDate.getTime() + diffMs);

        const hours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        const formattedDifference = `${String(hours).padStart(2, '0')}:${String(diffMinutes).padStart(2, '0')}`;

        const time_out = `${(checkOutDate.getHours() % 12 || 12).toString().padStart(2, '0')}:${checkOutDate.getMinutes().toString().padStart(2, '0')} ${checkOutDate.getHours() >= 12 ? 'PM' : 'AM'}`;
        const time_out_iso = toDatetimeLocalValue(checkOutDate) + ':00';

        const data = {
          check_out: time_out,
          hours: formattedDifference,
          time_out: time_out_iso,
          hours_permission: null,
          times: JSON.stringify([toDatetimeLocalValue(checkInDate) + ':00', time_out_iso]),
        }
        this.employeeService.addCheckOut(e.id, { data }).subscribe(res => {
          if (res) {
            this.getEmpDataPerMonth();
            this.showFingerprintMutationSuccess();
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
                this.showFingerprintMutationSuccess();
              }
            })
          }
          return null;
        }
      });
    }
  }

  editCheckIn(e: any) {
    const checkInDate = parseLocalDateTime(e.date, e.check_in);
    const defaultTime = checkInDate ? toTimeInputValue(checkInDate) : '09:00';

    Swal.fire({
      html: `<input type="time" id="time-input-${e.id}" value="${defaultTime}" class="swal2-input" required>`,
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
        const [checkInHour, checkInMinute] = check_in.split(':').map(Number);
        const [y, m, d] = e.date.split('-').map(Number);
        const checkInDateNew = new Date(y, m - 1, d, checkInHour, checkInMinute, 0);

        let checkOutDate = resolveCheckOutDate(e.date, e.check_in, e.check_out);
        if (!checkOutDate) {
          Swal.fire({ icon: 'error', text: 'تعذّر قراءة وقت الانصراف' });
          return;
        }

        const diffMs = diffMsBetween(checkInDateNew, checkOutDate);
        if (diffMs <= 0) {
          Swal.fire({ icon: 'error', text: 'تأكد من وقت الحضور' });
          return;
        }

        checkOutDate = new Date(checkInDateNew.getTime() + diffMs);

        const hours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        const formattedDifference = `${String(hours).padStart(2, '0')}:${String(diffMinutes).padStart(2, '0')}`;

        const time_in = `${(checkInHour % 12 || 12).toString().padStart(2, '0')}:${checkInMinute.toString().padStart(2, '0')} ${checkInHour >= 12 ? 'PM' : 'AM'}`;
        const time_in_iso = toDatetimeLocalValue(checkInDateNew) + ':00';

        const data = {
          check_in: time_in,
          check_out: e.check_out,
          hours: formattedDifference,
          time_in: time_in_iso,
          time_out: toDatetimeLocalValue(checkOutDate) + ':00',
          hours_permission: null,
          times: JSON.stringify([time_in_iso, toDatetimeLocalValue(checkOutDate) + ':00']),
        }

        this.employeeService.editCheckInOrOut(e.id, { data }).subscribe(res => {
          if (res) {
            this.getEmpDataPerMonth();
            this.showFingerprintMutationSuccess();
          }
        })
      }
    });
  }

  editCheckOut(e: any) {
    const checkInDate = parseLocalDateTime(e.date, e.check_in);
    const checkOutDate = resolveCheckOutDate(e.date, e.check_in, e.check_out);

    if (!checkInDate || !checkOutDate) {
      Swal.fire({ icon: 'error', text: 'تعذّر قراءة أوقات الحضور والانصراف' });
      return;
    }

    const formattedDate = toDatetimeLocalValue(checkOutDate);
    const formattedMin = toDatetimeLocalValue(checkInDate);
    const maxDate = new Date(checkInDate);
    maxDate.setDate(maxDate.getDate() + 1);
    maxDate.setHours(OVERNIGHT_CHECKOUT_CUTOFF_HOUR, 0, 0, 0);
    const formattedMaxDate = toDatetimeLocalValue(maxDate);

    Swal.fire({
      html: `<input type="datetime-local" id="time-input-${e.id}" value="${formattedDate}" min="${formattedMin}" max="${formattedMaxDate}" class="swal2-input" required>`,
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
        const selectedOut = parseDatetimeLocalValue(result.value);
        if (!selectedOut) {
          Swal.fire({ icon: 'error', text: 'تأكد من وقت الانصراف' });
          return;
        }

        const diffMs = diffMsBetween(checkInDate, selectedOut);
        if (diffMs <= 0 || diffMs > 24 * 60 * 60 * 1000) {
          Swal.fire({ icon: 'error', text: 'تأكد من وقت الانصراف' });
          return;
        }

        const checkOutDateFinal = new Date(checkInDate.getTime() + diffMs);
        const hours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        const formattedDifference = `${String(hours).padStart(2, '0')}:${String(diffMinutes).padStart(2, '0')}`;

        const checkOut = `${(checkOutDateFinal.getHours() % 12 || 12).toString().padStart(2, '0')}:${checkOutDateFinal.getMinutes().toString().padStart(2, '0')} ${checkOutDateFinal.getHours() >= 12 ? 'PM' : 'AM'}`;

        const data = {
          check_in: e.check_in,
          check_out: checkOut,
          hours: formattedDifference,
          time_in: toDatetimeLocalValue(checkInDate) + ':00',
          time_out: toDatetimeLocalValue(checkOutDateFinal) + ':00',
          hours_permission: null,
          times: JSON.stringify([
            toDatetimeLocalValue(checkInDate) + ':00',
            toDatetimeLocalValue(checkOutDateFinal) + ':00',
          ]),
        }
        console.log(data);

        this.employeeService.editCheckInOrOut(e.id, { data }).subscribe(res => {
          if (res) {
            this.getEmpDataPerMonth();
            this.showFingerprintMutationSuccess();
          }
        })
      }
    });
  }

  showChangeLog(row: { id?: number | null; date: string; logs_count?: number }): void {
    if (!row.id) {
      Swal.fire({ icon: 'info', text: 'لا يوجد سجل محفوظ لهذا اليوم بعد' });
      return;
    }

    this.employeeService.getFingerPrintSheetLogs(row.id).subscribe({
      next: (logs) => {
        if (!logs?.length) {
          Swal.fire({
            icon: 'info',
            title: 'سجل التعديلات',
            text: 'لا توجد تعديلات مسجّلة على هذا اليوم',
          });
          return;
        }

        const rowsHtml = logs.map((log: any) => {
          const when = log.created_at
            ? new Date(log.created_at).toLocaleString('ar-EG', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit'
              })
            : '—';
          const changesHtml = log.changes
            ? Object.values(log.changes).map((c: any) => `
                <div class="fp-log-change-row">
                  <span class="fp-log-field">${c.label}</span>
                  <span class="fp-log-old">${c.old}</span>
                  <span class="fp-log-arrow">→</span>
                  <span class="fp-log-new">${c.new}</span>
                </div>`
              ).join('')
            : '<div class="fp-log-empty-change">—</div>';
          const noteHtml = log.note ? `<div class="fp-log-note"><i class="fa-solid fa-circle-info"></i> ${log.note}</div>` : '';

          return `
            <div class="fp-log-item">
              <div class="fp-log-item-header">
                <span class="fp-log-action">${log.action}</span>
                <span class="fp-log-user">${log.user_name}</span>
              </div>
              <div class="fp-log-when">${when}</div>
              <div class="fp-log-changes">${changesHtml}</div>
              ${noteHtml}
            </div>
          `;
        }).join('');

        Swal.fire({
          title: `سجل التعديلات`,
          html: `
            <div class="fp-log-popup-date">${row.date}</div>
            <div class="fp-log-list">${rowsHtml}</div>
          `,
          width: 680,
          showCloseButton: true,
          confirmButtonText: 'إغلاق',
          customClass: {
            popup: 'fp-log-popup',
            title: 'fp-log-title',
            htmlContainer: 'fp-log-container',
            confirmButton: 'fp-log-confirm-btn',
            closeButton: 'fp-log-close-btn',
          },
        });
      },
      error: () => {
        Swal.fire({ icon: 'error', text: 'تعذّر تحميل سجل التعديلات' });
      },
    });
  }


}
