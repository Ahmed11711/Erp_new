import { ComponentFixture, TestBed } from '@angular/core/testing';

import { OrderSourceComponent } from './order-source.component';

describe('OrderSourceComponent', () => {
  let component: OrderSourceComponent;
  let fixture: ComponentFixture<OrderSourceComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [OrderSourceComponent]
    });
    fixture = TestBed.createComponent(OrderSourceComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
