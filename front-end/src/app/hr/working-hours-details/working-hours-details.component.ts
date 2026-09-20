import { Component, EventEmitter, Input, OnDestroy, OnInit, Output, SimpleChanges } from '@angular/core';
import { Subscription } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';
import { EmployeeService } from '../services/employee.service';
import { ActivatedRoute, Router } from '@angular/router';
import Swal from 'sweetalert2';
import { AuthService } from 'src/app/auth/auth.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { RBAC_ROUTE } from 'src/app/guards/rbac-route-data';
import { OvertimeMeritDialogComponent } from '../overtime-merit-dialog/overtime-merit-dialog.component';
import {
  applyNormalShiftTimes,
  annotateAttendanceDay,
  baseHourPrice,
  buildAttendanceMonthAccount,
  diffMsBetween,
  fullDayPermissionSavePayload,
  absentDayPlaceholderSavePayload,
  isAbsentDay,
  isFullDayPermissionLeave,
  absenceDayAmount,
  ABSENCE_DAY_DOUBLE,
  isAbsenceDoubled,
  isFullDayPermission,
  normalizeOvernightFingerPrintRecords,
  overtimeDeductionRate,
  overtimeMeritReasonLabel,
  parseOvertimeMeritReason,
  OvertimeMeritKind,
  extraDayMeritAmountAction,
  EXTRA_DAY_WORKING_DAYS,
  ATTENDANCE_HALF_DAY_BONUS,
  attendanceDayBonusAmount,
  attendanceDayBonusLabel,
  attendanceDayBonusReason,
  attendanceDayBonusType,
  parseAttendanceDayBonusReason,
  parseDeductionReason,
  deductionReasonLabel,
  deductionRowReason,
  overtimeMeritRowAmount,
  fridayAttendanceMeritAmount,
  fridayAttendanceLabel,
  parseFridayWorkedHours,
  monthlyHoursDivisor,
  monthHoursPaddingMinutes,
  OVERTIME_DEDUCTION_MULTIPLIER,
  hasFridayAttendance,
  parseDatetimeLocalValue,
  parseLocalDateTime,
  resolveCheckOutDate,
  resolveWorkDayHours,
  sumFingerprintWorkedMinutes,
  OVERNIGHT_CHECKOUT_CUTOFF_HOUR,
  syncDisplayTimesFromIso,
  toDatetimeLocalValue,
  toTimeInputValue,
  defaultFingerprintMonthValue,
  fingerprintPeriodForMonth,
  fridayDatesInRange,
  eachDateInRange,
  parseYearMonthValue
} from '../utils/fingerprint-hours.utils';

export interface DayOvertimeBadge {
  id?: number;
  kind: OvertimeMeritKind;
  hours: number | null;
  amount: number;
  reason: string;
  label: string;
}

export interface AttendanceDayBonus {
  id: number;
  date: string;
  days: number;
  amount: number;
  type: string;
  reason: string;
}

export interface WorkingHoursMonthAccount {
  fixedSalary: number;
  changedSalary: number;
  incentives: number;
  suits: number;
  rewards: number;
  overtimeFromHours: number;
  hoursDeduction: number;
  dailyIncentive: number;
  dailyDeduction: number;
  rival: number;
  absenceSub: number;
  advancePayment: number;
  totalMerit: number;
  totalSub: number;
  netTotal: number;
  overtimeHoursLabel: string;
  lateHoursLabel: string;
  extraDaysCount: number;
  extraDaysAmount: number;
  extraHoursFromMerits: number;
  extraHoursMeritsAmount: number;
  extraMeritsLabel: string;
  otherIncentives: number;
  deductionDaysCount: number;
  deductionDaysAmount: number;
  extraDeductionDaysCount?: number;
  extraDeductionDaysAmount?: number;
  extraDeductionHours?: number;
  extraDeductionHoursAmount?: number;
  otherRival?: number;
  halfDayBonusCount: number;
  halfDayBonusAmount: number;
  otherRewards: number;
  reviewed?: boolean;
}

export interface WorkingHoursDataEvent {
  tableData: any[];
  holidayDays: any[];
  totalHours: string;
  actualHours: string;
  fingerprintHours?: string;
  hoursDifferenceStr: string;
  fixedSalary: number;
  hourPrice: number;
  dayHours: number;
  formulaText: string;
  overtimeHourRate: number;
  totalActualHoursSalary: number;
  differnceSalary: number;
  monthAccount: WorkingHoursMonthAccount | null;
  merits?: any[];
  subtractions?: any[];
  advancePayments?: any[];
}

@Component({
  selector: 'app-working-hours-details',
  templateUrl: './working-hours-details.component.html',
  styleUrls: ['./working-hours-details.component.css']
})
export class WorkingHoursDetailsComponent implements OnInit, OnDestroy {
  @Input() dateFromEmp!: string;
  /** فترة مخصّصة قادمة من الأب (تتجاوز فترة التقفيل الافتراضية) — للشهور القديمة التي تبدأ يوم 1 */
  @Input() periodFrom?: string;
  @Input() periodTo?: string;
  @Output() dataEvent = new EventEmitter<WorkingHoursDataEvent>();
  id: any;
  currentMonthValue!: any
  dateFrom!: string;
  dateTo!: string;
  month!: any
  year!: any
  name!: any;
  tableData: any[] = [];
  holidayDays: any[] = [];
  filterDay!: string;
  url!: string;
  user!: string;
  is_overTime_removed!: boolean;

  /** أقسام أو صلاحيات الموارد البشرية المسموح لها بإدارة كشف الحضور */
  get canManageSheet(): boolean {
    if (this.rbac.canAny(RBAC_ROUTE.hrManageSheet)) {
      return true;
    }
    return this.user == 'Admin'
      || this.user == 'Operation Management'
      || this.user == 'Finance and operations management'
      || this.user == 'Financial Accounts';
  }

  constructor(
    private employeeService: EmployeeService,
    private route: ActivatedRoute,
    private authService: AuthService,
    private rbac: RbacService,
    private dialog: MatDialog,
  ) {
    this.applyMonthValue(defaultFingerprintMonthValue());
    this.id = this.route.snapshot.params['id'];
    this.holidayDaysFn();
    this.url = this.route.url['_value'][0]['path'];

  }

