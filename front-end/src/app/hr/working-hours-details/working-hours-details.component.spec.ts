import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MatDialog } from '@angular/material/dialog';
import { ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import { AuthService } from 'src/app/auth/auth.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import { EmployeeService } from '../services/employee.service';
import { WorkingHoursDetailsComponent } from './working-hours-details.component';

describe('WorkingHoursDetailsComponent', () => {
  let component: WorkingHoursDetailsComponent;
  let fixture: ComponentFixture<WorkingHoursDetailsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [WorkingHoursDetailsComponent],
      providers: [
        {
          provide: EmployeeService,
          useValue: {
            getEmpDataPerMonth: () => of({}),
            dataPerMonth: () => of({ merits: [], subtraction: [], advance_payment: [] }),
            holidayDays: () => of([]),
            registerAttendance: () => of({ success: true }),
            empHoursPermision: () => of({ message: 'ok' }),
            revertAbsenceDayPermission: () => of({ message: 'ok' }),
            absenceDeduction: () => of({ success: true }),
            addMerit: () => of({ id: 99 }),
            addSubtraction: () => of({ id: 100 }),
            deleteMerit: () => of('deleted'),
          },
        },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: { params: { id: 18 } },
            url: { _value: [{ path: 'workinghoursdetails' }] },
          },
        },
        { provide: AuthService, useValue: { getUser: () => 'Admin' } },
        { provide: RbacService, useValue: { canAny: () => false, can: () => false } },
        { provide: MatDialog, useValue: { open: () => ({ afterClosed: () => of(null) }) } },
      ],
    });
    fixture = TestBed.createComponent(WorkingHoursDetailsComponent);
    component = fixture.componentInstance;
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('month account shows extra hours and late hours separately', () => {
    component.fixedSalary = 10500;
    component.dayHours = 8;
    component.actualHours = '297:30';
    component.hoursDifferenceStr = '89:30';
    component.differnceSalary = 6777.04;
    component.tableData = [
      {
        salary_type2: 'حافز',
        salary_type: 200,
        hoursDifference: '02:00',
        holiday: false,
        is_overTime_removed: false,
      },
      {
        salary_type2: 'خصم',
        salary_type: 50,
        hoursDifference: '-01:00',
        absence_day: false,
      },
      {
        salary_type2: 'خصم',
        salary_type: 350,
        hoursDifference: '1 يوم',
        absence_day: true,
        absence_days: 1,
      },
      {
        salary_type2: 'خصم',
        salary_type: 605.77,
        hoursDifference: '1 يوم',
      },
    ];
    component.merits = [
      { type: 'حوافز', reason: 'إضافي يوم كامل (2024-07-03)', amount: 350 },
      { type: 'حوافز', reason: 'إضافي 2 ساعة (2024-07-10)', amount: 151.44 },
    ];
    component.subtractions = [];
    component.advancePayments = [];

    (component as any).buildMonthAccountSummary();

    expect(component.monthAccount?.overtimeHoursLabel).toBe('02:00');
    expect(component.monthAccount?.overtimeFromHours).toBe(200);
    expect(component.monthAccount?.lateHoursLabel).toBe('01:00');
    expect(component.monthAccount?.hoursDeduction).toBe(50);
    expect(component.monthAccount?.extraDaysCount).toBe(1);
    expect(component.monthAccount?.extraDaysAmount).toBe(350);
    expect(component.monthAccount?.extraHoursFromMerits).toBe(2);
    expect(component.monthAccount?.extraHoursMeritsAmount).toBe(151.44);
    expect(component.monthAccount?.deductionDaysCount).toBe(1);
    expect(component.monthAccount?.deductionDaysAmount).toBe(350);
    expect(component.monthAccount?.extraMeritsLabel).toBe('1 يوم إضافي + 2 ساعة');
  });

  it('late hours amount follows 02:19 and ignores full-day خصم rows', () => {
    component.fixedSalary = 10500;
    component.dayHours = 8;
    component.actualHours = '297:30';
    component.tableData = [
      {
        salary_type2: 'خصم',
        salary_type: 175.42,
        hoursDifference: '-02:19',
        absence_day: false,
      },
      {
        salary_type2: 'خصم',
        salary_type: 605.77,
        hoursDifference: '1 يوم',
      },
      {
        salary_type2: 'خصم',
        salary_type: 605.77,
        hoursDifference: '1 يوم',
      },
      {
        salary_type2: 'خصم',
        salary_type: 605.77,
        hoursDifference: '1 يوم',
      },
      {
        salary_type2: 'خصم',
        salary_type: 350,
        hoursDifference: '1 يوم',
        absence_day: true,
        absence_days: 1,
      },
    ];
    component.merits = [];
    component.subtractions = [];
    component.advancePayments = [];

    (component as any).buildMonthAccountSummary();

    expect(component.monthAccount?.lateHoursLabel).toBe('02:19');
    expect(component.monthAccount?.hoursDeduction).toBe(175.42);
    expect(component.monthAccount?.deductionDaysCount).toBe(1);
    expect(component.monthAccount?.deductionDaysAmount).toBe(350);
  });

  it('absence day permission payload is a full day with no deduction', () => {
    component.dayHours = 8;
    component.id = 63;
    const payload = component.absenceDayPermissionPayload({
      id: null,
      employee_id: 63,
      date: '2026-07-11',
      hours: '00:00',
      check_in: '08:00 AM',
      hoursDifference: '1 يوم',
    });

    expect(payload['hours_permission']).toBe('08:00');
    expect(payload['hours']).toBe('08:00');
    expect(payload['check_in']).toBe('08:00 AM');
    expect(payload['check_out']).toBe('04:00 PM');
    expect(payload['absence_deduction']).toBeNull();
    expect(payload['date']).toBe('2026-07-11');
  });

  it('absence day revert payload restores placeholder without leftover shift hours', () => {
    component.dayHours = 8;
    component.id = 63;
    const payload = component.absenceDayRevertPayload({
      id: 12,
      employee_id: 63,
      date: '2026-08-26',
      hours: '08:00',
      check_in: '08:00 AM',
      check_out: '04:00 PM',
      hours_permission: '08:00',
    });

    expect(payload['hours_permission']).toBeNull();
    expect(payload['absence_deduction']).toBeNull();
    expect(payload['hours']).toBe('00:00');
    expect(payload['check_in']).toBe('08:00 AM');
    expect(payload['check_out']).toBe('08:00 AM');
    expect(payload['id']).toBe(12);
    expect(payload['date']).toBe('2026-08-26');
  });

  it('shows row menu for full-day permission leave so it can be undone', () => {
    component.dayHours = 8;
    const leaveDay = {
      hours_permission: '08:00',
      hours: '08:00',
      check_in: '08:00 AM',
      check_out: '04:00 PM',
      hoursDifference: '00:00',
      salary_type: 0,
      salary_type2: 'اذن',
      holiday: false,
      vacation: false,
    };

    expect(component.isFullDayPermissionLeave(leaveDay)).toBeTrue();
    expect(component.showSheetRowMenu(leaveDay)).toBeTrue();
    expect(component.isAbsentDay(leaveDay)).toBeFalse();
  });

  it('absence day double payload sets two-day deduction', () => {
    component.id = 63;
    const row = {
      id: 12,
      employee_id: 63,
      date: '2026-08-26',
      hours: '00:00',
      check_in: '08:00 AM',
      absence_deduction: null,
    };

    expect(component.isAbsenceDoubled(row)).toBeFalse();
    expect(component.absenceDayDoublePayload(row)).toEqual({
      id: 12,
      employee_id: 63,
      date: '2026-08-26',
      absence_deduction: '2',
    });
    expect(component.absenceDayUndoDoublePayload(row)).toEqual({
      id: 12,
      employee_id: 63,
      date: '2026-08-26',
      absence_deduction: null,
    });
    expect(component.isAbsenceDoubled({ ...row, absence_deduction: 2 })).toBeTrue();
  });

  it('month account counts a doubled absence as two deduction days', () => {
    component.fixedSalary = 15000;
    component.dayHours = 8;
    component.actualHours = '208:00';
    component.tableData = [
      {
        salary_type2: 'خصم',
        salary_type: 1000,
        hoursDifference: '2 يوم',
        absence_day: true,
        absence_days: 2,
        absence_deduction: 2,
      },
    ];
    component.merits = [];
    component.subtractions = [];
    component.advancePayments = [];

    (component as any).buildMonthAccountSummary();

    expect(component.monthAccount?.deductionDaysCount).toBe(2);
    expect(component.monthAccount?.deductionDaysAmount).toBe(1000);
    expect(component.monthAccount?.absenceSub).toBe(1000);
  });

  it('regular attendance day can take a full or half-day bonus payload', () => {
    component.id = 63;
    component.year = 2026;
    component.month = 8;
    component.fixedSalary = 15000;
    component.dayHours = 8;
    const workDay = {
      date: '2026-08-26',
      hours: '08:00',
      check_in: '08:00 AM',
      check_out: '04:00 PM',
      holiday: false,
      vacation: false,
    };

    expect(component.isRegularAttendanceDay(workDay)).toBeTrue();
    expect(component.showSheetRowMenu(workDay)).toBeTrue();
    expect(component.attendanceDayBonusPayload(workDay, 1)).toEqual({
      employee_id: 63,
      month: 8,
      year: 2026,
      type: 'حوافز',
      amount: 750,
      reason: 'إضافي يوم كامل (2026-08-26)',
    });
    expect(component.attendanceDayBonusPayload(workDay, 0.5)).toEqual({
      employee_id: 63,
      month: 8,
      year: 2026,
      type: 'مكافئات',
      amount: 375,
      reason: 'مكافأة نصف يوم (2026-08-26)',
    });
  });

  it('month account uses the same half-day bonus amount shown on the row', () => {
    component.fixedSalary = 15000;
    component.dayHours = 8;
    component.tableData = [];
    component.merits = [
      { type: 'مكافئات', reason: 'مكافأة نصف يوم (2026-08-26)', amount: 375 },
      { type: 'مكافئات', reason: 'مكافأة أخرى', amount: 100 },
    ];
    component.subtractions = [];
    component.advancePayments = [];

    (component as any).buildMonthAccountSummary();

    expect(component.attendanceBonusForDay('2026-08-26')?.amount).toBe(375);
    expect(component.monthAccount?.halfDayBonusCount).toBe(1);
    expect(component.monthAccount?.halfDayBonusAmount).toBe(375);
    expect(component.monthAccount?.otherRewards).toBe(100);
    expect(component.monthAccount?.rewards).toBe(475);
    expect(component.monthAccount?.totalMerit).toBe(15000 + 475);
  });
});
