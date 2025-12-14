import { ComponentFixture, TestBed } from '@angular/core/testing';

import { OrderCodingComponent } from './order-coding.component';

describe('OrderCodingComponent', () => {
  let component: OrderCodingComponent;
  let fixture: ComponentFixture<OrderCodingComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [OrderCodingComponent]
    });
    fixture = TestBed.createComponent(OrderCodingComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
