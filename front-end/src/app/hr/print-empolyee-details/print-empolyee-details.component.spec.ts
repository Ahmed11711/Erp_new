import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PrintEmpolyeeDetailsComponent } from './print-empolyee-details.component';

describe('PrintEmpolyeeDetailsComponent', () => {
  let component: PrintEmpolyeeDetailsComponent;
  let fixture: ComponentFixture<PrintEmpolyeeDetailsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [PrintEmpolyeeDetailsComponent]
    });
    fixture = TestBed.createComponent(PrintEmpolyeeDetailsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('splits mixed Arabic reason dates for print', () => {
    const printer = new PrintEmpolyeeDetailsComponent();
    expect(printer.printReasonArabic('إضافي جمعة (2026-08-07)')).toBe('إضافي جمعة');
    expect(printer.printReasonDate('إضافي جمعة (2026-08-07)')).toBe('2026-08-07');
    expect(printer.printReasonArabic('إضافي يوم كامل (07-08-2026)')).toBe('إضافي يوم كامل');
    expect(printer.printReasonDate('إضافي يوم كامل (07-08-2026)')).toBe('07-08-2026');
  });
});
