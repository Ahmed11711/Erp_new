import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ShippingLineStatementComponent } from './shipping-line-statement.component';

describe('ShippingLineStatementComponent', () => {
  let component: ShippingLineStatementComponent;
  let fixture: ComponentFixture<ShippingLineStatementComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ShippingLineStatementComponent]
    });
    fixture = TestBed.createComponent(ShippingLineStatementComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
