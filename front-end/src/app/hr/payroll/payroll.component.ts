import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, Subscription } from 'rxjs';
import { debounceTime, takeUntil } from 'rxjs/operators';
import { EmployeeService } from '../services/employee.service';
import Swal from 'sweetalert2';
import { AuthService } from 'src/app/auth/auth.service';
import { BanksService } from 'src/app/financial/services/banks.service';
import { environment } from 'src/env/env';
import { buildPayrollRowFromAttendance, defaultFingerprintMonthValue, fingerprintPeriodForMonth, fridayDatesInRange, parseYearMonthValue } from '../utils/fingerprint-hours.utils';

@Component({
  selector: 'app-payroll',
  templateUrl: './payroll.component.html',
  styleUrls: ['./payroll.component.css']
})
export class PayrollComponent implements OnInit, OnDestroy{
  user!:string;
  employees:any[]=[];
  data:any[]=[];
  private sourceRows: any[] = [];
  catword:any="name"
  currentMonthValue!:any
  dateFrom!: string;
  dateTo!: string;
  month!:any
  year!:any

  length = 0;
  pageSize = 25;
  page = 0;
  pageSizeOptions = [25,50,100];
  holidayDays:any[]=[];
  total_merit:number = 0;
  total_subtraction:number = 0;
  total_net:number = 0;
  banks :any = [];
  /** مصادر الصرف من وحدة الحسابات (خزائن، بنوك، حسابات خدمية) */
  paymentSourcesBundle: { safes: any[]; banks: any[]; service_accounts: any[] } | null = null;

  showImportPanel = false;
  importFile: File | null = null;
  importLoading = false;
  importApplying = false;
  importError = '';
  importSuccess = '';
  matchedImportLines: any[] = [];
  missingImportLines: any[] = [];
  skippedImportLines: any[] = [];
  importTotals: any = null;
  listLoading = false;
  listError = '';
  private listRequest?: Subscription;
  private readonly searchInput$ = new Subject<void>();
  private readonly destroy$ = new Subject<void>();

  get canApplyImport(): boolean {
    return !this.importLoading
      && !this.importApplying
      && this.matchedImportLines.some((r) => r.can_apply);
  }

  matchByLabel(matchBy: string | null | undefined): string {
    if (matchBy === 'name') {
      return 'الاسم';
    }
    if (matchBy === 'acc_no') {
      return 'البصمة';
    }
    if (matchBy === 'code') {
      return 'الكود';
    }
    return matchBy || '—';
  }

  constructor(
    private empService: EmployeeService,
    private authService: AuthService,
    private bankService: BanksService,
    private http: HttpClient,
  ){
    this.user = this.authService.getUser();
    this.applyMonthValue(defaultFingerprintMonthValue());
    this.form.patchValue({
      type:'نوع الراتب'
    });
    this.holidayDaysFn();

  }

  ngOnDestroy(): void {
    this.listRequest?.unsubscribe();
    this.destroy$.next();
    this.destroy$.complete();
  }

  get isFiltering(): boolean {
    return !!this.payrollQueryParams().name
      || !!this.payrollQueryParams().code
      || !!this.payrollQueryParams().type;
  }

  trackByEmployeeId(_index: number, elm: any): number | string {
    return elm?.id ?? _index;
  }

