import { Component, OnDestroy, OnInit, Renderer2 } from '@angular/core';
import { FormGroup, FormControl } from '@angular/forms';
import { Subscription } from 'rxjs';
import * as XLSX from 'xlsx';
import { EmployeeService } from '../services/employee.service';
import Swal from 'sweetalert2';
import { Router } from '@angular/router';
import { DatePipe } from '@angular/common';
import {
  convertMinutesToHours,
  defaultFingerprintMonthValue,
  fingerprintDayFromPunches,
  fingerprintPeriodForMonth,
  nextDateStr,
  normalizeOvernightFingerPrintRecords,
  OVERNIGHT_CHECKOUT_CUTOFF_HOUR,
  parseYearMonthValue,
  punchHour24,
  sumFingerprintWorkedMinutes
} from '../utils/fingerprint-hours.utils';

@Component({
  selector: 'app-working-hours',
  templateUrl: './working-hours.component.html',
  styleUrls: ['./working-hours.component.css'],
  providers: [DatePipe]
})
export class WorkingHoursComponent implements OnInit, OnDestroy {

  sheetData: any[] = [];
  data: any[] = [];
  selectedFile: any;
  employees: any[] = [];
  days: any[] = [];
  btnShowForm = false;

  currentMonthValue!: string;
  dateFrom!: string;
  dateTo!: string;
  previousMonth!: Date;
  month!: number;
  year!: number;

  selectVacationBoolean = false;
  isEmpSelected = false;
  reason!: string;
  listLoading = false;
  listError = '';
  private listRequest?: Subscription;

  range = new FormGroup({
    start: new FormControl<Date | null>(null),
    end: new FormControl<Date | null>(null),
  });

  constructor(
    private employeeService: EmployeeService,
    private route: Router,
    private datePipe: DatePipe,
    private renderer: Renderer2
  ) {
    this.applyMonthValue(defaultFingerprintMonthValue());
    const [y, m] = this.currentMonthValue.split('-').map(Number);
    this.previousMonth = new Date(y, m - 1);
  }

  ngOnInit(): void {
    this.getEmpDataPerMonth();
  }

  ngOnDestroy(): void {
    this.listRequest?.unsubscribe();
  }

  trackByEmployeeId(_index: number, elm: any): number | string {
    return elm?.id ?? elm?.acc_no ?? _index;
  }

  /* ======================================================
     EMPLOYEE MONTH DATA
  ====================================================== */
  getEmpDataPerMonth() {
    this.listLoading = true;
    this.listError = '';
    this.listRequest?.unsubscribe();
    this.listRequest = this.employeeService.getEmpsDataPerMonth({
      month: this.currentMonthValue,
      year: this.year,
      date_from: this.dateFrom,
      date_to: this.dateTo,
    }).subscribe({
      next: res => {
        const rows = this.asEmployeeRows(res);
        rows.forEach(emp => {
          try {
            emp.selected = false;
            let hourPerDay = emp.working_hours == 9 ? 9 : 8;
            let totalMinutes = 26 * hourPerDay * 60;
            let actualMinutes = 0;

            emp.totalHours = convertMinutesToHours(totalMinutes);

            const prints = Array.isArray(emp.finger_print)
              ? emp.finger_print
              : (Array.isArray(emp.fingerPrint) ? emp.fingerPrint : []);
            emp.finger_print = prints;
            if (prints.length) {
              emp.finger_print = normalizeOvernightFingerPrintRecords(prints);
            }

            actualMinutes = sumFingerprintWorkedMinutes(emp.finger_print, hourPerDay);

            emp.actualTotalHours = convertMinutesToHours(actualMinutes);
            const diff = actualMinutes - totalMinutes;
            emp.hoursDifference = diff >= 0
              ? convertMinutesToHours(diff)
              : '-' + convertMinutesToHours(Math.abs(diff));
          } catch (err) {
            console.error('تعذّر معالجة بيانات الموظف', emp?.acc_no, err);
            emp.totalHours = emp.totalHours || '00:00';
            emp.actualTotalHours = emp.actualTotalHours || '00:00';
            emp.hoursDifference = emp.hoursDifference || '00:00';
          }
        });
        this.employees = rows;
        this.listLoading = false;
      },
      error: err => {
        console.error('تعذّر تحميل بيانات الموظفين', err);
        this.listLoading = false;
        this.listError = err?.error?.message || 'تعذّر تحميل قائمة الموظفين';
        this.employees = [];
        Swal.fire({
          icon: 'error',
          title: 'تعذّر تحميل قائمة الموظفين',
          text: 'تحقق من الاتصال بالخادم ثم أعد تحميل الصفحة.'
        });
      }
    });
  }

