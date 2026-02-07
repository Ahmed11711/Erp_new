import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { EmployeeService } from '../services/employee.service';
import Swal from 'sweetalert2';
import { AuthService } from 'src/app/auth/auth.service';
import { BanksService } from 'src/app/financial/services/banks.service';

@Component({
  selector: 'app-payroll',
  templateUrl: './payroll.component.html',
  styleUrls: ['./payroll.component.css']
})
export class PayrollComponent implements OnInit{
  user!:string;
  employees:any[]=[];
  data:any[]=[];
  catword:any="name"
  currentMonthValue!:any
  month!:any
  year!:any

  length = 50;
  pageSize = 25;
  page = 0;
  pageSizeOptions = [25,50,100];
  holidayDays:any[]=[];
  total_merit:number = 0;
  total_subtraction:number = 0;
  banks :any = [];

  constructor(private empService:EmployeeService , private authService:AuthService , private bankService:BanksService){
    this.user = this.authService.getUser();
    const today = new Date();
    // Get previous month as default
    const previousMonth = new Date(today.getFullYear(), today.getMonth() - 1);
    this.year = previousMonth.getFullYear();
    this.month = previousMonth.getMonth() + 1; // getMonth() returns 0-11, so add 1
    this.currentMonthValue = `${this.year}-${this.month.toString().padStart(2, '0')}`;
    this.form.patchValue({
      type:'نوع الراتب'
    });
    this.holidayDaysFn(this.currentMonthValue);

  }

