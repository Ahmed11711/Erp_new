import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ShippingcompanyDetailsComponent } from './shippingcompany-details.component';

describe('ShippingcompanyDetailsComponent', () => {
  let component: ShippingcompanyDetailsComponent;
  let fixture: ComponentFixture<ShippingcompanyDetailsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ShippingcompanyDetailsComponent]
    });
    fixture = TestBed.createComponent(ShippingcompanyDetailsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
