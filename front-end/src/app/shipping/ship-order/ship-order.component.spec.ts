import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ShipOrderComponent } from './ship-order.component';

describe('ShipOrderComponent', () => {
  let component: ShipOrderComponent;
  let fixture: ComponentFixture<ShipOrderComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ShipOrderComponent]
    });
    fixture = TestBed.createComponent(ShipOrderComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