  ngOnInit(): void {
    this.bankService.bankSelect().subscribe(res=>this.banks=res);
    this.search(arguments);
  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'type' :new FormControl(null , [Validators.required ]),
    'code' :new FormControl(null , [Validators.required ]),
  })

  submitform(){}

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

  onPageChange(event:any){
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
  }

  param:any={};
  search(e:any){
    if(e?.target?.id === "name"){
      this.param['name']=e?.target?.value;
    }
    if(e?.target?.id === "code"){
      this.param['code']=e?.target?.value;
    }
    if(e?.target?.id === "type"){
      this.param['type']=e?.target?.value;
    }

    this.empService.EmployeesPerMonth(this.pageSize,this.page+1,this.month ,this.year,this.param).subscribe((result:any)=>{
      this.data = [];
      result.data.forEach(elm=>{
        let obj = {};
        if (elm.salary_paid.length == 1) {
          obj['salary_paid'] = true;
        } else {
          obj['salary_paid'] = false;
        }
        obj['name'] = elm.name;
        obj['id'] = elm.id;
        obj['code'] = elm.code;
        obj['level'] = elm.level;
        //استحقاقات
        obj['fixed_salary'] = elm.fixed_salary;
        obj['calc_salary'] = elm.fixed_salary;
        obj['incentives'] = 0;
        obj['suits'] = 0;
        obj['rewards'] = 0;
        obj['changed_salary'] = 0;
        //استقطاعات
        obj['rival'] = 0;
        obj['absence'] = 0;
        obj['absence_sub'] = 0;
        obj['advance_payment'] = 0;
        if (elm.merits.length > 0) {
          let incentives = 0;
          let suits = 0;
          let rewards = 0;
          let changed_salary = 0;
          elm.merits.forEach(item=>{
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
        if (elm.subtraction.length > 0) {
          let rival = 0;
          let absence = 0;
          let absence_sub = 0;
          elm.subtraction.forEach(item=>{
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

        obj['extraHours'] = 0;
        obj['noFingerPrints'] = false; // Flag to indicate no fingerprints in month
        if (elm.finger_print.length > 0) {
          let fingerSheet = this.fingerSheet(elm);
          if (fingerSheet.calcSalary.differnceSalary <= 0) {
            obj['absence_sub'] = Math.abs(fingerSheet.calcSalary.differnceSalary);
          } else {
            obj['extraHours'] = fingerSheet.calcSalary.differnceSalary;
          }
          obj['isReviewed'] = fingerSheet.reviewed;
          obj['absenceDetails'] = fingerSheet.absenceDetails;
          if (obj['absenceDetails'].absenceDaysCount) {
            obj['absence'] = obj['absenceDetails'].absenceDaysCount;
          }
        } else {
          obj['noFingerPrints'] = true; // No fingerprints for this month
          obj['absenceDetails'] = {}; // Empty details
        }

        if (elm.advance_payment.length > 0) {
          let advance_payment = 0;
          elm.advance_payment.forEach(item=>{
            if (item.type == "سلف") {
              advance_payment+=item.amount;
            }
          })
          obj['advance_payment'] = advance_payment;
        }
        obj['total_merit'] = obj['calc_salary']+ obj['incentives']+obj['suits'] +obj['rewards'] + obj['extraHours'];
        obj['total_sub'] = Number((obj['rival']+ obj['absence_sub']+obj['advance_payment']).toFixed(2));
        obj['net_total'] = Math.ceil((obj['total_merit'] - obj['total_sub']) / 5) * 5;
        this.data.push(obj);

      })
      this.total_merit = this.data.reduce((acc, item) => acc + item.total_merit, 0);
      this.total_subtraction = this.data.reduce((acc, item) => acc + item.total_sub, 0);
      this.length=result.total;
      this.pageSize=result.per_page;
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


  fingerSheet(emp){
    let tableData:any = [];
    let param = {}
    param['dayHours'] = '08:00';
    if (emp.dayHours == 9) {
      param['dayHours'] = '09:00';
    }

    let fixedSalary = emp.fixed_salary;
    let workingHourPerDay = 8;
    let hour = '08:00';
    if (emp.working_hours) {
      workingHourPerDay = emp.working_hours;
      hour = '09:00';
    }
    let salaryType = emp.salary_type;
    let changedSalary = 0;
    if (salaryType == "متباين") {
      if (emp.merits) {
        changedSalary = emp.merits.filter(elm => elm.type === "الراتب المتغير").reduce((acc, elm) => acc + elm.amount, 0);
      }
    }
    let dayHours = workingHourPerDay;
    let totalHours:any = workingHourPerDay * 60;
    let totalHoursPerMonth = workingHourPerDay * 26 * 60;
    let actualTotalMinutesPerMonth = 0;
    totalHours = this.convertMinutesToHours(totalHoursPerMonth);
    let hourPrice = fixedSalary/30/dayHours
    emp.finger_print.forEach(elm=>{
      elm.times = JSON.parse(elm.times.replace(/\\/g, ''));
      elm['working_hours'] = workingHourPerDay;
      let holiday = this.holidayDays.find(elm2 => elm2 == elm.date);
      if (holiday) {
        elm['holiday'] = true;
      }
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
      tableData.push(elm);
    });

    // total for month

    if (tableData.length > 0) {
      actualTotalMinutesPerMonth = actualTotalMinutesPerMonth - ((tableData.length-this.holidayDays.length-26) * dayHours*60);
    }
    let hoursDifference = actualTotalMinutesPerMonth - totalHoursPerMonth;



    let hoursDifferenceStr: string;
    if (hoursDifference >= 0) {
      hoursDifferenceStr = this.convertMinutesToHours(hoursDifference);
    } else {
      hoursDifferenceStr = "-" + this.convertMinutesToHours(-hoursDifference);
    }
    // this.hoursDifferenceStr = hoursDifferenceStr;
    let actualHours:any = this.convertMinutesToHours(actualTotalMinutesPerMonth);
    // end total for month
    let calcSalary = this.calcSalary(actualHours , hourPrice , dayHours , totalHours , emp.fixed_salary );
    let reviewed = tableData[0].reviewed;
    let absenceDays = tableData.filter(elm => elm.vacation == 0 && elm.check_in == '08:00 AM' && elm.check_out == '08:00 AM' && elm.hours_permission !== `0${dayHours}:00`);
    let absenceDaysCount = absenceDays.reduce((total, elm) => {
      let day = 1;
      if (elm.absence_deduction) {
        day = Number(elm.absence_deduction);
      }
      return total + day;
    }, 0);
    absenceDaysCount = absenceDaysCount - this.holidayDays.length;
    let absenceDaysPrice = absenceDaysCount * dayHours * hourPrice;
    let absenceDetails:any = {};
    if (absenceDaysPrice > 0) {
      absenceDetails['absenceDaysCount'] = absenceDaysCount;
    }


    if (Math.abs(hoursDifference) > absenceDaysCount * dayHours * 60) {
      let absenceHours = this.convertMinutesToHours(Math.abs(hoursDifference) - absenceDaysCount * dayHours * 60);
      let [hours, minutes] = absenceHours.split(':').map(Number);
      let  absenceHoursPrice = ((hours * 60 + minutes)/60 * hourPrice);
      absenceDetails['absenceHours'] = absenceHours;
      absenceDetails['absenceHoursPrice'] = absenceHoursPrice;
    }
    return {calcSalary , reviewed , absenceDetails};
  }

  convertMinutesToHours(minutes: number): string {
    let h = Math.floor(minutes / 60);
    let m = minutes % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
  }

  calcSalary(empActualHours , hourPrice , dayHours , totalHours , fixedSalary ){
    // this.hourPrice = this.fixedSalary/30/this.dayHours;
    let [hours, minutes] = empActualHours.split(':').map(Number);
    let actualHours = hours * 60 + minutes;
    let totalActualHoursSalary = hourPrice * actualHours/60;
    if (actualHours !== 0) {
      totalActualHoursSalary = totalActualHoursSalary  + (hourPrice * (dayHours * 4) );
    }
    let [hours2, minutes2] = totalHours.split(':').map(Number);
    if (actualHours > hours2 * 60 + minutes2) {
      totalActualHoursSalary = fixedSalary + ((actualHours - hours2 * 60 + minutes2)/60 * hourPrice * 1.5);
    }
    let differnceSalary = totalActualHoursSalary - fixedSalary;
    return {totalActualHoursSalary , differnceSalary , empActualHours};
  }

  reviewMonth(e){
    if (this.user == 'Admin' && e.isReviewed == 0) {
      this.empService.reviewMonth(this.currentMonthValue , e.id).subscribe(res=>{
        if (res) {
          this.search(arguments);
          Swal.fire({
            icon:'success',
            timer:1500,
            showConfirmButton:false
          })
        }
      })
    }
  }

  salaryCashing(e){
    if (e.isReviewed == 0) {
      Swal.fire({
        icon:'error',
        title: 'يرجي مراجعة كشف الحضور والانصراف',
      })
      return;
    }
    this.search(arguments);
    const banks = this.banks;
    let selectedBank;
    const bankSelectOptions = banks.reduce((options, bank) => {
      options[bank.id] = bank.name;
      if (bank.name == 'خزينة المصنع') {
        selectedBank = bank.id;
      }
      return options;
    }, {});

    Swal.fire({
      title: `صرف مرتب ${e.name} عن شهر ${this.currentMonthValue}`,
      input: 'select',
      inputOptions: bankSelectOptions,
      inputPlaceholder: 'اختر الخزينة',
      inputValue: selectedBank,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      customClass: {
        input: 'text-center'
      }
    }).then((bankResult) => {
      if (bankResult.isConfirmed) {
        let emp = this.data.find(elm => elm.id == e.id);
        const selectedBankId = bankResult.value;
        if (selectedBankId) {
          let data = {
            employee_id: e.id,
            month: this.month,
            year: this.year,
            amount: emp.net_total,
            bank_id: selectedBankId,
          }
          this.empService.addSalaryPayment(data).subscribe(result=>{
            console.log(result);
            if (result) {
              Swal.fire({
                icon : 'success',
                timer:1500,
                showConfirmButton:false,
              }).then(result=>{
                this.search(arguments);
              });
            }
          },
          (error)=>{
            Swal.fire({
              icon : 'error',
              title: error.error.message,
              timer:1500,
              showConfirmButton:false,
            })
          });
        } else{
          Swal.fire({
            icon:'error',
            title: 'اختر الخزينة',
          }).then(res=>{
            this.salaryCashing(e);
          })
        }
      }
    });
  }

  addMerits(e){
    Swal.fire({
      title: ` اضافة استحقاق الي ${e.name} عن شهر ${this.currentMonthValue}`,
      html: `
        <div class="row w-100 m-auto">
          <div class="col-md-6">
            <div class="form-group">
              <input id="swal-input2" class="form-control text-center" placeholder="المبلغ " type="number" min="0">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <select id="swal-input1" class="form-control  text-center">
                <option value="نوع الاستحقاق" disabled selected>نوع الاستحقاق</option>
                <option value="الراتب المتغير">الراتب المتغير</option>
                <option value="حوافز">حوافز</option>
                <option value="مكافئات">مكافئات</option>
                <option value="بدلات">بدلات</option>
              </select>
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <textarea style="text-align: end;" id="swal-input3" class="form-control" placeholder="السبب"></textarea>
            </div>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const type:any = document.getElementById('swal-input1');
        const amount:any = document.getElementById('swal-input2');
        const reason:any = document.getElementById('swal-input3');

        if (!type.value || type.value === "نوع الاستحقاق") {
          Swal.showValidationMessage('الرجاء اختيار نوع الاستحقاق');
          return false;
        }
        if (!amount.value || amount.value <= 0) {
          Swal.showValidationMessage('الرجاء تحديد المبلغ');
          return false;
        }

        return { type:type.value, amount:amount.value, reason:reason.value };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        let data = {
          type : result.value.type,
          amount : Number(result.value.amount),
          reason : result.value.reason
        }
        data['employee_id'] = e.id;
        data['month'] = this.month;
        data['year'] = this.year;
        this.empService.addMerit(data).subscribe(result=>{
          if (result) {
            Swal.fire({
              icon : 'success',
              timer:1500,
              showConfirmButton:false,
            }).then(result=>{
              this.search(arguments);
            });
          }
        },
        (error)=>{
          Swal.fire({
            icon : 'error',
            title: error.error.message,
            showConfirmButton:true,
          })
        })
      }
    });

  }

  addSubtraction(e){
    let options = `<option value="خصومات">خصومات</option>`
    if (!e.absenceDetails) {
      options += `<option value="غياب">غياب</option>`
    }
    Swal.fire({
      title: ` اضافة استقطاع الي ${e.name} عن شهر ${this.currentMonthValue}`,
      html: `
        <div class="row w-100 m-auto">
          <div class="col-md-6">
            <div class="form-group">
              <input id="swal-input2" class="form-control text-center" placeholder="المبلغ" type="number" min="0">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <select id="swal-input1" class="form-control  text-center">
                <option value="نوع الاستقطاع" disabled selected>نوع الاستقطاع</option>
                ${options}
              </select>
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <textarea style="text-align:end;" id="swal-input3" class="form-control" placeholder="السبب"></textarea>
            </div>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const type:any = document.getElementById('swal-input1');
        const amount:any = document.getElementById('swal-input2');
        const reason:any = document.getElementById('swal-input3');

        if (!type.value || type.value === "نوع الاستقطاع") {
          Swal.showValidationMessage('الرجاء اختيار نوع الاستقطاع');
          return false;
        }
        if (!amount.value || amount.value <= 0) {
          let errro = 'المبلغ';
          if (type.value == 'غياب') {
            errro = 'عدد ايام الغياب';
          }
          Swal.showValidationMessage('الرجاء تحديد '+errro);
          return false;
        }

        return { type:type.value, amount:amount.value, reason:reason.value };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        let data = {
          type : result.value.type,
          amount : Number(result.value.amount),
          reason : result.value.reason
        }
        data['employee_id'] = e.id;
        data['month'] = this.month;
        data['year'] = this.year;
        this.empService.addSubtraction(data).subscribe(result=>{
          if (result) {
            Swal.fire({
              icon : 'success',
              timer:1500,
              showConfirmButton:false,
            }).then(result=>{
              this.search(arguments);
            });
          }
        },
        (error)=>{
          Swal.fire({
            icon : 'error',
            title: error.error.message,
            showConfirmButton:true,
          })
        });
      }
    });

    let elm:any = document.getElementById('swal-input1');
    elm.addEventListener('change', logSelectValue);

    function logSelectValue() {
      const selectedValue = elm.value;
      if (selectedValue == 'غياب') {
        let amountInp:any = document.getElementById('swal-input2');
        amountInp.placeholder = 'عدد ايام الغياب';
      }
      if (selectedValue == 'خصومات') {
        let amountInp:any = document.getElementById('swal-input2');
        amountInp.placeholder = 'المبلغ';
      }
    }

  }

  advancePayment(e){
    let options;
    this.banks.forEach(elm =>{
      let selected = '';
      if (elm.name == 'خزينة المصنع') {
        selected = 'selected';
      }
      options += `<option ${selected} value="${elm.id}">${elm.name}</option>`;
    })
    Swal.fire({
      title: `  صرف سلفه الي ${e.name} عن شهر ${this.currentMonthValue}`,
      html: `
        <div class="row w-100 m-auto">
          <div class="col-md-6">
            <div class="form-group">
              <select id="swal-input1" class="form-control  text-center bg-main">
                <option value="اختر الخزينة" disabled selected>اختر الخزينة</option>
                ${options}
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <input id="swal-input2" class="form-control text-center" placeholder="المبلغ" type="number" min="0">
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <textarea style="text-align:end;" id="swal-input3" class="form-control" placeholder="السبب"></textarea>
            </div>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const bank:any = document.getElementById('swal-input1');
        const amount:any = document.getElementById('swal-input2');
        const reason:any = document.getElementById('swal-input3');

        if (!bank.value || bank.value === "اختر الخزينة") {
          Swal.showValidationMessage('الرجاء اختيار الخزينة');
          return false;
        }
        if (!amount.value || amount.value <= 0) {
          Swal.showValidationMessage('الرجاء تحديد المبلغ');
          return false;
        }

        return { bank_id:bank.value, amount:amount.value, reason:reason.value };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        let data = {
          'type' : 'سلف',
          bank_id : result.value.bank_id,
          amount : Number(result.value.amount),
          reason : result.value.reason
        }
        data['employee_id'] = e.id;
        data['month'] = this.month;
        data['year'] = this.year;
        this.empService.addAdvancePayment(data).subscribe(result=>{
          if (result) {
            Swal.fire({
              icon : 'success',
              timer:1500,
              showConfirmButton:false,
            }).then(result=>{
              this.search(arguments);
            });
          }
        },
        (error)=>{
          Swal.fire({
            icon : 'error',
            title: error.error.message,
            showConfirmButton:true,
          })
        });
      }
    });

    let elm:any = document.getElementById('swal-input1');
    elm.addEventListener('change', logSelectValue);

    function logSelectValue() {
      const selectedValue = elm.value;
      if (selectedValue == 'غياب') {
        let amountInp:any = document.getElementById('swal-input2');
        amountInp.placeholder = 'عدد ايام الغياب';
      }
      if (selectedValue == 'خصومات') {
        let amountInp:any = document.getElementById('swal-input2');
        amountInp.placeholder = 'المبلغ';
      }
    }

  }

  addChangedSalary(e){
    Swal.fire({
      title: ` اضافة راتب متغير الي ${e.name} عن شهر ${this.currentMonthValue}`,
      html: `
        <div class="row w-100 m-auto">
          <div class="col-md-12">
            <div class="form-group">
              <input id="swal-input2" class="form-control text-center" placeholder="المبلغ " type="number" min="0">
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <textarea style="text-align: end;" id="swal-input3" class="form-control" placeholder="السبب"></textarea>
            </div>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const amount:any = document.getElementById('swal-input2');
        const reason:any = document.getElementById('swal-input3');

        if (!amount.value || amount.value <= 0) {
          Swal.showValidationMessage('الرجاء تحديد المبلغ');
          return false;
        }

        return { type:'الراتب المتغير', amount:amount.value, reason:reason.value };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        let data = {
          type : result.value.type,
          amount : Number(result.value.amount),
          reason : result.value.reason
        }
        data['employee_id'] = e.id;
        data['month'] = this.month;
        data['year'] = this.year;
        this.empService.addMerit(data).subscribe(result=>{
          if (result) {
            Swal.fire({
              icon : 'success',
              timer:1500,
              showConfirmButton:false,
            }).then(result=>{
              this.search(arguments);
            });
          }
        },
        (error)=>{
          Swal.fire({
            icon : 'error',
            title: error.error.message,
            showConfirmButton:true,
          })
        })
      }
    });

  }

  addIncentives(e){
    Swal.fire({
      title: ` اضافة حافز الي ${e.name} عن شهر ${this.currentMonthValue}`,
      html: `
        <div class="row w-100 m-auto">
          <div class="col-md-12">
            <div class="form-group">
              <input id="swal-input2" class="form-control text-center" placeholder="المبلغ " type="number" min="0">
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <textarea style="text-align: end;" id="swal-input3" class="form-control" placeholder="السبب"></textarea>
            </div>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const amount:any = document.getElementById('swal-input2');
        const reason:any = document.getElementById('swal-input3');

        if (!amount.value || amount.value <= 0) {
          Swal.showValidationMessage('الرجاء تحديد المبلغ');
          return false;
        }

        return { type:'حوافز', amount:amount.value, reason:reason.value };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        let data = {
          type : result.value.type,
          amount : Number(result.value.amount),
          reason : result.value.reason
        }
        data['employee_id'] = e.id;
        data['month'] = this.month;
        data['year'] = this.year;
        this.empService.addMerit(data).subscribe(result=>{
          if (result) {
            Swal.fire({
              icon : 'success',
              timer:1500,
              showConfirmButton:false,
            }).then(result=>{
              this.search(arguments);
            });
          }
        },
        (error)=>{
          Swal.fire({
            icon : 'error',
            title: error.error.message,
            showConfirmButton:true,
          })
        })
      }
    });

  }

  addRewards(e){
    Swal.fire({
      title: ` اضافة مكافئة الي ${e.name} عن شهر ${this.currentMonthValue}`,
      html: `
        <div class="row w-100 m-auto">
          <div class="col-md-12">
            <div class="form-group">
              <input id="swal-input2" class="form-control text-center" placeholder="المبلغ " type="number" min="0">
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <textarea style="text-align: end;" id="swal-input3" class="form-control" placeholder="السبب"></textarea>
            </div>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const amount:any = document.getElementById('swal-input2');
        const reason:any = document.getElementById('swal-input3');

        if (!amount.value || amount.value <= 0) {
          Swal.showValidationMessage('الرجاء تحديد المبلغ');
          return false;
        }

        return { type:'مكافئات', amount:amount.value, reason:reason.value };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        let data = {
          type : result.value.type,
          amount : Number(result.value.amount),
          reason : result.value.reason
        }
        data['employee_id'] = e.id;
        data['month'] = this.month;
        data['year'] = this.year;
        this.empService.addMerit(data).subscribe(result=>{
          if (result) {
            Swal.fire({
              icon : 'success',
              timer:1500,
              showConfirmButton:false,
            }).then(result=>{
              this.search(arguments);
            });
          }
        },
        (error)=>{
          Swal.fire({
            icon : 'error',
            title: error.error.message,
            showConfirmButton:true,
          })
        })
      }
    });

  }

  addSuits(e){
    Swal.fire({
      title: ` اضافة بدلات الي ${e.name} عن شهر ${this.currentMonthValue}`,
      html: `
        <div class="row w-100 m-auto">
          <div class="col-md-12">
            <div class="form-group">
              <input id="swal-input2" class="form-control text-center" placeholder="المبلغ " type="number" min="0">
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <textarea style="text-align: end;" id="swal-input3" class="form-control" placeholder="السبب"></textarea>
            </div>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const amount:any = document.getElementById('swal-input2');
        const reason:any = document.getElementById('swal-input3');

        if (!amount.value || amount.value <= 0) {
          Swal.showValidationMessage('الرجاء تحديد المبلغ');
          return false;
        }

        return { type:'بدلات', amount:amount.value, reason:reason.value };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        let data = {
          type : result.value.type,
          amount : Number(result.value.amount),
          reason : result.value.reason
        }
        data['employee_id'] = e.id;
        data['month'] = this.month;
        data['year'] = this.year;
        this.empService.addMerit(data).subscribe(result=>{
          if (result) {
            Swal.fire({
              icon : 'success',
              timer:1500,
              showConfirmButton:false,
            }).then(result=>{
              this.search(arguments);
            });
          }
        },
        (error)=>{
          Swal.fire({
            icon : 'error',
            title: error.error.message,
            showConfirmButton:true,
          })
        })
      }
    });

  }

  addRival(e){
    Swal.fire({
      title: ` اضافة خصم الي ${e.name} عن شهر ${this.currentMonthValue}`,
      html: `
        <div class="row w-100 m-auto">
          <div class="col-md-12">
            <div class="form-group">
              <input id="swal-input2" class="form-control text-center" placeholder="المبلغ" type="number" min="0">
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <textarea style="text-align:end;" id="swal-input3" class="form-control" placeholder="السبب"></textarea>
            </div>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const amount:any = document.getElementById('swal-input2');
        const reason:any = document.getElementById('swal-input3');
        if (!amount.value || amount.value <= 0) {
          Swal.showValidationMessage('الرجاء تحديد مبلغ الخصم');
          return false;
        }

        return { type:'خصومات', amount:amount.value, reason:reason.value };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        let data = {
          type : result.value.type,
          amount : Number(result.value.amount),
          reason : result.value.reason
        }
        data['employee_id'] = e.id;
        data['month'] = this.month;
        data['year'] = this.year;
        this.empService.addSubtraction(data).subscribe(result=>{
          if (result) {
            Swal.fire({
              icon : 'success',
              timer:1500,
              showConfirmButton:false,
            }).then(result=>{
              this.search(arguments);
            });
          }
        },
        (error)=>{
          Swal.fire({
            icon : 'error',
            title: error.error.message,
            showConfirmButton:true,
          })
        });
      }
    });

  }

  addAbsence(e){
    if (!e.absenceDetails) {
      Swal.fire({
        title: ` اضافة ايام غياب الي ${e.name} عن شهر ${this.currentMonthValue}`,
        html: `
          <div class="row w-100 m-auto">
            <div class="col-md-12">
              <div class="form-group">
                <input id="swal-input2" class="form-control text-center" placeholder="عدد ايام الغياب" type="number" min="0">
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <textarea style="text-align:end;" id="swal-input3" class="form-control" placeholder="السبب"></textarea>
              </div>
            </div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'تأكيد',
        cancelButtonText: 'إلغاء',
        preConfirm: () => {
          const amount:any = document.getElementById('swal-input2');
          const reason:any = document.getElementById('swal-input3');
          if (!amount.value || amount.value <= 0) {
            Swal.showValidationMessage('الرجاء تحديد عدد ايام الغياب');
            return false;
          }

          return { type:'غياب', amount:amount.value, reason:reason.value };
        }
      }).then((result) => {
        if (result.isConfirmed) {
          let data = {
            type : result.value.type,
            amount : Number(result.value.amount),
            reason : result.value.reason
          }
          data['employee_id'] = e.id;
          data['month'] = this.month;
          data['year'] = this.year;
          this.empService.addSubtraction(data).subscribe(result=>{
            if (result) {
              Swal.fire({
                icon : 'success',
                timer:1500,
                showConfirmButton:false,
              }).then(result=>{
                this.search(arguments);
              });
            }
          },
          (error)=>{
            Swal.fire({
              icon : 'error',
              title: error.error.message,
              showConfirmButton:true,
            })
          });
        }
      });
    }

  }

  addFixedChangedSalary(e){
    Swal.fire({
      title: `  الراتب المحسوب الي ${e.name} عن شهر ${this.currentMonthValue}`,
      html: `
        <div class="row w-100 m-auto">
          <div class="col-md-12">
            <div class="form-group">
              <input id="swal-input2" class="form-control text-center" placeholder="المبلغ " type="number" min="0">
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <textarea style="text-align: end;" id="swal-input3" class="form-control" placeholder="السبب"></textarea>
            </div>
          </div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const amount:any = document.getElementById('swal-input2');
        const reason:any = document.getElementById('swal-input3');

        if (!amount.value || amount.value < e.fixed_salary) {
          Swal.showValidationMessage('لا يمكنك تحديد مبلغ اقل من الراتب الثابت');
          return false;
        }

        return { type:'الراتب المتغير', amount:Number(amount.value)-Number(e.fixed_salary), reason:reason.value };
      }
    }).then((result) => {
      if (result.isConfirmed) {
        let data = {
          type : result.value.type,
          amount : Number(result.value.amount),
          reason : result.value.reason
        }
        data['employee_id'] = e.id;
        data['month'] = this.month;
        data['year'] = this.year;
        this.empService.addFixedChangedSalary(data).subscribe(result=>{
          if (result) {
            Swal.fire({
              icon : 'success',
              timer:1500,
              showConfirmButton:false,
            }).then(result=>{
              this.search(arguments);
            });
          }
        },
        (error)=>{
          Swal.fire({
            icon : 'error',
            title: error.error.message,
            showConfirmButton:true,
          })
        })
      }
    });

  }

}
