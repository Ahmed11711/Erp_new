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
});
