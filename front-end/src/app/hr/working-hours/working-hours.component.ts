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
export class WorkingHoursComponent implements OnInit {

  sheetData: any[] = [];
  data: any[] = [];
  selectedFile: any;
  employees: any[] = [];
  days: any[] = [];
  monthDays: any[] = [];

  btnShowForm = false;

  currentMonthValue!: string;
  previousMonth!: Date;
  month!: number;
  year!: number;

  selectVacationBoolean = false;
  isEmpSelected = false;
  reason!: string;

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
    const today = new Date();
    // Get previous month as default
    const previousMonth = new Date(today.getFullYear(), today.getMonth() - 1);
    this.year = previousMonth.getFullYear();
    this.month = previousMonth.getMonth() + 1; // getMonth() returns 0-11, so add 1
    this.currentMonthValue = `${this.year}-${String(this.month).padStart(2, '0')}`;
    this.previousMonth = previousMonth;
  }

  ngOnInit(): void {
    this.getEmpDataPerMonth();
  }

  /* ======================================================
     EMPLOYEE MONTH DATA
  ====================================================== */
  getEmpDataPerMonth() {
    this.employeeService.getEmpsDataPerMonth({ month: this.currentMonthValue }).subscribe(res => {
      res.forEach(emp => {
        emp.selected = false;
        let hourPerDay = emp.working_hours == 9 ? 9 : 8;
        let totalMinutes = 26 * hourPerDay * 60;
        let actualMinutes = 0;

        emp.totalHours = this.convertMinutesToHours(totalMinutes);

        emp.finger_print?.forEach(fp => {
          if (fp.hours) {
            const [h, m] = fp.hours.split(':').map(Number);
            actualMinutes += h * 60 + m;
          }
          if (fp.hours_permission) {
            const [h, m] = fp.hours_permission.split(':').map(Number);
            actualMinutes += h * 60 + m;
          }
        });

        emp.actualTotalHours = this.convertMinutesToHours(actualMinutes);
        const diff = actualMinutes - totalMinutes;
        emp.hoursDifference = diff >= 0
          ? this.convertMinutesToHours(diff)
          : '-' + this.convertMinutesToHours(Math.abs(diff));
      });

      this.employees = res;
    });
  }

  convertMinutesToHours(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
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

      this.sheetData = rows.map(row => {
        const dateObj = this.parseExcelDate(row.Time);

        const iso = `${dateObj.getFullYear()}-${String(dateObj.getMonth() + 1).padStart(2, '0')}-${String(dateObj.getDate()).padStart(2, '0')}T${String(dateObj.getHours()).padStart(2, '0')}:${String(dateObj.getMinutes()).padStart(2, '0')}:00.000Z`;

        return {
          acc_no: row['AC-No.'],
          state: row['State'],
          date: iso.split('T')[0],
          hour: dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true }),
          iso_date: iso
        };
      });

      this.days = [...new Set(this.sheetData.map(d => d.date))].sort();

      const [y, m] = this.days[0].split('-').map(Number);
      const daysInMonth = new Date(y, m, 0).getDate();
      this.monthDays = [];
      for (let d = 1; d <= daysInMonth; d++) {
        this.monthDays.push(`${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`);
      }
    };

    reader.readAsArrayBuffer(this.selectedFile);
  }

  /* ======================================================
     EXCEL TIME PARSER (Õ = AM , ã = PM)
  ====================================================== */
  parseExcelDate(value: string): Date {
    let isAM = false;
    let isPM = false;

    if (value.includes('Õ')) isAM = true;
    if (value.includes('ã')) isPM = true;

    value = value.replace(/[^\d/:\s]/g, '').trim();
    const [datePart, timePart] = value.split(' ');

    const [d, m, y] = datePart.split('/').map(Number);
    let [h, min] = timePart.split(':').map(Number);

    if (isPM && h < 12) h += 12;
    if (isAM && h === 12) h = 0;

    return new Date(y, m - 1, d, h, min, 0);
  }

  /* ======================================================
     SUBMIT (UNCHANGED LOGIC – SAFE)
  ====================================================== */
  async submitform() {
    this.data = [];
    this.sheetData = this.sheetData.filter(r => this.monthDays.includes(r.date));

    this.days.forEach(day => {
      this.employees.forEach(emp => {
        const punches = this.sheetData
          .filter(p => p.acc_no == emp.acc_no && p.date == day)
          .sort((a, b) => new Date(a.iso_date).getTime() - new Date(b.iso_date).getTime());

        if (!punches.length) return;

        const first = punches[0];
        const last = punches[punches.length - 1];

        const diffMin = (new Date(last.iso_date).getTime() - new Date(first.iso_date).getTime()) / 60000;

        this.data.push({
          acc_no: emp.acc_no,
          employee_id: emp.id,
          date: day,
          check_in: first.hour,
          time_in: first.iso_date,
          check_out: last.hour,
          time_out: last.iso_date,
          hours: this.convertMinutesToHours(Math.max(diffMin, 0)),
          iso_date: first.iso_date,
          times: JSON.stringify(punches.map(p => p.iso_date))
        });
      });
    });

    if (!this.data.length) return;

    this.employeeService.saveExcelData({ data: this.data }, '').subscribe(() => {
      Swal.fire({ icon: 'success', timer: 1500, showConfirmButton: false });
      this.getEmpDataPerMonth();
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
    this.currentMonthValue = val;
    this.getEmpDataPerMonth();
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
