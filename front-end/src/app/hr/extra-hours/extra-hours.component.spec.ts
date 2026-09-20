import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MatDialog } from '@angular/material/dialog';
import { of } from 'rxjs';
import { AuthService } from 'src/app/auth/auth.service';
import { EmployeeService } from '../services/employee.service';
import { ExtraHoursComponent } from './extra-hours.component';

describe('ExtraHoursComponent', () => {
  let component: ExtraHoursComponent;
  let fixture: ComponentFixture<ExtraHoursComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ExtraHoursComponent],
      providers: [
        { provide: EmployeeService, useValue: { data: () => of([]), dataPerMonth: () => of({ merits: [] }) } },
        { provide: MatDialog, useValue: { open: () => ({ afterClosed: () => of(null) }) } },
        { provide: AuthService, useValue: { getUser: () => 'Admin' } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ExtraHoursComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
