import { Component, Inject } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { forkJoin, of } from 'rxjs';
import Swal from 'sweetalert2';
import { EmployeeService } from '../services/employee.service';
import {
  OvertimeMeritKind,
  EXTRA_DAY_WORKING_DAYS,
  OVERTIME_DEDUCTION_MULTIPLIER,
  WORKING_DAYS_PER_MONTH,
  extraDayMeritAmountAction,
  overtimeDeductionRate,
  overtimeMeritRowAmount,
  overtimeMeritRowReason,
  deductionRowReason,
  payrollMonthForDate,
} from '../utils/fingerprint-hours.utils';

export type OvertimeDialogMode = 'merit' | 'deduction';

export interface OvertimeMeritDialogData {
  employeeId: number;
  employeeName: string;
  month: number;
  year: number;
  fixedSalary: number;
  dayHours: number;
  mode?: OvertimeDialogMode;
  dateFrom?: string;
  dateTo?: string;
}

interface OvertimeRowView {
  date: string;
  kind: OvertimeMeritKind;
  hours: number | null;
  amount: number | null;
}

@Component({
  selector: 'app-overtime-merit-dialog',
  templateUrl: './overtime-merit-dialog.component.html',
  styleUrls: ['./overtime-merit-dialog.component.css'],
})
export class OvertimeMeritDialogComponent {
  rows: OvertimeRowView[] = [];
  saving = false;

  get isDeduction(): boolean {
    return this.data.mode === 'deduction';
  }

  constructor(
    private dialogRef: MatDialogRef<OvertimeMeritDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: OvertimeMeritDialogData,
    private employeeService: EmployeeService,
  ) {
    this.addRow();
  }

  get dayAmount(): number {
    return extraDayMeritAmountAction(this.data.fixedSalary, this.data.dayHours || 8);
  }

  get overtimeHourRate(): number {
    return overtimeDeductionRate(this.data.fixedSalary, this.data.dayHours || 8);
  }

  get formulaHint(): string {
    const dh = this.data.dayHours || 8;
    const base = `يوم كامل = (راتب ÷ ${EXTRA_DAY_WORKING_DAYS} ÷ ${dh}) × ${OVERTIME_DEDUCTION_MULTIPLIER} × ${dh} | ساعة = (راتب ÷ ${WORKING_DAYS_PER_MONTH * dh}) × ${OVERTIME_DEDUCTION_MULTIPLIER}`;
    return this.isDeduction ? `${base} | مبلغ معيّن يُحفظ كما هو` : base;
  }

  rowAmount(row: OvertimeRowView): number {
    return overtimeMeritRowAmount(this.data.fixedSalary, this.data.dayHours || 8, {
      date: row.date,
      kind: row.kind,
      hours: row.hours,
      amount: row.amount,
    });
  }

  get totalAmount(): number {
    return this.rows.reduce((sum, row) => sum + this.rowAmount(row), 0);
  }

  defaultDate(): string {
    if (this.data.dateTo) {
      return this.data.dateTo;
    }
    const m = String(this.data.month).padStart(2, '0');
    return `${this.data.year}-${m}-01`;
  }

  addRow(): void {
    this.rows.push({
      date: this.defaultDate(),
      kind: 'hours',
      hours: 1,
      amount: null,
    });
  }

  removeRow(index: number): void {
    if (this.rows.length <= 1) {
      return;
    }
    this.rows.splice(index, 1);
  }

  onKindChange(row: OvertimeRowView): void {
    if (row.kind === 'day') {
      row.hours = null;
      row.amount = null;
    } else if (row.kind === 'hours') {
      row.amount = null;
      if (!row.hours || row.hours <= 0) {
        row.hours = 1;
      }
    } else {
      row.hours = null;
      if (!row.amount || row.amount <= 0) {
        row.amount = null;
      }
    }
  }

  payrollMonthForRow(date: string): { year: number; month: number } {
    const fromDate = payrollMonthForDate(date);
    if (fromDate?.year && fromDate?.month) {
      return fromDate;
    }
    return { year: Number(this.data.year), month: Number(this.data.month) };
  }

  private validate(): string | null {
    if (!this.rows.length) {
      return 'أضف سطرًا واحدًا على الأقل';
    }
    for (let i = 0; i < this.rows.length; i++) {
      const row = this.rows[i];
      if (!row.date) {
        return `السطر ${i + 1}: تاريخ غير صالح`;
      }
      if (this.data.dateFrom && row.date < this.data.dateFrom) {
        return `السطر ${i + 1}: التاريخ خارج فترة التقفيل`;
      }
      if (this.data.dateTo && row.date > this.data.dateTo) {
        return `السطر ${i + 1}: التاريخ خارج فترة التقفيل`;
      }
      if (row.kind === 'hours') {
        const hours = Number(row.hours);
        if (!hours || hours <= 0) {
          return `السطر ${i + 1}: أدخل عدد الساعات التي تريدها`;
        }
      }
      if (row.kind === 'amount') {
        const amount = Number(row.amount);
        if (!amount || amount <= 0) {
          return `السطر ${i + 1}: أدخل المبلغ المراد خصمه`;
        }
      }
      if (this.rowAmount(row) <= 0) {
        return `السطر ${i + 1}: المبلغ غير صالح`;
      }
    }
    return null;
  }

  save(): void {
    const error = this.validate();
    if (error) {
      Swal.fire({ icon: 'warning', title: error, timer: 2200, showConfirmButton: false });
      return;
    }

    this.saving = true;
    const requests = this.rows.map((row) => {
      const ym = this.payrollMonthForRow(row.date);
      const amount = Number(this.rowAmount(row).toFixed(2));
      const payload = {
        employee_id: this.data.employeeId,
        month: ym.month,
        year: ym.year,
        amount,
        reason: this.isDeduction
          ? deductionRowReason({ date: row.date, kind: row.kind, hours: row.hours, amount: row.amount })
          : overtimeMeritRowReason({ date: row.date, kind: row.kind, hours: row.hours }),
      };
      return this.isDeduction
        ? this.employeeService.addSubtraction({ ...payload, type: 'خصومات' })
        : this.employeeService.addMerit({ ...payload, type: 'حوافز' });
    });

    forkJoin(requests.length ? requests : [of(null)]).subscribe({
      next: () => {
        this.saving = false;
        Swal.fire({
          icon: 'success',
          title: this.isDeduction ? 'تم حفظ الخصم' : 'تم حفظ الإضافي',
          timer: 1500,
          showConfirmButton: false,
        });
        this.dialogRef.close({ saved: true, count: this.rows.length, total: this.totalAmount, mode: this.data.mode || 'merit' });
      },
      error: (err) => {
        this.saving = false;
        Swal.fire({
          icon: 'error',
          title: 'خطأ',
          text: err?.error?.message || 'تعذر حفظ أحد السطور',
        });
      },
    });
  }

  cancel(): void {
    this.dialogRef.close();
  }
}
