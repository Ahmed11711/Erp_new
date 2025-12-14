import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CustomerAccountDetailsComponent } from './customer-account-details.component';

describe('CustomerAccountDetailsComponent', () => {
  let component: CustomerAccountDetailsComponent;
  let fixture: ComponentFixture<CustomerAccountDetailsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [CustomerAccountDetailsComponent]
    });
    fixture = TestBed.createComponent(CustomerAccountDetailsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
