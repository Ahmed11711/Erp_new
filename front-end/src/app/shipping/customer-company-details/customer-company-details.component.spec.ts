import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CustomerCompanyDetailsComponent } from './customer-company-details.component';

describe('CustomerCompanyDetailsComponent', () => {
  let component: CustomerCompanyDetailsComponent;
  let fixture: ComponentFixture<CustomerCompanyDetailsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [CustomerCompanyDetailsComponent]
    });
    fixture = TestBed.createComponent(CustomerCompanyDetailsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
