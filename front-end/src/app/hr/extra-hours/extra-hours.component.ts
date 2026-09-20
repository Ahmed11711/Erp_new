import { Component, OnInit } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { AuthService } from 'src/app/auth/auth.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { RBAC_ROUTE } from 'src/app/guards/rbac-route-data';
import { OvertimeMeritDialogComponent } from '../overtime-merit-dialog/overtime-merit-dialog.component';
import { EmployeeService } from '../services/employee.service';
import { extraDayMeritAmountAction, defaultFingerprintMonthValue, fingerprintPeriodForMonth, overtimeDeductionRate, parseYearMonthValue } from '../utils/fingerprint-hours.utils';

@Component({
  selector: 'app-extra-hours',
  templateUrl: './extra-hours.component.html',
  styleUrls: ['./extra-hours.component.css']
})
export class ExtraHoursComponent implements OnInit {
  employees: any[] = [];
  catword = 'name';
  currentMonthValue!: string;
  dateFrom!: string;
  dateTo!: string;
  month!: number;
  year!: number;

  id = 0;
  name!: string;
  fixed_salary = 0;
  dayHours = 8;
  incentivesTotal = 0;

  user!: string;

  get canManage(): boolean {
    if (this.rbac.canAny(RBAC_ROUTE.hrManageSheet)) {
      return true;
    }
    return this.user == 'Admin'
      || this.user == 'Operation Management'
      || this.user == 'Finance and operations management'
      || this.user == 'Financial Accounts';
  }

  get hourPrice(): number {
    return overtimeDeductionRate(this.fixed_salary || 0, this.dayHours || 8);
  }

  get dayAmount(): number {
    return extraDayMeritAmountAction(this.fixed_salary || 0, this.dayHours || 8);
  }

  constructor(
    private empService: EmployeeService,
    private dialog: MatDialog,
    private authService: AuthService,
    private rbac: RbacService,
  ) {
    this.applyMonthValue(defaultFingerprintMonthValue());
  }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    this.empService.data().subscribe(result => this.employees = result);
  }

  empChange(e: any): void {
    this.id = e.id;
    this.name = e.name;
    this.fixed_salary = Number(e.fixed_salary || 0);
    this.dayHours = Number(e.working_hours || 8);
    this.loadIncentives();
  }

  resetData(): void {
    this.id = 0;
    this.name = '';
    this.fixed_salary = 0;
    this.incentivesTotal = 0;
  }

  onMonthChange(event: Event): void {
    const val = (event.target as HTMLInputElement)?.value;
    if (!val) {
      return;
    }
    this.applyMonthValue(val);
    if (this.id) {
      this.loadIncentives();
    }
  }

  private applyMonthValue(value: string): void {
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

  private loadIncentives(): void {
    this.empService.dataPerMonth(this.id, this.month, this.year).subscribe({
      next: (res: any) => {
        const merits = res?.merits || [];
        this.incentivesTotal = merits
          .filter((m: any) => m.type === 'حوافز')
          .reduce((acc: number, m: any) => acc + Number(m.amount || 0), 0);
      },
      error: () => {
        this.incentivesTotal = 0;
      },
    });
  }

  openOvertimeDialog(): void {
    if (!this.id || !this.canManage) {
      return;
    }
    const ref = this.dialog.open(OvertimeMeritDialogComponent, {
      width: '780px',
      maxWidth: '95vw',
      data: {
        employeeId: this.id,
        employeeName: this.name || '',
        month: this.month,
        year: this.year,
        fixedSalary: this.fixed_salary,
        dayHours: this.dayHours,
        mode: 'merit',
        dateFrom: this.dateFrom,
        dateTo: this.dateTo,
      },
    });
    ref.afterClosed().subscribe((result) => {
      if (result?.saved) {
        this.loadIncentives();
      }
    });
  }

  openDeductionDialog(): void {
    if (!this.id || !this.canManage) {
      return;
    }
    const ref = this.dialog.open(OvertimeMeritDialogComponent, {
      width: '780px',
      maxWidth: '95vw',
      data: {
        employeeId: this.id,
        employeeName: this.name || '',
        month: this.month,
        year: this.year,
        fixedSalary: this.fixed_salary,
        dayHours: this.dayHours,
        mode: 'deduction',
        dateFrom: this.dateFrom,
        dateTo: this.dateTo,
      },
    });
    ref.afterClosed().subscribe((result) => {
      if (result?.saved) {
        this.loadIncentives();
      }
    });
  }
}
