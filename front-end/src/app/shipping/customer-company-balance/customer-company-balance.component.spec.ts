import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CustomerCompanyBalanceComponent } from './customer-company-balance.component';

describe('CustomerCompanyBalanceComponent', () => {
  let component: CustomerCompanyBalanceComponent;
  let fixture: ComponentFixture<CustomerCompanyBalanceComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [CustomerCompanyBalanceComponent]
    });
    fixture = TestBed.createComponent(CustomerCompanyBalanceComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
