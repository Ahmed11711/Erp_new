import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SalaryCashingComponent } from './salary-cashing.component';

describe('SalaryCashingComponent', () => {
  let component: SalaryCashingComponent;
  let fixture: ComponentFixture<SalaryCashingComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [SalaryCashingComponent]
    });
    fixture = TestBed.createComponent(SalaryCashingComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