  ngOnDestroy(): void {
    this.listRequest?.unsubscribe();
  }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    // الصفحة المستقلة فقط: المضمّنة في كشف الحساب تتحمّل من ngOnChanges لتفادي طلب مكرر
    if (!this.dateFromEmp && !this.periodFrom) {
      this.getEmpDataPerMonth();
    }
  }

  ngOnChanges(changes: SimpleChanges): void {

    if (changes.dateFromEmp && changes.dateFromEmp.currentValue) {
      this.applyMonthValue(changes.dateFromEmp.currentValue);
    }

    // فترة مخصّصة من الأب تتجاوز فترة التقفيل الافتراضية
    if (changes.periodFrom || changes.periodTo) {
      this.applyCustomPeriod();
    }

    if (changes.dateFromEmp || changes.periodFrom || changes.periodTo) {
      this.holidayDaysFn();
      this.getEmpDataPerMonth();
    }
  }

  private applyCustomPeriod(): void {
    if (this.periodFrom) {
      this.dateFrom = this.periodFrom;
    }
    if (this.periodTo) {
      this.dateTo = this.periodTo;
    }
  }
  changedSalary!: number;
  salaryType!: string;
  totalHours!: string;
  actualHours!: string;
  fingerprintHours = '';
  hoursDifferenceStr!: string;
  dayHours!: number;
  merits: any[] = [];
  subtractions: any[] = [];
  advancePayments: any[] = [];
  monthAccount: WorkingHoursMonthAccount | null = null;
  /** إضافي محفوظ كحوافز مفهرس بتاريخ اليوم */
  overtimeByDate: Record<string, DayOvertimeBadge[]> = {};
  deductionByDate: Record<string, DayOvertimeBadge[]> = {};
  attendanceBonusByDate: Record<string, AttendanceDayBonus> = {};

  get monthlyHoursDivisorValue(): number {
    return monthlyHoursDivisor(this.dayHours || 8);
  }

  get overtimeHourRate(): number {
    return overtimeDeductionRate(this.fixedSalary || 0, this.dayHours || 8);
  }

  get extraDayFormulaDetail(): string {
    const salary = Number(this.fixedSalary || 0).toFixed(2);
    const divisor = EXTRA_DAY_WORKING_DAYS * (this.dayHours || 8);
    const amount = this.extraDayAmount.toFixed(2);
    return `(الراتب ${salary} ÷ ${divisor}) × ${OVERTIME_DEDUCTION_MULTIPLIER} × ${this.dayHours} س = ${amount} ج`;
  }

  get extraDayAmount(): number {
    return extraDayMeritAmountAction(this.fixedSalary || 0, this.dayHours || 8);
  }

  get formulaText(): string {
    return `(الراتب ÷ ${this.monthlyHoursDivisorValue}) × ${OVERTIME_DEDUCTION_MULTIPLIER} × الساعات`;
  }

  private inFlightLoadKey = '';
  private listRequest?: Subscription;

  getEmpDataPerMonth() {
    if (!this.id || !this.dateFrom || !this.dateTo) {
      return;
    }
    const loadKey = `${this.id}|${this.dateFrom}|${this.dateTo}|${this.filterDay || ''}`;
    if (this.inFlightLoadKey === loadKey) {
      return;
    }
    this.inFlightLoadKey = loadKey;
    let param = {
      month: this.currentMonthValue,
      date_from: this.dateFrom,
      date_to: this.dateTo,
    }
    if (this.filterDay) {
      param['filterDay'] = this.filterDay;
      param['dayHours'] = '08:00';
      if (this.dayHours == 9) {
        param['dayHours'] = '09:00';
      }
    }
    this.listRequest?.unsubscribe();
    this.listRequest = this.employeeService.getEmpDataPerMonth(this.id, param).subscribe({
      next: (res) => {
      if (this.inFlightLoadKey !== loadKey) {
        return;
      }
      if (!res || !res.id) {
        return;
      }
      this.tableData = [];
      this.name = res.name;
      this.fixedSalary = res.fixed_salary;
      let workingHourPerDay = 8;
      if (res.working_hours) {
        workingHourPerDay = res.working_hours;
      }
      const prints = Array.isArray(res.finger_print)
        ? res.finger_print
        : (Array.isArray(res.fingerPrint) ? res.fingerPrint : []);
      res.finger_print = prints;
      if (prints.length) {
        prints.forEach((r: { working_hours?: number }) => {
          r.working_hours = workingHourPerDay;
        });
        res.finger_print = normalizeOvernightFingerPrintRecords(prints);
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
          this.is_overTime_removed = (res.finger_print || []).some(elm => elm.is_overTime_removed == null && elm.hours > hour);
          this.changedSalary = res.merits.filter(elm => elm.type === "الراتب المتغير").reduce((acc, elm) => acc + elm.amount, 0);
        }
      }
      this.dayHours = workingHourPerDay;
      let totalHours = workingHourPerDay * 60;
      let totalHoursPerMonth = workingHourPerDay * 26 * 60;
      let actualTotalMinutesPerMonth = 0;
      this.totalHours = this.convertMinutesToHours(totalHoursPerMonth);
      this.hourPrice = baseHourPrice(this.fixedSalary, this.dayHours);

      const periodDates = eachDateInRange(this.dateFrom, this.dateTo);

      for (const dateStr of periodDates) {

        // Try to find existing record
        let elm = (res.finger_print || []).find(r => String(r.date || '').slice(0, 10) === dateStr);

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
        syncDisplayTimesFromIso(elm);

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

        if (hasFingerPrints) {
          actualTotalMinutesPerMonth += annotateAttendanceDay(elm, {
            dayHours: workingHourPerDay,
            fixedSalary: this.fixedSalary || 0,
            hourPrice: this.hourPrice,
          });
        }

        this.tableData.push(elm);
      } // end for loop

      // total for month logic ...
      if (this.tableData.length > 0 && (!param['filterDay'] || param['filterDay'] == 'all')) {
        actualTotalMinutesPerMonth -= monthHoursPaddingMinutes(
          this.tableData.length,
          this.holidayDays.length,
          this.dayHours
        );
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
        this.fingerprintHours = this.convertMinutesToHours(
          sumFingerprintWorkedMinutes(this.tableData, workingHourPerDay)
        );
      } else {
        this.actualHours = ''; // Empty string instead of calculated value
        this.hoursDifferenceStr = ''; // Empty string instead of calculated value
        this.fingerprintHours = '';
      }

      this.calcSalary();
      this.applyMonthAccountFromEmployee(res);
    },
    error: (err) => {
      if (this.inFlightLoadKey === loadKey) {
        this.inFlightLoadKey = '';
      }
      console.error('تعذّر تحميل كشف الحضور', err);
    },
    complete: () => {
      if (this.inFlightLoadKey === loadKey) {
        this.inFlightLoadKey = '';
      }
    },
    });
  }

  fixedSalary: number = 0;
  hourPrice: number = 0;
  totalActualHoursSalary: number = 0;
  differnceSalary: number = 0;

  private applyMonthAccountFromEmployee(res: any): void {
    const hasAccountRelations = Array.isArray(res?.subtraction) || Array.isArray(res?.advance_payment);
    if (hasAccountRelations) {
      this.merits = res.merits || [];
      this.subtractions = res.subtraction || [];
      this.advancePayments = res.advance_payment || [];
      this.buildMonthAccountSummary();
      return;
    }
    this.loadMonthAccountData();
  }

  private loadMonthAccountData(): void {
    if (!this.id || !this.month || !this.year) {
      return;
    }
    this.employeeService.dataPerMonth(this.id, this.month, this.year).subscribe((account: any) => {
      this.merits = account?.merits || [];
      this.subtractions = account?.subtraction || [];
      this.advancePayments = account?.advance_payment || [];
      this.buildMonthAccountSummary();
    });
  }

  private buildMonthAccountSummary(): void {
    this.monthAccount = buildAttendanceMonthAccount({
      tableData: this.tableData,
      merits: this.merits,
      subtractions: this.subtractions,
      advancePayments: this.advancePayments,
      fixedSalary: this.fixedSalary || 0,
    });
    this.isReviewed = !!this.monthAccount.reviewed;
    this.rebuildOvertimeByDate();
    this.emitDataEvent();
  }

  private rebuildOvertimeByDate(): void {
    const map: Record<string, DayOvertimeBadge[]> = {};
    for (const item of this.merits) {
      if (item.type !== 'حوافز') {
        continue;
      }
      const parsed = parseOvertimeMeritReason(item.reason);
      if (!parsed) {
        continue;
      }
      if (!map[parsed.date]) {
        map[parsed.date] = [];
      }
      map[parsed.date].push({
        id: item.id,
        kind: parsed.kind,
        hours: parsed.hours,
        amount: Number(item.amount) || 0,
        reason: String(item.reason || ''),
        label: overtimeMeritReasonLabel(parsed),
      });
    }
    this.overtimeByDate = map;
    this.rebuildDeductionByDate();
    this.rebuildAttendanceBonusByDate();
    this.applyFridayRewardLabels();
  }

  private rebuildDeductionByDate(): void {
    const map: Record<string, DayOvertimeBadge[]> = {};
    for (const item of this.subtractions || []) {
      if (item.type !== 'خصومات') {
        continue;
      }
      const parsed = parseDeductionReason(item.reason);
      if (!parsed) {
        continue;
      }
      if (!map[parsed.date]) {
        map[parsed.date] = [];
      }
      map[parsed.date].push({
        id: item.id,
        kind: parsed.kind,
        hours: parsed.hours,
        amount: Number(item.amount) || 0,
        reason: String(item.reason || ''),
        label: deductionReasonLabel(parsed),
      });
    }
    this.deductionByDate = map;
  }

  private rebuildAttendanceBonusByDate(): void {
    const map: Record<string, AttendanceDayBonus> = {};
    for (const item of this.merits || []) {
      const parsed = parseAttendanceDayBonusReason(item.reason);
      if (!parsed) {
        continue;
      }
      map[parsed.date] = {
        id: item.id,
        date: parsed.date,
        days: parsed.days,
        amount: Number(item.amount) || 0,
        type: String(item.type || ''),
        reason: String(item.reason || ''),
      };
    }
    this.attendanceBonusByDate = map;
  }

  /** يطابق صف الجمعة مع الحافز/المكافأة المحفوظة */
  private applyFridayRewardLabels(): void {
    const fridayBonusByDate = this.mapFridayBonusMerits();

    for (const row of this.tableData) {
      if (!row.friday_work && !(row.holiday && hasFridayAttendance(row))) {
        continue;
      }
      row.friday_work = true;

      // المكافأة لها أولوية على تقدير المعادلة
      const fridayBonus = fridayBonusByDate[row.date];
      if (fridayBonus) {
        row.salary_type = Number(fridayBonus.amount) || 0;
        row.salary_type2 = 'حافز';
        row.friday_label = 'احتسب مكافأة';
        continue;
      }

      const dayMerits = this.overtimeByDate[row.date] || [];
      if (dayMerits.length) {
        const total = dayMerits.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
        const worked = parseFridayWorkedHours(row.hours);
        row.salary_type = total;
        row.salary_type2 = 'حافز';
        row.friday_label = fridayAttendanceLabel(worked, this.dayHours || 8);
        continue;
      }

      // تقدير محلي قبل اكتمال تحميل الحوافز / عند غياب السجل
      const worked = parseFridayWorkedHours(row.hours);
      const estimated = fridayAttendanceMeritAmount(
        this.fixedSalary || 0,
        this.dayHours || 8,
        worked
      );
      row.salary_type = estimated;
      row.salary_type2 = 'حافز';
      row.friday_label = fridayAttendanceLabel(worked, this.dayHours || 8);
    }
  }

  /** يربط مكافآت الجمعة بالتاريخ (ويدعم السجلات القديمة بدون تاريخ في السبب) */
  private mapFridayBonusMerits(): Record<string, any> {
    const map: Record<string, any> = {};
    const fridayDates = this.tableData
      .filter((row) => row.holiday && hasFridayAttendance(row))
      .map((row) => row.date)
      .sort();

    const bonuses = (this.merits || []).filter((item) => {
      if (item.type !== 'مكافئات') {
        return false;
      }
      const parsed = parseAttendanceDayBonusReason(item.reason);
      return !parsed || parsed.days !== ATTENDANCE_HALF_DAY_BONUS;
    });
    const used = new Set<number>();

    for (const date of fridayDates) {
      const linked = bonuses.find((item) => {
        if (used.has(item.id)) {
          return false;
        }
        const reason = String(item.reason || '');
        return reason.includes(`مكافأة حضور الجمعة (${date})`) || reason.includes(`(${date})`);
      });
      if (linked) {
        map[date] = linked;
        used.add(linked.id);
      }
    }

    // سجلات قديمة بدون تاريخ في السبب (مثل ..........)
    for (const date of fridayDates) {
      if (map[date] || (this.overtimeByDate[date] || []).length) {
        continue;
      }
      const orphan = bonuses.find((item) => {
        if (used.has(item.id)) {
          return false;
        }
        const reason = String(item.reason || '');
        return !/\(\d{4}-\d{2}-\d{2}\)/.test(reason);
      });
      if (orphan) {
        map[date] = orphan;
        used.add(orphan.id);
      }
    }

    return map;
  }

  overtimeForDay(date: string): DayOvertimeBadge[] {
    return this.overtimeByDate[date] || [];
  }

  deductionForDay(date: string): DayOvertimeBadge[] {
    return this.deductionByDate[date] || [];
  }

  deductionDayTitle(date: string): string {
    const items = this.deductionForDay(date);
    if (!items.length) {
      return '';
    }
    return items
      .map((item) => `${item.label}: ${Number(item.amount).toFixed(2)} ج`)
      .join(' | ');
  }

  showDeductionDayDetails(date: string, event?: Event): void {
    event?.stopPropagation();
    const items = this.deductionForDay(date);
    if (!items.length) {
      return;
    }
    const rows = items
      .map(
        (item) =>
          `<tr><td>${item.label}</td><td dir="ltr">${Number(item.amount).toFixed(2)} ج</td></tr>`
      )
      .join('');
    const total = items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
    Swal.fire({
      title: `خصم يوم ${date}`,
      html: `
        <table class="table table-sm table-bordered text-center mb-2">
          <thead><tr><th>النوع</th><th>المبلغ</th></tr></thead>
          <tbody>${rows}</tbody>
          <tfoot><tr class="fw-bold"><td>الإجمالي</td><td dir="ltr">${total.toFixed(2)} ج</td></tr></tfoot>
        </table>
      `,
      confirmButtonText: 'حسناً',
    });
  }

  overtimeDayTitle(date: string): string {
    const items = this.overtimeForDay(date);
    if (!items.length) {
      return '';
    }
    return items
      .map((item) => `إضافي ${item.label}: ${Number(item.amount).toFixed(2)} ج`)
      .join(' | ');
  }

  showOvertimeDayDetails(date: string, event?: Event): void {
    event?.stopPropagation();
    const items = this.overtimeForDay(date);
    if (!items.length) {
      return;
    }
    const rows = items
      .map(
        (item) =>
          `<tr><td>${item.label}</td><td dir="ltr">${Number(item.amount).toFixed(2)} ج</td></tr>`
      )
      .join('');
    const total = items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
    Swal.fire({
      title: `إضافي يوم ${date}`,
      html: `
        <table class="table table-sm table-bordered text-center mb-2">
          <thead><tr><th>النوع</th><th>المبلغ</th></tr></thead>
          <tbody>${rows}</tbody>
          <tfoot><tr class="fw-bold"><td>الإجمالي</td><td dir="ltr">${total.toFixed(2)} ج</td></tr></tfoot>
        </table>
      `,
      confirmButtonText: 'حسناً',
    });
  }

  private emitDataEvent(): void {
    this.dataEvent.emit({
      tableData: this.tableData,
      holidayDays: this.holidayDays,
      totalHours: this.totalHours,
      actualHours: this.actualHours,
      fingerprintHours: this.fingerprintHours,
      hoursDifferenceStr: this.hoursDifferenceStr,
      fixedSalary: this.fixedSalary,
      hourPrice: this.hourPrice,
      dayHours: this.dayHours || 8,
      formulaText: this.formulaText,
      overtimeHourRate: this.overtimeHourRate,
      totalActualHoursSalary: this.totalActualHoursSalary,
      differnceSalary: this.differnceSalary,
      monthAccount: this.monthAccount,
      merits: this.merits,
      subtractions: this.subtractions,
      advancePayments: this.advancePayments,
    });
  }

  openOvertimeMeritDialog(mode: 'merit' | 'deduction' = 'merit'): void {
    if (!this.fixedSalary) {
      Swal.fire({ icon: 'warning', title: 'لا يوجد راتب ثابت لهذا الموظف', timer: 2000, showConfirmButton: false });
      return;
    }
    const ref = this.dialog.open(OvertimeMeritDialogComponent, {
      width: '780px',
      maxWidth: '95vw',
      data: {
        employeeId: Number(this.id),
        employeeName: this.name || '',
        month: Number(this.month),
        year: Number(this.year),
        fixedSalary: Number(this.fixedSalary || 0),
        dayHours: Number(this.dayHours || 8),
        mode,
        dateFrom: this.dateFrom,
        dateTo: this.dateTo,
      },
    });
    ref.afterClosed().subscribe((result) => {
      if (result?.saved) {
        this.loadMonthAccountData();
      }
    });
  }

  openDeductionDialog(): void {
    this.openOvertimeMeritDialog('deduction');
  }

  addDeductionAmount(): void {
    Swal.fire({
      title: 'إضافة خصم بمبلغ معيّن',
      html: `
        <label class="d-block text-end mb-1">المبلغ</label>
        <input id="flat-deduct-amount" type="number" min="0.01" step="0.01" class="swal2-input" placeholder="مثال: 150" style="display:flex;width:100%;margin:0 0 12px">
        <label class="d-block text-end mb-1">السبب (اختياري)</label>
        <input id="flat-deduct-reason" type="text" class="swal2-input" placeholder="سبب الخصم" style="display:flex;width:100%;margin:0">
      `,
      showCancelButton: true,
      confirmButtonText: 'حفظ',
      cancelButtonText: 'إلغاء',
      focusConfirm: false,
      preConfirm: () => {
        const amount = Number((document.getElementById('flat-deduct-amount') as HTMLInputElement | null)?.value);
        if (!amount || amount <= 0) {
          Swal.showValidationMessage('أدخل مبلغ الخصم');
          return false;
        }
        const reason = String((document.getElementById('flat-deduct-reason') as HTMLInputElement | null)?.value || '').trim();
        return { amount, reason: reason || 'خصم مبلغ' };
      },
    }).then((result) => {
      if (!result.isConfirmed || !result.value) {
        return;
      }
      this.employeeService.addSubtraction({
        employee_id: this.id,
        month: this.month,
        year: this.year,
        type: 'خصومات',
        amount: Number(result.value.amount),
        reason: result.value.reason,
      }).subscribe({
        next: () => {
          Swal.fire({ icon: 'success', title: 'تم الخصم', timer: 1500, showConfirmButton: false });
          this.loadMonthAccountData();
        },
        error: (err) => {
          Swal.fire({ icon: 'error', title: 'خطأ', text: err?.error?.message || 'تعذر الحفظ' });
        },
      });
    });
  }

  addBonusMerit(): void {
    Swal.fire({
      title: 'إضافة مكافأة',
      input: 'number',
      inputPlaceholder: 'المبلغ',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value || Number(value) <= 0) {
          return 'يجب إدخال مبلغ صحيح';
        }
        return undefined;
      },
    }).then((result) => {
      if (!result.isConfirmed || !result.value) {
        return;
      }
      Swal.fire({
        title: 'سبب المكافأة (اختياري)',
        input: 'text',
        showCancelButton: true,
      }).then((reasonResult) => {
        const reason = reasonResult.isConfirmed && reasonResult.value ? String(reasonResult.value) : 'مكافأة';
        this.saveMerit('مكافئات', Number(result.value), reason);
      });
    });
  }

  private saveMerit(type: string, amount: number, reason: string): void {
    this.employeeService.addMerit({
      employee_id: this.id,
      month: this.month,
      year: this.year,
      type,
      amount,
      reason,
    }).subscribe({
      next: () => {
        Swal.fire({ icon: 'success', title: 'تمت الإضافة', timer: 1500, showConfirmButton: false });
        this.loadMonthAccountData();
      },
      error: (err) => {
        Swal.fire({ icon: 'error', title: 'خطأ', text: err?.error?.message || 'تعذر الحفظ' });
      },
    });
  }

  calcSalary() {
    // If no actual hours (no fingerprints), set values to 0
    if (!this.actualHours) {
      this.totalActualHoursSalary = 0;
      this.differnceSalary = 0;
      this.buildMonthAccountSummary();
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
    this.buildMonthAccountSummary();
  }

  convertMinutesToHours(minutes: number): string {
    let h = Math.floor(minutes / 60);
    let m = minutes % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
  }

  private stripHoursSign(value: string | null | undefined): string {
    if (!value) {
      return '';
    }
    return String(value).replace(/^-/, '');
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

  isAbsentDay(elm: any): boolean {
    return isAbsentDay(elm);
  }

  isAbsenceDoubled(elm: any): boolean {
    return isAbsenceDoubled(elm?.absence_deduction);
  }

  absenceDayDoublePayload(elm: any): Record<string, unknown> {
    return this.sheetActionPayload(elm, { absence_deduction: String(ABSENCE_DAY_DOUBLE) });
  }

  absenceDayUndoDoublePayload(elm: any): Record<string, unknown> {
    return this.sheetActionPayload(elm, { absence_deduction: null });
  }

  isFullDayPermissionLeave(elm: any): boolean {
    return isFullDayPermissionLeave(elm, this.dayHours || 8);
  }

  showSheetRowMenu(elm: any): boolean {
    if (elm?.holiday || elm?.vacation) {
      return false;
    }
    if (this.isFullDayPermissionLeave(elm) || this.isAbsentDay(elm) || this.isRegularAttendanceDay(elm)) {
      return true;
    }
    return elm.hoursDifference < '00:00' && elm.salary_type !== 0 && !elm.absence_deduction;
  }

  isRegularAttendanceDay(elm: any): boolean {
    if (!elm || elm.holiday || elm.vacation) {
      return false;
    }
    if (this.isAbsentDay(elm) || this.isFullDayPermissionLeave(elm)) {
      return false;
    }
    return !!elm.hours && elm.hours !== '00:00';
  }

  attendanceBonusForDay(date: string): AttendanceDayBonus | null {
    return this.attendanceBonusByDate[date] || null;
  }

  attendanceBonusText(bonus: AttendanceDayBonus): string {
    return attendanceDayBonusLabel(bonus.days);
  }

  attendanceDayBonusPayload(elm: any, days: number): Record<string, unknown> {
    const date = String(elm?.date || '');
    return {
      employee_id: this.id,
      month: this.month,
      year: this.year,
      type: attendanceDayBonusType(days),
      amount: Number(attendanceDayBonusAmount(this.fixedSalary || 0, this.dayHours || 8, days).toFixed(2)),
      reason: attendanceDayBonusReason(date, days),
    };
  }

  addDayDeduction(e: any): void {
    if (!this.canManageSheet || !this.isRegularAttendanceDay(e)) {
      return;
    }
    if (!this.fixedSalary) {
      Swal.fire({ icon: 'warning', title: 'لا يوجد راتب ثابت لهذا الموظف', timer: 2000, showConfirmButton: false });
      return;
    }
    const dayAmount = overtimeMeritRowAmount(this.fixedSalary || 0, this.dayHours || 8, {
      date: e.date,
      kind: 'day',
    });
    const hourRate = this.overtimeHourRate;
    const dayLabel = dayAmount.toFixed(2);
    const rateLabel = hourRate.toFixed(2);
    Swal.fire({
      title: 'خصم على يوم الحضور',
      html: `
        <span class="hr-deduct-date">${e.date}</span>
        <div class="hr-deduct-field">
          <label for="deduct-kind">نوع الخصم</label>
          <select id="deduct-kind">
            <option value="">اختر النوع</option>
            <option value="day">يوم كامل ≈ ${dayLabel} ج</option>
            <option value="hours">ساعات (× ${rateLabel} ج)</option>
          </select>
        </div>
        <div id="deduct-hours-wrap" class="hr-deduct-field" style="display:none">
          <label for="deduct-hours">عدد الساعات المراد خصمها</label>
          <input id="deduct-hours" type="number" min="0.25" step="0.25" value="1" placeholder="مثال: 2 أو 1.5">
          <div id="deduct-result" class="hr-deduct-result">
            <span class="hr-deduct-result__label">المبلغ الذي سيتم خصمه</span>
            <span id="deduct-result-value" class="hr-deduct-result__value">${rateLabel} ج</span>
            <span class="hr-deduct-result__hint">1 ساعة × ${rateLabel} ج</span>
          </div>
        </div>
        <div id="deduct-day-result" class="hr-deduct-result" style="display:none">
          <span class="hr-deduct-result__label">المبلغ الذي سيتم خصمه</span>
          <span class="hr-deduct-result__value">${dayLabel} ج</span>
          <span class="hr-deduct-result__hint">يوم كامل</span>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'حفظ',
      cancelButtonText: 'إلغاء',
      focusConfirm: false,
      customClass: {
        popup: 'hr-deduct-popup',
        title: 'hr-deduct-title',
        htmlContainer: 'hr-deduct-body',
        actions: 'hr-deduct-actions',
        confirmButton: 'hr-deduct-confirm',
        cancelButton: 'hr-deduct-cancel',
      },
      didOpen: () => {
        const kindEl = document.getElementById('deduct-kind') as HTMLSelectElement | null;
        const wrapEl = document.getElementById('deduct-hours-wrap');
        const dayResultEl = document.getElementById('deduct-day-result');
        const hoursEl = document.getElementById('deduct-hours') as HTMLInputElement | null;
        const valueEl = document.getElementById('deduct-result-value');
        const hintEl = wrapEl?.querySelector('.hr-deduct-result__hint') as HTMLElement | null;
        const formatAmount = (value: number) => value.toFixed(2);
        const updateHoursResult = () => {
          const hours = Number(hoursEl?.value);
          const safeHours = hours > 0 ? hours : 0;
          const total = safeHours * hourRate;
          if (valueEl) {
            valueEl.textContent = `${formatAmount(total)} ج`;
          }
          if (hintEl) {
            hintEl.textContent = safeHours > 0
              ? `${safeHours} ساعة × ${rateLabel} ج`
              : `سعر الساعة ${rateLabel} ج`;
          }
        };
        const toggleKind = () => {
          const kind = kindEl?.value;
          if (wrapEl) {
            wrapEl.style.display = kind === 'hours' ? 'block' : 'none';
          }
          if (dayResultEl) {
            dayResultEl.style.display = kind === 'day' ? 'block' : 'none';
          }
          if (kind === 'hours') {
            updateHoursResult();
            hoursEl?.focus();
            hoursEl?.select();
          }
        };
        kindEl?.addEventListener('change', toggleKind);
        hoursEl?.addEventListener('input', updateHoursResult);
        toggleKind();
      },
      preConfirm: () => {
        const kindEl = document.getElementById('deduct-kind') as HTMLSelectElement | null;
        const kind = kindEl?.value === 'hours' ? 'hours' : kindEl?.value === 'day' ? 'day' : '';
        if (!kind) {
          Swal.showValidationMessage('اختر يوم كامل أو ساعات');
          return false;
        }
        if (kind === 'day') {
          return { kind, hours: null as number | null };
        }
        const hours = Number((document.getElementById('deduct-hours') as HTMLInputElement | null)?.value);
        if (!hours || hours <= 0) {
          Swal.showValidationMessage('أدخل عدد الساعات التي تريد خصمها');
          return false;
        }
        return { kind, hours };
      },
    }).then((result) => {
      if (!result.isConfirmed || !result.value) {
        return;
      }
      const kind = result.value.kind === 'hours' ? 'hours' : 'day';
      const hours = kind === 'hours' ? Number(result.value.hours) : null;
      const amount = Number(overtimeMeritRowAmount(this.fixedSalary || 0, this.dayHours || 8, {
        date: e.date,
        kind,
        hours,
      }).toFixed(2));
      this.employeeService.addSubtraction({
        employee_id: this.id,
        month: this.month,
        year: this.year,
        type: 'خصومات',
        amount,
        reason: deductionRowReason({ date: e.date, kind, hours }),
      }).subscribe({
        next: () => {
          this.loadMonthAccountData();
          this.showFingerprintMutationSuccess();
        },
        error: (err) => {
          Swal.fire({ icon: 'error', title: 'خطأ', text: err?.error?.message || 'تعذر الحفظ' });
        },
      });
    });
  }

  addAttendanceDayBonus(e: any): void {
    if (!this.canManageSheet || !this.isRegularAttendanceDay(e) || this.attendanceBonusForDay(e.date)) {
      return;
    }
    if (!this.fixedSalary) {
      Swal.fire({ icon: 'warning', title: 'لا يوجد راتب ثابت لهذا الموظف', timer: 2000, showConfirmButton: false });
      return;
    }

    const fullAmount = attendanceDayBonusAmount(this.fixedSalary || 0, this.dayHours || 8, 1);
    const halfAmount = attendanceDayBonusAmount(this.fixedSalary || 0, this.dayHours || 8, ATTENDANCE_HALF_DAY_BONUS);
    Swal.fire({
      title: 'إضافة على يوم الحضور',
      input: 'select',
      inputOptions: {
        '1': `يوم كامل (مضاعفة) ≈ ${fullAmount.toFixed(2)} ج`,
        '0.5': `نصف يوم مكافأة ≈ ${halfAmount.toFixed(2)} ج`,
      },
      inputPlaceholder: 'اختر القيمة',
      showCancelButton: true,
      confirmButtonText: 'حفظ',
      cancelButtonText: 'إلغاء',
      inputValidator: (value) => value ? undefined : 'اختر يوم كامل أو نصف يوم',
    }).then((result) => {
      if (!result.isConfirmed || !result.value) {
        return;
      }
      const days = Number(result.value);
      this.employeeService.addMerit(this.attendanceDayBonusPayload(e, days)).subscribe({
        next: () => {
          this.loadMonthAccountData();
          this.showFingerprintMutationSuccess();
        },
        error: (err) => {
          Swal.fire({ icon: 'error', title: 'خطأ', text: err?.error?.message || 'تعذر الحفظ' });
        },
      });
    });
  }

  undoAttendanceDayBonus(e: any): void {
    const bonus = this.attendanceBonusForDay(e?.date);
    if (!this.canManageSheet || !bonus) {
      return;
    }
    Swal.fire({
      title: 'إلغاء الإضافة على اليوم',
      text: `سيتم حذف ${this.attendanceBonusText(bonus)}`,
      showCancelButton: true,
      confirmButtonText: 'حذف',
      cancelButtonText: 'رجوع',
      confirmButtonColor: '#d33',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.employeeService.deleteMerit(bonus.id).subscribe({
        next: () => {
          this.loadMonthAccountData();
          this.showFingerprintMutationSuccess();
        },
        error: () => {
          Swal.fire({ icon: 'error', text: 'تعذّر حذف الإضافة' });
        },
      });
    });
  }

  /** جمعة بدون حضور — يمكن تسجيل حضور يُضاف كيوم إضافي */
  isEmptyFriday(elm: any): boolean {
    return !!elm?.holiday && !hasFridayAttendance(elm) && !elm?.vacation;
  }

  /** إلغاء الغياب / تسجيل حضور الجمعة + انصراف على نفس اليوم */
  registerAttendance(e: any): void {
    if (!this.canManageSheet || (!this.isAbsentDay(e) && !this.isEmptyFriday(e))) {
      return;
    }

    const isFriday = this.isEmptyFriday(e);
    const [outHRaw, outMRaw] = this.addHoursToTime('08:00 AM', this.dayHours).split(':');
    const defaultOut = `${String(outHRaw).padStart(2, '0')}:${String(outMRaw).padStart(2, '0')}`;
    const dayHours = this.dayHours || 8;
    const fullFridayHint = isFriday
      ? Number(fridayAttendanceMeritAmount(this.fixedSalary || 0, dayHours, dayHours)).toFixed(2)
      : '';
    const overtimeFridayHint = isFriday
      ? Number(fridayAttendanceMeritAmount(this.fixedSalary || 0, dayHours, dayHours + 2)).toFixed(2)
      : '';
    const samplePartialHours = Math.max(dayHours - 1, 1);
    const partialFridayHint = isFriday
      ? Number(fridayAttendanceMeritAmount(this.fixedSalary || 0, dayHours, samplePartialHours)).toFixed(2)
      : '';

    const fridayRewardHtml = isFriday ? `
        <hr class="my-2">
        <p class="small text-muted mb-2" style="max-width:360px;margin:0 auto;">
          = ${dayHours} س: يوم عادي فقط ≈ ${fullFridayHint} ج<br>
          أكثر من ${dayHours} س: يوم عادي + ساعات إضافي (مثال ${dayHours + 2}س ≈ ${overtimeFridayHint} ج)<br>
          أقل من ${dayHours} س: ساعات إضافي فقط (مثال ${samplePartialHours}س ≈ ${partialFridayHint} ج)
        </p>
        <label class="d-block mb-1 fw-bold">نوع الإضافة</label>
        <div class="text-start px-3" style="max-width:320px;margin:0 auto;">
          <label class="d-block mb-1">
            <input type="radio" name="friday-reward" value="extra_day" checked>
            حسب معادلة الجمعة
          </label>
          <label class="d-block mb-1">
            <input type="radio" name="friday-reward" value="bonus">
            مكافأة بمبلغ
          </label>
          <input type="number" id="friday-bonus-amount" class="swal2-input" placeholder="مبلغ المكافأة"
            style="margin:0.25rem auto;display:none;" min="1" step="0.01">
          <input type="text" id="friday-bonus-reason" class="swal2-input" placeholder="سبب المكافأة (اختياري)"
            style="margin:0.25rem auto;display:none;">
          <label class="d-block mt-1">
            <input type="radio" name="friday-reward" value="none">
            بدون حافز / مكافأة
          </label>
        </div>
      ` : '';

    Swal.fire({
      title: isFriday ? 'تسجيل حضور الجمعة' : 'تسجيل حضور / انصراف',
      html: `
        <label class="d-block mb-1">وقت الحضور</label>
        <input type="time" id="absent-check-in" value="08:00" class="swal2-input" style="margin:0.25rem auto;" required>
        <label class="d-block mt-2 mb-1">وقت الانصراف</label>
        <input type="time" id="absent-check-out" value="${defaultOut}" class="swal2-input" style="margin:0.25rem auto;" required>
        ${fridayRewardHtml}
      `,
      showCancelButton: true,
      confirmButtonText: 'حفظ',
      cancelButtonText: 'إلغاء',
      didOpen: () => {
        if (!isFriday) {
          return;
        }
        const toggleBonus = () => {
          const selected = (Swal.getPopup()?.querySelector('input[name="friday-reward"]:checked') as HTMLInputElement | null)?.value;
          const amountEl = Swal.getPopup()?.querySelector('#friday-bonus-amount') as HTMLInputElement | null;
          const reasonEl = Swal.getPopup()?.querySelector('#friday-bonus-reason') as HTMLInputElement | null;
          const show = selected === 'bonus';
          if (amountEl) {
            amountEl.style.display = show ? 'block' : 'none';
          }
          if (reasonEl) {
            reasonEl.style.display = show ? 'block' : 'none';
          }
        };
        Swal.getPopup()?.querySelectorAll('input[name="friday-reward"]').forEach((el) => {
          el.addEventListener('change', toggleBonus);
        });
        toggleBonus();
      },
      preConfirm: () => {
        const inEl = Swal.getPopup()?.querySelector('#absent-check-in') as HTMLInputElement | null;
        const outEl = Swal.getPopup()?.querySelector('#absent-check-out') as HTMLInputElement | null;
        if (!inEl?.value || !outEl?.value) {
          Swal.showValidationMessage('يجب إدخال وقت الحضور والانصراف');
          return null;
        }

        let fridayRewardType: 'extra_day' | 'bonus' | 'none' | undefined;
        let fridayBonusAmount: number | undefined;
        let fridayBonusReason: string | undefined;

        if (isFriday) {
          fridayRewardType = ((Swal.getPopup()?.querySelector('input[name="friday-reward"]:checked') as HTMLInputElement | null)?.value
            || 'extra_day') as 'extra_day' | 'bonus' | 'none';
          if (fridayRewardType === 'bonus') {
            const amountEl = Swal.getPopup()?.querySelector('#friday-bonus-amount') as HTMLInputElement | null;
            const reasonEl = Swal.getPopup()?.querySelector('#friday-bonus-reason') as HTMLInputElement | null;
            fridayBonusAmount = Number(amountEl?.value || 0);
            if (!fridayBonusAmount || fridayBonusAmount <= 0) {
              Swal.showValidationMessage('أدخل مبلغ المكافأة');
              return null;
            }
            fridayBonusReason = reasonEl?.value?.trim() || undefined;
          }
        }

        return {
          checkIn: inEl.value,
          checkOut: outEl.value,
          fridayRewardType,
          fridayBonusAmount,
          fridayBonusReason,
        };
      },
    }).then((result) => {
      if (!result.isConfirmed || !result.value) {
        return;
      }

      const {
        checkIn,
        checkOut,
        fridayRewardType,
        fridayBonusAmount,
        fridayBonusReason,
      } = result.value as {
        checkIn: string;
        checkOut: string;
        fridayRewardType?: 'extra_day' | 'bonus' | 'none';
        fridayBonusAmount?: number;
        fridayBonusReason?: string;
      };
      const [y, m, d] = e.date.split('-').map(Number);
      const [inH, inM] = checkIn.split(':').map(Number);
      const [outH, outM] = checkOut.split(':').map(Number);
      const checkInDate = new Date(y, m - 1, d, inH, inM, 0);
      let checkOutDate = new Date(y, m - 1, d, outH, outM, 0);

      let diffMs = diffMsBetween(checkInDate, checkOutDate);
      if (diffMs <= 0) {
        // انصراف بعد منتصف الليل
        checkOutDate = new Date(checkOutDate.getTime() + 24 * 60 * 60 * 1000);
        diffMs = diffMsBetween(checkInDate, checkOutDate);
      }
      if (diffMs <= 0 || diffMs > 24 * 60 * 60 * 1000) {
        Swal.fire({ icon: 'error', text: 'تأكد من أوقات الحضور والانصراف' });
        return;
      }

      const hours = Math.floor(diffMs / (1000 * 60 * 60));
      const mins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
      const formattedHours = `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;

      const fmt12 = (dt: Date) =>
        `${(dt.getHours() % 12 || 12).toString().padStart(2, '0')}:${dt.getMinutes().toString().padStart(2, '0')} ${dt.getHours() >= 12 ? 'PM' : 'AM'}`;

      const timeInIso = toDatetimeLocalValue(checkInDate) + ':00';
      const timeOutIso = toDatetimeLocalValue(checkOutDate) + ':00';

      const data: Record<string, unknown> = this.sheetActionPayload(e, {
        check_in: fmt12(checkInDate),
        check_out: fmt12(checkOutDate),
        hours: formattedHours,
        time_in: timeInIso,
        time_out: timeOutIso,
        hours_permission: null,
        absence_deduction: null,
        times: JSON.stringify([timeInIso, timeOutIso]),
      });

      if (fridayRewardType) {
        data['friday_reward_type'] = fridayRewardType;
        if (fridayRewardType === 'bonus') {
          data['friday_bonus_amount'] = fridayBonusAmount;
          if (fridayBonusReason) {
            data['friday_bonus_reason'] = fridayBonusReason;
          }
        }
      }

      this.employeeService.registerAttendance({ data }).subscribe({
        next: (res) => {
          if (res) {
            this.getEmpDataPerMonth();
            this.showFingerprintMutationSuccess();
          }
        },
        error: () => {
          Swal.fire({ icon: 'error', text: 'تعذّر حفظ الحضور' });
        },
      });
    });
  }

  onMonthChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.applyMonthValue(target.value);
    this.holidayDaysFn();
    this.getEmpDataPerMonth();
  }

  onDateFromChange(event: Event) {
    const val = (event.target as HTMLInputElement)?.value;
    if (!val) {
      return;
    }
    this.dateFrom = val;
    this.holidayDaysFn();
    this.getEmpDataPerMonth();
  }

  onDateToChange(event: Event) {
    const val = (event.target as HTMLInputElement)?.value;
    if (!val) {
      return;
    }
    this.dateTo = val;
    this.holidayDaysFn();
    this.getEmpDataPerMonth();
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

  permission(e) {
    if (this.isAbsentDay(e)) {
      this.grantAbsenceDayPermission(e);
      return;
    }

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
            this.showFingerprintMutationSuccess();
          }

        })
        return undefined;
      }
    });
  }

  /** إذن يوم غياب كامل = إجازة بإذن بدون خصم */
  absenceDayPermissionPayload(elm: any): Record<string, unknown> {
    const hoursPermission = this.convertMinutesToHours((this.dayHours || 8) * 60);
    return this.sheetActionPayload(elm, {
      hours_permission: hoursPermission,
      absence_deduction: null,
      ...this.permissionSaveExtra(hoursPermission),
    });
  }

  absenceDayRevertPayload(elm: any): Record<string, unknown> {
    return this.sheetActionPayload(elm, absentDayPlaceholderSavePayload());
  }

  private grantAbsenceDayPermission(e: any): void {
    const hoursPermission = this.convertMinutesToHours((this.dayHours || 8) * 60);
    Swal.fire({
      title: 'إذن يوم غياب',
      text: `سيتم احتساب اليوم إجازة بإذن (${hoursPermission}) بدون خصم`,
      showCancelButton: true,
      confirmButtonText: 'حفظ',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.employeeService.empHoursPermision({
        data: this.absenceDayPermissionPayload(e),
      }).subscribe({
        next: (res) => {
          if (res) {
            this.getEmpDataPerMonth();
            this.showFingerprintMutationSuccess();
          }
        },
        error: () => {
          Swal.fire({ icon: 'error', text: 'تعذّر حفظ الإذن' });
        },
      });
    });
  }

  revokeAbsenceDayPermission(e: any): void {
    if (!this.canManageSheet || !this.isFullDayPermissionLeave(e)) {
      return;
    }

    Swal.fire({
      title: 'تراجع عن إجازة بإذن',
      text: 'سيُعاد اليوم غياباً ويُحسب خصم يوم من الراتب',
      showCancelButton: true,
      confirmButtonText: 'تراجع',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#d33',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.employeeService.revertAbsenceDayPermission({
        data: this.absenceDayRevertPayload(e),
      }).subscribe({
        next: (res) => {
          if (res) {
            this.getEmpDataPerMonth();
            this.showFingerprintMutationSuccess();
          }
        },
        error: () => {
          Swal.fire({ icon: 'error', text: 'تعذّر التراجع عن الإذن' });
        },
      });
    });
  }

  doubleAbsenceDay(e: any): void {
    if (!this.canManageSheet || !this.isAbsentDay(e) || this.isAbsenceDoubled(e)) {
      return;
    }

    const amount = absenceDayAmount(this.fixedSalary || 0, ABSENCE_DAY_DOUBLE);
    Swal.fire({
      title: 'مضاعفة يوم الغياب',
      text: `سيتم خصم يومين من الراتب ≈ ${Number(amount).toFixed(2)} ج`,
      showCancelButton: true,
      confirmButtonText: 'ضاعف',
      cancelButtonText: 'إلغاء',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.saveAbsenceDeduction(this.absenceDayDoublePayload(e));
    });
  }

  undoAbsenceDayDouble(e: any): void {
    if (!this.canManageSheet || !this.isAbsentDay(e) || !this.isAbsenceDoubled(e)) {
      return;
    }

    Swal.fire({
      title: 'إلغاء مضاعفة الغياب',
      text: 'سيُحسب اليوم خصم يوم واحد فقط من الراتب',
      showCancelButton: true,
      confirmButtonText: 'إلغاء المضاعفة',
      cancelButtonText: 'رجوع',
      confirmButtonColor: '#d33',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.saveAbsenceDeduction(this.absenceDayUndoDoublePayload(e));
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
        this.saveAbsenceDeduction(this.sheetActionPayload(e, { absence_deduction: value }));
        return null;
      }
    });
  }

  private saveAbsenceDeduction(data: Record<string, unknown>): void {
    this.employeeService.absenceDeduction({ data }).subscribe({
      next: (res) => {
        if (!res) {
          return;
        }
        this.getEmpDataPerMonth();
        this.showFingerprintMutationSuccess();
      },
      error: () => {
        Swal.fire({ icon: 'error', text: 'تعذّر حفظ خصم الغياب' });
      },
    });
  }

  holidayDaysFn(_month?: string) {
    this.holidayDays = fridayDatesInRange(this.dateFrom, this.dateTo);
  }

  filter(e) {
    this.filterDay = e.target.value;
    this.getEmpDataPerMonth();
  }

  isReviewed: boolean = false;
  reviewMonth() {
    this.employeeService.reviewMonth(this.currentMonthValue, this.id, this.dateFrom, this.dateTo).subscribe({
      next: (res) => {
        if (res) {
          this.getEmpDataPerMonth();
          Swal.fire({
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
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
            this.showFingerprintMutationSuccess();
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
            let totalMinutes = (Math.abs(differnceSalary) - value) / (this.hourPrice * OVERTIME_DEDUCTION_MULTIPLIER) * 60;
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
        const time_out = `${(checkOutDate.getHours() % 12 || 12).toString().padStart(2, '0')}:${checkOutDate.getMinutes().toString().padStart(2, '0')} ${checkOutDate.getHours() >= 12 ? 'PM' : 'AM'}`;
        const time_in_iso = toDatetimeLocalValue(checkInDateNew) + ':00';
        const time_out_iso = toDatetimeLocalValue(checkOutDate) + ':00';

        const data = {
          check_in: time_in,
          check_out: time_out,
          hours: formattedDifference,
          time_in: time_in_iso,
          time_out: time_out_iso,
          hours_permission: null,
          times: JSON.stringify([time_in_iso, time_out_iso]),
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
    let checkOutDate = e.time_out ? parseDatetimeLocalValue(String(e.time_out)) : null;
    if (!checkOutDate || (checkInDate && checkOutDate.getTime() <= checkInDate.getTime())) {
      checkOutDate = resolveCheckOutDate(e.date, e.check_in, e.check_out);
    }

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
        const checkInFmt = `${(checkInDate.getHours() % 12 || 12).toString().padStart(2, '0')}:${checkInDate.getMinutes().toString().padStart(2, '0')} ${checkInDate.getHours() >= 12 ? 'PM' : 'AM'}`;
        const time_in_iso = toDatetimeLocalValue(checkInDate) + ':00';
        const time_out_iso = toDatetimeLocalValue(checkOutDateFinal) + ':00';

        const data = {
          check_in: checkInFmt,
          check_out: checkOut,
          hours: formattedDifference,
          time_in: time_in_iso,
          time_out: time_out_iso,
          hours_permission: null,
          times: JSON.stringify([time_in_iso, time_out_iso]),
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
