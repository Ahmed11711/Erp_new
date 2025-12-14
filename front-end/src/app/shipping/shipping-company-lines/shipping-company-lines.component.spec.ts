import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ShippingCompanyLinesComponent } from './shipping-company-lines.component';

describe('ShippingCompanyLinesComponent', () => {
  let component: ShippingCompanyLinesComponent;
  let fixture: ComponentFixture<ShippingCompanyLinesComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ShippingCompanyLinesComponent]
    });
    fixture = TestBed.createComponent(ShippingCompanyLinesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
