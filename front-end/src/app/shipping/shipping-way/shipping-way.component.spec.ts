import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ShippingWayComponent } from './shipping-way.component';

describe('ShippingWayComponent', () => {
  let component: ShippingWayComponent;
  let fixture: ComponentFixture<ShippingWayComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ShippingWayComponent]
    });
    fixture = TestBed.createComponent(ShippingWayComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
