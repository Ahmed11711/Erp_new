import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ReportNewOrdersComponent } from './report-new-order.component';

describe('CustomerAccountsComponent', () => {
  let component: ReportNewOrdersComponent;
  let fixture: ComponentFixture<ReportNewOrdersComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ReportNewOrdersComponent]
    });
    fixture = TestBed.createComponent(ReportNewOrdersComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