  private asEmployeeRows(res: any): any[] {
    if (Array.isArray(res)) {
      return res;
    }
    if (Array.isArray(res?.data)) {
      return res.data;
    }
    if (res && typeof res === 'object') {
      const values = Object.values(res).filter((item: any) => item && typeof item === 'object' && (item.id || item.acc_no || item.name));
      if (values.length) {
        return values as any[];
      }
    }
    return [];
  }

  private punchKey(punch: { acc_no: string | number; iso_date: string }): string {
    return `${String(punch.acc_no).trim()}|${punch.iso_date}`;
  }

  private punchTimestamp(isoDate: string): number {
    return new Date(isoDate).getTime();
  }

  private nextDateStr(dateStr: string): string {
    const [y, m, d] = dateStr.split('-').map(Number);
    const next = new Date(y, m - 1, d + 1);
    return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`;
  }

  private collectDayPunches(
    accNo: string | number,
    day: string,
    sheetRows: { acc_no: string | number; date: string; iso_date: string; hour: string }[],
    consumedNextDayPunchKeys: Set<string>
  ): typeof sheetRows {
    const acc = String(accNo).trim();
    const punches = sheetRows
      .filter(p => String(p.acc_no).trim() === acc && p.date === day)
      .filter(p => !consumedNextDayPunchKeys.has(this.punchKey(p)))
      .sort((a, b) => this.punchTimestamp(a.iso_date) - this.punchTimestamp(b.iso_date));

    if (!punches.length) return [];

    const nextDay = this.nextDateStr(day);
    const nextDayCandidates = sheetRows
      .filter(p =>
        String(p.acc_no).trim() === acc
        && p.date === nextDay
        && !consumedNextDayPunchKeys.has(this.punchKey(p))
      )
      .sort((a, b) => this.punchTimestamp(a.iso_date) - this.punchTimestamp(b.iso_date));

    // بصمات بعد منتصف الليل (00:00–07:59) = انصراف لدوام الليلة السابقة
    const earlyNextDay: typeof sheetRows = [];
    for (const p of nextDayCandidates) {
      if (punchHour24(p.iso_date) >= OVERNIGHT_CHECKOUT_CUTOFF_HOUR) {
        break;
      }
      earlyNextDay.push(p);
    }

    earlyNextDay.forEach(p => consumedNextDayPunchKeys.add(this.punchKey(p)));
    return [...punches, ...earlyNextDay];
  }

  /* ======================================================
     FILE UPLOAD
  ====================================================== */
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.readExcel();
  }

  /* ======================================================
     READ EXCEL (FIXED Õ / ã)
  ====================================================== */
  readExcel() {
    this.sheetData = [];
    this.days = [];
    this.data = [];

    if (!this.selectedFile) return;

    const reader = new FileReader();
    reader.onload = (e: any) => {
      const workbook = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
      const sheet = workbook.Sheets[workbook.SheetNames[0]];
      const rows: any[] = XLSX.utils.sheet_to_json(sheet, { raw: false });

      if (!rows.length || !rows[0]['AC-No.'] || !rows[0]['Time']) {
        Swal.fire({ icon: 'error', title: 'ملف غير صحيح' });
        return;
      }

      const parsedRows = rows
        .map(row => {
          const dateObj = this.parseExcelDate(row.Time);
          if (!dateObj) return null;

          const iso = `${dateObj.getFullYear()}-${String(dateObj.getMonth() + 1).padStart(2, '0')}-${String(dateObj.getDate()).padStart(2, '0')}T${String(dateObj.getHours()).padStart(2, '0')}:${String(dateObj.getMinutes()).padStart(2, '0')}:${String(dateObj.getSeconds()).padStart(2, '0')}`;

          return {
            acc_no: row['AC-No.'],
            state: row['State'],
            date: iso.split('T')[0],
            hour: dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true }),
            iso_date: iso
          };
        })
        .filter((row): row is NonNullable<typeof row> => row !== null);

      if (!parsedRows.length) {
        Swal.fire({
          icon: 'error',
          title: 'تعذّر قراءة التواريخ',
          text: 'تحقق من عمود Time في الملف (مثال: 5/14/2026 9:00).'
        });
        return;
      }

      this.sheetData = parsedRows;
      this.days = [...new Set(this.sheetData.map(d => d.date))].sort();

      Swal.fire({
        icon: 'success',
        title: 'تم قراءة الملف',
        html: `عدد السجلات: <b>${this.sheetData.length}</b><br>عدد الأيام: <b>${this.days.length}</b><br>تأكد أن الشهر المختار أعلاه يطابق تواريخ الملف (<b>${this.currentMonthValue}</b>).`,
        timer: 4000,
        showConfirmButton: true
      });
    };

    reader.readAsArrayBuffer(this.selectedFile);
  }

  /* ======================================================
     EXCEL TIME PARSER
     ZKTeco exports M/D/YYYY (e.g. 5/14/2026 9) — not D/M/Y.
     Õ / ã = corrupted AM/PM markers from Arabic locale exports.
  ====================================================== */
  parseExcelDate(value: string | number): Date | null {
    if (value == null || value === '') return null;

    if (typeof value === 'number' && Number.isFinite(value)) {
      const excelEpoch = new Date(Date.UTC(1899, 11, 30));
      const wholeDays = Math.floor(value);
      const dayFraction = value - wholeDays;
      const date = new Date(excelEpoch.getTime() + wholeDays * 86400000);
      if (dayFraction > 0) {
        const totalMinutes = Math.round(dayFraction * 24 * 60);
        date.setHours(Math.floor(totalMinutes / 60), totalMinutes % 60, 0, 0);
      }
      return Number.isNaN(date.getTime()) ? null : date;
    }

    const raw = String(value).trim();
    if (!raw) return null;

    let isAM = false;
    let isPM = false;
    const lower = raw.toLowerCase();
    if (raw.includes('Õ') || lower.includes('am') || raw.includes('ص')) isAM = true;
    if (raw.includes('ã') || lower.includes('pm') || raw.includes('م')) isPM = true;

    const cleaned = raw.replace(/[^\d/:\s]/g, '').trim();
    const [datePart, timePart = '0'] = cleaned.split(/\s+/);
    const dateBits = datePart.split('/').map(Number);
    if (dateBits.length !== 3 || dateBits.some(n => Number.isNaN(n))) return null;

    const [a, b, y] = dateBits;
    let month: number;
    let day: number;
    if (a > 12) {
      day = a;
      month = b;
    } else if (b > 12) {
      month = a;
      day = b;
    } else {
      // Ambiguous (e.g. 5/5/2026) — fingerprint machines use M/D/Y.
      month = a;
      day = b;
    }

    let h = 0;
    let min = 0;
    let sec = 0;
    if (timePart.includes(':')) {
      const [hourRaw, minRaw, secRaw] = timePart.split(':');
      h = Number(hourRaw);
      min = Number(minRaw ?? 0);
      sec = Number(secRaw ?? 0);
    } else {
      h = Number(timePart);
    }
    if (Number.isNaN(h)) h = 0;
    if (Number.isNaN(min)) min = 0;
    if (Number.isNaN(sec)) sec = 0;

    if (isPM && h < 12) h += 12;
    if (isAM && h === 12) h = 0;

    const date = new Date(y, month - 1, day, h, min, sec);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  /* ======================================================
     SUBMIT — شهر الواجهة + رسائل للأخطاء الصامتة سابقاً
  ====================================================== */
  submitform() {
    if (!this.selectedFile) {
      Swal.fire({
        icon: 'warning',
        title: 'لم يتم اختيار ملف',
        text: 'اختر ملف البصمة ثم انتظر انتهاء قراءته قبل الضغط على حفظ.'
      });
      return;
    }
    if (!this.sheetData?.length) {
      Swal.fire({
        icon: 'warning',
        title: 'لا توجد بيانات في الشيت',
        text: 'تأكد من الملف (أعمدة AC-No. و Time) أو جرّب اختيار الملف مرة أخرى.'
      });
      return;
    }
    if (!this.employees?.length) {
      Swal.fire({
        icon: 'warning',
        title: 'لم تُحمَّل قائمة الموظفين',
        text: 'أعد تحميل الصفحة حتى تظهر أسماء الموظفين في الجدول ثم جرّب الحفظ مرة أخرى.'
      });
      return;
    }

    const allowedDates = new Set<string>();
    const from = new Date(this.dateFrom + 'T00:00:00');
    const to = new Date(this.dateTo + 'T00:00:00');
    for (let d = new Date(from); d.getTime() <= to.getTime(); d.setDate(d.getDate() + 1)) {
      allowedDates.add(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`);
    }

    const nextMonthFirstDay = nextDateStr(this.dateTo);
    const sheetRows = this.sheetData.filter(r => allowedDates.has(r.date) || r.date === nextMonthFirstDay);
    const daysToProcess = [...new Set(sheetRows.filter(r => allowedDates.has(r.date)).map(row => row.date))].sort();

    if (!daysToProcess.length) {
      Swal.fire({
        icon: 'warning',
        title: 'الشهر المختار لا يطابق تواريخ الشيت',
        html: `فترة التقفيل الحالية: <b>${this.dateFrom}</b> إلى <b>${this.dateTo}</b> (شهر ${this.currentMonthValue} — التقفيل يوم 25). غيّر الشهر أو تاريخ من/إلى ليطابق الملف.`
      });
      return;
    }

    this.data = [];
    const consumedNextDayPunchKeys = new Set<string>();

    daysToProcess.forEach(day => {
      this.employees.forEach(emp => {
        const punches = this.collectDayPunches(emp.acc_no, day, sheetRows, consumedNextDayPunchKeys);

        if (!punches.length) return;

        const dayRecord = fingerprintDayFromPunches(punches.map(p => p.iso_date));
        if (!dayRecord) return;

        this.data.push({
          acc_no: emp.acc_no,
          employee_id: emp.id,
          date: day,
          check_in: dayRecord.check_in,
          time_in: dayRecord.time_in,
          check_out: dayRecord.check_out,
          time_out: dayRecord.time_out,
          hours: dayRecord.hours,
          iso_date: dayRecord.time_in,
          times: JSON.stringify(dayRecord.times)
        });
      });
    });

    if (!this.data.length) {
      Swal.fire({
        icon: 'info',
        title: 'لا توجد سجلات للحفظ',
        text: 'لم يُطابق أي سطر بين الملف وجدول الموظفين لهذا الشهر. راجع أرقام البصمة (AC-No.) وأنك تعرض شهر الموظفين نفسه في الجدول.'
      });
      return;
    }

    this.confirmFingerprintImportThenSave();
  }

  private confirmFingerprintImportThenSave(): void {
    const byEmployee = new Map<string, { name: string; acc_no: string; days: number; minutes: number }>();
    for (const row of this.data) {
      const key = String(row.employee_id);
      const current = byEmployee.get(key) || {
        name: this.employees.find(e => String(e.id) === key)?.name || row.acc_no,
        acc_no: String(row.acc_no),
        days: 0,
        minutes: 0,
      };
      current.days += 1;
      const [h, m] = String(row.hours || '00:00').split(':').map(Number);
      current.minutes += (h || 0) * 60 + (m || 0);
      byEmployee.set(key, current);
    }

    const matchedAcc = new Set(this.data.map(r => String(r.acc_no).trim()));
    const unmatched = [...new Set(this.sheetData.map(r => String(r.acc_no).trim()))]
      .filter(acc => acc && !this.employees.some(e => String(e.acc_no).trim() === acc) && !matchedAcc.has(acc));

    const rowsHtml = [...byEmployee.values()]
      .sort((a, b) => Number(a.acc_no) - Number(b.acc_no))
      .map(row => `<tr>
        <td>${row.acc_no}</td>
        <td>${row.name}</td>
        <td>${row.days}</td>
        <td dir="ltr">${convertMinutesToHours(row.minutes)}</td>
      </tr>`)
      .join('');

    const unmatchedHtml = unmatched.length
      ? `<p class="text-danger mt-2">أرقام بصمة في الملف غير مربوطة بموظف: <b>${unmatched.join(', ')}</b></p>`
      : '';

    Swal.fire({
      title: 'تأكيد أرقام البصمة قبل الحفظ',
      html: `
        <p>الساعات = آخر بصمة − أول بصمة لكل يوم، كما في شيت الجهاز.</p>
        <p>الموظفون: <b>${byEmployee.size}</b> — الأيام: <b>${this.data.length}</b> — الفترة: <b>${this.dateFrom}</b> إلى <b>${this.dateTo}</b></p>
        <div style="max-height:280px;overflow:auto">
          <table class="table table-sm table-bordered text-center mb-0">
            <thead><tr><th>رقم البصمة</th><th>الاسم</th><th>أيام</th><th>ساعات البصمة</th></tr></thead>
            <tbody>${rowsHtml}</tbody>
          </table>
        </div>
        ${unmatchedHtml}
      `,
      width: 720,
      showCancelButton: true,
      confirmButtonText: 'حفظ هذه الأرقام',
      cancelButtonText: 'إلغاء',
    }).then(result => {
      if (!result.isConfirmed) {
        return;
      }
      this.saveFingerprintImport();
    });
  }

  private saveFingerprintImport(): void {
    Swal.fire({
      title: 'جاري الحفظ…',
      didOpen: () => { Swal.showLoading(); },
      allowOutsideClick: false
    });

    this.employeeService.saveExcelData({
      data: this.data,
      month: this.month,
      year: this.year,
      date_from: this.dateFrom,
      date_to: this.dateTo,
    }, '').subscribe({
      next: () => {
        Swal.close();
        Swal.fire({ icon: 'success', timer: 1500, showConfirmButton: false });
        this.getEmpDataPerMonth();
      },
      error: (err) => {
        Swal.close();
        const msg =
          err?.error?.message ||
          (typeof err?.error === 'string' ? err.error : null) ||
          err?.message ||
          'تعذّر الاتصال بالخادم أو رفض الطلب.';
        Swal.fire({ icon: 'error', title: 'فشل الحفظ', text: msg });
      }
    });
  }

  /* ======================================================
     UI HELPERS
  ====================================================== */
  openForm() { this.btnShowForm = true; }
  closeForm() { this.btnShowForm = false; }

  employeeDetails(id: number) {
    this.route.navigate([`/dashboard/hr/workinghoursdetails/${id}`]);
  }

  onMonthChange(event: any) {
    const val = event?.target?.value;
    if (!val) return;
    this.applyMonthValue(val);
    this.getEmpDataPerMonth();
  }

  onDateFromChange(event: Event) {
    const val = (event.target as HTMLInputElement)?.value;
    if (!val) return;
    this.dateFrom = val;
    this.getEmpDataPerMonth();
  }

  onDateToChange(event: Event) {
    const val = (event.target as HTMLInputElement)?.value;
    if (!val) return;
    this.dateTo = val;
    this.getEmpDataPerMonth();
  }

  private applyMonthValue(value: string) {
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
    this.previousMonth = new Date(parsed.year, parsed.month - 1);
  }

  selectvacationDays() {
    this.selectVacationBoolean = true;
    this.employees.forEach(e => e.selected = false);
    this.isEmpSelected = false;
    this.range.reset();
    this.reason = '';
  }

  cancelVac() {
    this.selectVacationBoolean = false;
    this.employees.forEach(e => e.selected = false);
    this.isEmpSelected = false;
    this.range.reset();
    this.reason = '';
  }

  vacationFn() {
    if (!this.isEmpSelected || !this.range.value.start || !this.range.value.end) return;
    const selected = this.employees.filter(e => e.selected).map(e => e.id || e.acc_no);
    const payload = {
      employees: selected,
      from: this.datePipe.transform(this.range.value.start, 'yyyy-MM-dd'),
      to: this.datePipe.transform(this.range.value.end, 'yyyy-MM-dd'),
      reason: this.reason || null
    };

    Swal.fire({ title: 'Processing...', didOpen: () => { Swal.showLoading(); } });

    // Attempt to call service if available; fallback to UI-only behavior
    if (this.employeeService && (this.employeeService as any).applyVacation) {
      (this.employeeService as any).applyVacation(payload).subscribe(() => {
        Swal.close();
        Swal.fire({ icon: 'success', text: 'تم حفظ الاجازات' });
        this.cancelVac();
        this.getEmpDataPerMonth();
      }, () => {
        Swal.close();
        Swal.fire({ icon: 'error', text: 'فشل حفظ الاجازات' });
      });
    } else {
      Swal.close();
      Swal.fire({ icon: 'success', text: 'تم تحديد الاجازات (محلي)' });
      this.cancelVac();
    }
  }

  selectEmp(event: any) {
    const id = event?.target?.id;
    const checked = !!event?.target?.checked;

    if (id === 'selectAll') {
      this.employees.forEach(e => e.selected = checked);
    } else {
      const idx = Number(id);
      if (!isNaN(idx) && this.employees[idx]) {
        this.employees[idx].selected = checked;
      } else {
        const emp = this.employees.find((el: any) => String(el.id) === String(id) || String(el.acc_no) === String(id));
        if (emp) emp.selected = checked;
      }
    }

    this.isEmpSelected = this.employees.some(e => e.selected);
  }
}
