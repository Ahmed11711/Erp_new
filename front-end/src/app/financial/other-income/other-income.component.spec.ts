import { ComponentFixture, TestBed } from '@angular/core/testing';

import { OtherIncomeComponent } from './other-income.component';

describe('OtherIncomeComponent', () => {
  let component: OtherIncomeComponent;
  let fixture: ComponentFixture<OtherIncomeComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [OtherIncomeComponent]
    });
    fixture = TestBed.createComponent(OtherIncomeComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