  ngOnInit(): void {
    this.searchInput$.pipe(debounceTime(300), takeUntil(this.destroy$)).subscribe(() => this.search(null));
    this.bankService.bankSelect().subscribe(res=>this.banks=res);
    this.http.get<{ safes: any[]; banks: any[]; service_accounts: any[] }>(`${environment.Url}/accounting/payment-sources`).subscribe({
      next: (res) => { this.paymentSourcesBundle = res; },
      error: () => { this.paymentSourcesBundle = null; },
    });
    this.search(arguments);
  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null , [Validators.required ]),
    'type' :new FormControl(null , [Validators.required ]),
    'code' :new FormControl(null , [Validators.required ]),
  })

  submitform(){}

  holidayDaysFn(_month?: string) {
    this.holidayDays = fridayDatesInRange(this.dateFrom, this.dateTo);
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
  }

  param:any={};

  onSearchField(e: Event) {
    const target = e?.target as HTMLInputElement | HTMLSelectElement | null;
    const id = target?.id;
    const value = String(target?.value ?? '').trim();
    if (id === 'name') {
      if (value) {
        this.param['name'] = value;
      } else {
        delete this.param['name'];
      }
    }
    if (id === 'code') {
      if (value) {
        this.param['code'] = value;
      } else {
        delete this.param['code'];
      }
    }
    if (id === 'type') {
      if (value && value !== 'نوع الراتب') {
        this.param['type'] = value;
      } else {
        delete this.param['type'];
      }
    }
    this.page = 0;
    this.applyLocalFilter();
    if (id === 'type') {
      this.search(null);
      return;
    }
    this.searchInput$.next();
  }

  private applyLocalFilter() {
    if (!this.sourceRows.length) {
      return;
    }
    const name = String(this.param['name'] || '').trim();
    const code = String(this.param['code'] || '').trim();
    const type = String(this.param['type'] || '').trim();
    this.data = this.sourceRows.filter((row) => {
      if (name && !String(row?.name || '').includes(name)) {
        return false;
      }
      if (code && !String(row?.code || '').startsWith(code)) {
        return false;
      }
      if (type && type !== 'نوع الراتب' && String(row?.salary_type || '') && String(row?.salary_type) !== type) {
        return false;
      }
      return true;
    });
    this.total_merit = this.data.reduce((acc, item) => acc + Number(item.total_merit || 0), 0);
    this.total_subtraction = this.data.reduce((acc, item) => acc + Number(item.total_sub || 0), 0);
    this.total_net = this.data.reduce((acc, item) => acc + Number(item.net_total || 0), 0);
  }

  private payrollQueryParams(): any {
    const params: any = {
      date_from: this.dateFrom,
      date_to: this.dateTo,
    };
    const name = String(this.param['name'] || '').trim();
    const code = String(this.param['code'] || '').trim();
    const type = String(this.param['type'] || '').trim();
    if (name) {
      params.name = name;
    }
    if (code) {
      params.code = code;
    }
    if (type && type !== 'نوع الراتب') {
      params.type = type;
    }
    return params;
  }

  search(e:any){
    if(e?.target?.id === "name" || e?.target?.id === "code" || e?.target?.id === "type"){
      this.onSearchField(e);
      return;
    }

    this.listLoading = true;
    this.listError = '';
    this.listRequest?.unsubscribe();

    this.listRequest = this.empService.EmployeesPerMonth(this.pageSize,this.page+1,this.month ,this.year,this.payrollQueryParams()).subscribe({
      next: (result:any) => {
        const rows = this.asPayrollApiRows(result);
        const mapped: any[] = [];
        rows.forEach((elm: any) => {
          try {
            mapped.push(this.mapEmployeeMonthRow(elm));
          } catch (err) {
            console.error('تعذّر حساب صف المرتب', elm?.id, elm?.name, err);
            mapped.push(this.fallbackPayrollRow(elm));
          }
        });
        this.sourceRows = mapped;
        this.data = mapped;
        this.total_merit = this.data.reduce((acc, item) => acc + Number(item.total_merit || 0), 0);
        this.total_subtraction = this.data.reduce((acc, item) => acc + Number(item.total_sub || 0), 0);
        this.total_net = this.data.reduce((acc, item) => acc + Number(item.net_total || 0), 0);
        this.length = Number(result?.total ?? mapped.length) || 0;
        this.pageSize = Number(result?.per_page ?? this.pageSize) || this.pageSize;
        this.listLoading = false;
      },
      error: (err) => {
        console.error('تعذّر تحميل كشف المرتبات', err);
        this.sourceRows = [];
        this.data = [];
        this.total_merit = 0;
        this.total_subtraction = 0;
        this.total_net = 0;
        this.length = 0;
        this.listLoading = false;
        this.listError = err?.error?.message || 'تعذّر تحميل كشف المرتبات';
      }
    });
  }

  private asPayrollApiRows(result: any): any[] {
    if (Array.isArray(result?.data)) {
      return result.data;
    }
    if (Array.isArray(result)) {
      return result;
    }
    return [];
  }

  private fallbackPayrollRow(elm: any): any {
    const salary = Number(elm?.fixed_salary) || 0;
    return {
      salary_paid: Array.isArray(elm?.salary_paid) && elm.salary_paid.length === 1,
      name: elm?.name,
      id: elm?.id,
      code: elm?.code,
      level: elm?.level,
      salary_type: elm?.salary_type,
      fixed_salary: salary,
      calc_salary: salary,
      changed_salary: 0,
      incentives: 0,
      suits: 0,
      rewards: 0,
      extraHours: 0,
      rival: 0,
      absence: 0,
      absence_sub: 0,
      advance_payment: 0,
      total_merit: salary,
      total_sub: 0,
      net_total: salary,
      noFingerPrints: true,
      absenceDetails: {},
      isReviewed: 0,
      calculated_net_total: salary,
      net_from_accrual: false,
    };
  }

  toggleImportPanel(): void {
    this.showImportPanel = !this.showImportPanel;
    if (!this.showImportPanel) {
      this.resetImportState();
    }
  }

  onImportFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.importFile = input.files?.[0] || null;
    this.importError = '';
    this.importSuccess = '';
    this.matchedImportLines = [];
    this.missingImportLines = [];
    this.skippedImportLines = [];
    this.importTotals = null;
  }

  private resetImportState(): void {
    this.importFile = null;
    this.importLoading = false;
    this.importApplying = false;
    this.importError = '';
    this.importSuccess = '';
    this.matchedImportLines = [];
    this.missingImportLines = [];
    this.skippedImportLines = [];
    this.importTotals = null;
  }

  previewAccrualImport(): void {
    if (!this.importFile) {
      this.importError = 'اختر ملف Excel أولاً';
      return;
    }
    this.importLoading = true;
    this.importError = '';
    this.importSuccess = '';
    this.empService.previewPayrollAccrualImport(
      this.importFile,
      Number(this.month),
      Number(this.year)
    ).subscribe({
      next: (res: any) => {
        const data = res?.data || res;
        this.matchedImportLines = data.matched || [];
        this.missingImportLines = data.missing || [];
        this.skippedImportLines = data.skipped || [];
        this.importTotals = data.totals || null;
        this.importLoading = false;
        const payable = this.importTotals?.payable_count ?? 0;
        this.importSuccess = `تمت المعاينة: ${this.matchedImportLines.length} مطابق، ${payable} قابل للتحديث، ${this.missingImportLines.length} غير مطابق.`;
      },
      error: (err) => {
        this.importLoading = false;
        this.importError = err?.error?.message || 'فشل معاينة الملف';
      },
    });
  }

  applyAccrualImport(): void {
    const lines = this.matchedImportLines
      .filter((r) => r.can_apply)
      .map((r) => ({
        employee_id: Number(r.employee_id),
        amount: Number(r.amount || 0),
        extra_day_value: Number(r.extra_day_value || 0),
        overtime_value: Number(r.overtime_value || 0),
        rewards: Number(r.rewards || 0),
        allowances: Number(r.allowances || 0),
        deductions: Number(r.deductions || 0),
        advance: Number(r.advance || 0),
      }));
    if (!lines.length) {
      this.importError = 'لا توجد صفوف قابلة للتحديث';
      return;
    }

    Swal.fire({
      title: 'تأكيد تحديث المستحقات والتفاصيل',
      html: `<div class="text-end" style="direction:rtl">سيتم تحديث <strong>${lines.length}</strong> موظفاً (مستحق + أيام/ساعات إضافية + خصومات) عن شهر <strong>${this.currentMonthValue}</strong>.</div>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'تحديث',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.importApplying = true;
      this.importError = '';
      this.importSuccess = '';
      this.empService.applyPayrollAccrualImport(Number(this.month), Number(this.year), lines).subscribe({
        next: (res: any) => {
          this.importApplying = false;
          const data = res?.data || res;
          const totals = data?.totals || {};
          this.importSuccess = res?.message
            || `تم تحديث ${totals.updated_count || 0}، تخطي ${totals.skipped_count || 0}، فشل ${totals.failed_count || 0}`;
          this.search(arguments);
          Swal.fire({
            icon: (totals.failed_count || 0) > 0 ? 'warning' : 'success',
            title: this.importSuccess,
            timer: 2500,
            showConfirmButton: false,
          });
        },
        error: (err) => {
          this.importApplying = false;
          this.importError = err?.error?.message || 'فشل تحديث المرتبات المستحقة';
        },
      });
    });
  }

  onMonthChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.applyMonthValue(target.value);
    this.matchedImportLines = [];
    this.missingImportLines = [];
    this.skippedImportLines = [];
    this.importTotals = null;
    this.importSuccess = '';
    this.importError = '';
    this.search(arguments);
  }

  onDateFromChange(event: Event) {
    const val = (event.target as HTMLInputElement)?.value;
    if (!val) {
      return;
    }
    this.dateFrom = val;
    this.holidayDaysFn();
    this.search(arguments);
  }

  onDateToChange(event: Event) {
    const val = (event.target as HTMLInputElement)?.value;
    if (!val) {
      return;
    }
    this.dateTo = val;
    this.holidayDaysFn();
    this.search(arguments);
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
    this.holidayDaysFn();
  }

  /** تحويل سجل موظف من الـ API إلى صف الجدول — نفس حساب كشف الحضور والانصراف */
  mapEmployeeMonthRow(elm: any): any {
    const obj: any = {};
    obj['salary_paid'] = Array.isArray(elm.salary_paid) && elm.salary_paid.length === 1;
    obj['name'] = elm.name;
    obj['id'] = elm.id;
    obj['code'] = elm.code;
    obj['level'] = elm.level;
    obj['salary_type'] = elm.salary_type;
    obj['fixed_salary'] = elm.fixed_salary;

    const row = buildPayrollRowFromAttendance({
      ...elm,
      finger_print: elm.finger_print || [],
      merits: elm.merits || [],
      subtraction: elm.subtraction || [],
      advance_payment: elm.advance_payment || [],
    }, {
      dateFrom: this.dateFrom,
      dateTo: this.dateTo,
      holidayDays: this.holidayDays,
    });

    obj['calc_salary'] = row.calc_salary;
    obj['changed_salary'] = row.changedSalary;
    obj['incentives'] = row.incentives;
    obj['suits'] = row.suits;
    obj['rewards'] = row.rewards;
    obj['extraHours'] = row.extraHours;
    obj['rival'] = row.rival;
    obj['absence'] = row.absence;
    obj['absence_sub'] = row.absence_sub;
    obj['advance_payment'] = row.advancePayment;
    obj['total_merit'] = row.totalMerit;
    obj['total_sub'] = row.totalSub;
    obj['noFingerPrints'] = row.noFingerPrints;
    obj['absenceDetails'] = row.absenceDetails;
    obj['isReviewed'] = row.isReviewed;
    obj['calculated_net_total'] = row.netTotal;

    const accrual = (elm.month_accruals && elm.month_accruals[0])
      || (elm.monthAccruals && elm.monthAccruals[0])
      || null;
    if (obj['salary_paid'] && accrual && accrual.amount != null && Number(accrual.amount) >= 0) {
      obj['net_total'] = Number(accrual.amount);
      obj['net_from_accrual'] = true;
    } else {
      obj['net_total'] = row.netTotal;
      obj['net_from_accrual'] = false;
    }
    return obj;
  }

  reviewMonth(e){
    if (this.user == 'Admin' && e.isReviewed == 0) {
      this.empService.reviewMonth(this.currentMonthValue , e.id, this.dateFrom, this.dateTo).subscribe({
        next: (res) => {
          if (res) {
            this.search(arguments);
            Swal.fire({
              icon:'success',
              timer:1500,
              showConfirmButton:false
            });
          }
        },
        error: (err) => {
          Swal.fire({
            icon: 'error',
            title: err?.error?.message || 'تعذر مراجعة الشهر',
          });
        },
      });
    }
  }

  private fillPaySourceIdSelect(typeSelect: HTMLSelectElement, idSelect: HTMLSelectElement): void {
    const t = typeSelect.value;
    const bundle = this.paymentSourcesBundle;
    idSelect.innerHTML = '';
    if (!bundle) {
      return;
    }
    const list = t === 'safe' ? bundle.safes : t === 'bank' ? bundle.banks : bundle.service_accounts;
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = '— اختر —';
    idSelect.appendChild(opt0);
    (list || []).forEach((row: any) => {
      const o = document.createElement('option');
      o.value = String(row.id);
      const bal = row.balance != null ? Number(row.balance).toFixed(2) : '';
      o.textContent = bal ? `${row.name} (رصيد: ${bal})` : row.name;
      idSelect.appendChild(o);
    });
  }

  private async promptPayrollDisbursementSource(title: string): Promise<{ source_type: string; source_id: number } | null> {
    if (!this.paymentSourcesBundle) {
      await Swal.fire({ icon: 'error', title: 'تعذر تحميل مصادر الصرف', text: 'تحقق من الاتصال ووحدة الحسابات (خزائن/بنوك/حسابات خدمية).' });
      return null;
    }
    const html = `
      <div class="text-end" style="direction:rtl;max-width:420px;margin:0 auto;">
        <label class="d-block mb-1 small text-muted">نوع المصدر</label>
        <select id="pay-src-type" class="swal2-input form-control mb-2">
          <option value="safe">خزينة</option>
          <option value="bank">بنك</option>
          <option value="service_account">حساب خدمي</option>
        </select>
        <label class="d-block mb-1 small text-muted">المصدر (من شجرة الحسابات)</label>
        <select id="pay-src-id" class="swal2-input form-control"></select>
      </div>`;

    const result = await Swal.fire({
      title,
      html,
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
      didOpen: () => {
        const ts = document.getElementById('pay-src-type') as HTMLSelectElement;
        const ids = document.getElementById('pay-src-id') as HTMLSelectElement;
        this.fillPaySourceIdSelect(ts, ids);
        ts.addEventListener('change', () => this.fillPaySourceIdSelect(ts, ids));
      },
      preConfirm: () => {
        const ts = document.getElementById('pay-src-type') as HTMLSelectElement;
        const ids = document.getElementById('pay-src-id') as HTMLSelectElement;
        if (!ids.value) {
          Swal.showValidationMessage('اختر المصدر');
          return false;
        }
        return { source_type: ts.value, source_id: parseInt(ids.value, 10) };
      },
    });

    if (result.isConfirmed && result.value) {
      return result.value as { source_type: string; source_id: number };
    }
    return null;
  }

  async salaryCashing(e: any) {
    if (e.isReviewed == 0) {
      await Swal.fire({
        icon: 'error',
        title: 'يرجي مراجعة كشف الحضور والانصراف',
      });
      return;
    }
    const emp = this.data.find((elm: any) => elm.id == e.id);
    if (!emp) {
      await Swal.fire({ icon: 'error', title: 'لم يُعثر على بيانات الموظف في الصفحة الحالية' });
      return;
    }

    const src = await this.promptPayrollDisbursementSource(`صرف مرتب ${e.name} عن شهر ${this.currentMonthValue}`);
    if (!src) {
      return;
    }

    const data = {
      employee_id: e.id,
      month: this.month,
      year: this.year,
      amount: emp.net_total,
      source_type: src.source_type,
      source_id: src.source_id,
    };

    this.empService.addSalaryPayment(data).subscribe({
      next: () => {
        Swal.fire({
          icon: 'success',
          timer: 1500,
          showConfirmButton: false,
        }).then(() => {
          this.search(arguments);
        });
      },
      error: (error) => {
        Swal.fire({
          icon: 'error',
          title: error.error?.message || 'خطأ',
          timer: 2500,
          showConfirmButton: false,
        });
      },
    });
  }

  async bulkSalaryDisbursement() {
    if (!this.paymentSourcesBundle) {
      await Swal.fire({ icon: 'error', title: 'تعذر تحميل مصادر الصرف من الحسابات' });
      return;
    }

    this.empService.EmployeesPerMonth(5000, 1, this.month, this.year, this.param).subscribe({
      next: async (result: any) => {
        const sourceRows = this.asPayrollApiRows(result);
        const rows = sourceRows.map((elm: any) => {
          try {
            return this.mapEmployeeMonthRow(elm);
          } catch (err) {
            console.error('تعذّر حساب صف المرتب', elm?.id, err);
            return this.fallbackPayrollRow(elm);
          }
        });
        const eligible = rows.filter(
          (r: any) => !r.salary_paid && r.isReviewed != 0 && r.net_total > 0,
        );
        if (eligible.length === 0) {
          await Swal.fire({
            icon: 'info',
            title: 'لا يوجد موظفون للصرف',
            text: 'تأكد من المراجعة وأن الراتب غير مسدد وأن المحصلة أكبر من صفر.',
          });
          return;
        }

        const sum = eligible.reduce((acc: number, r: any) => acc + r.net_total, 0);
        const confirmBulk = await Swal.fire({
          title: 'صرف جماعي',
          html: `<div class="text-end" style="direction:rtl">سيتم صرف مرتبات <strong>${eligible.length}</strong> موظفاً بإجمالي <strong>${sum.toFixed(2)}</strong> عن شهر ${this.currentMonthValue}.</div>`,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'متابعة اختيار المصدر',
          cancelButtonText: 'إلغاء',
        });
        if (!confirmBulk.isConfirmed) {
          return;
        }

        const src = await this.promptPayrollDisbursementSource(
          `اختر مصدر الصرف — ${eligible.length} موظف — شهر ${this.currentMonthValue}`,
        );
        if (!src) {
          return;
        }

        const payments = eligible.map((r: any) => ({ employee_id: r.id, amount: r.net_total }));
        this.empService
          .bulkSalaryPayment({
            month: Number(this.month),
            year: Number(this.year),
            source_type: src.source_type,
            source_id: src.source_id,
            payments,
          })
          .subscribe({
            next: (res: any) => {
              Swal.fire({
                icon: 'success',
                title: `تم صرف ${res.count ?? eligible.length} مرتب`,
                timer: 2000,
                showConfirmButton: false,
              }).then(() => this.search(arguments));
            },
            error: (error) => {
              Swal.fire({
                icon: 'error',
                title: error.error?.message || 'فشل الصرف الجماعي',
                showConfirmButton: true,
              });
            },
          });
      },
      error: async () => {
        await Swal.fire({ icon: 'error', title: 'تعذر تحميل قائمة الموظفين' });
      },
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
