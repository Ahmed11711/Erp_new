import { ComponentFixture, TestBed } from '@angular/core/testing';

import { OrderPrintComponent } from './order-print.component';

describe('OrderPrintComponent', () => {
  let component: OrderPrintComponent;
  let fixture: ComponentFixture<OrderPrintComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [OrderPrintComponent]
    });
    fixture = TestBed.createComponent(OrderPrintComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
