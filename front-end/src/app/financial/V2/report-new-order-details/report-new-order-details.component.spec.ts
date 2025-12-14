import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ReportNewOrdersComponentDetails } from './report-new-order-details.component';

describe('CustomerAccountsComponent', () => {
  let component: ReportNewOrdersComponentDetails;
  let fixture: ComponentFixture<ReportNewOrdersComponentDetails>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ReportNewOrdersComponentDetails]
    });
    fixture = TestBed.createComponent(ReportNewOrdersComponentDetails);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
