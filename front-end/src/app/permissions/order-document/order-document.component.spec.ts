import { ComponentFixture, TestBed } from '@angular/core/testing';

import { OrderDocumentComponent } from './order-document.component';

describe('OrderDocumentComponent', () => {
  let component: OrderDocumentComponent;
  let fixture: ComponentFixture<OrderDocumentComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [OrderDocumentComponent]
    });
    fixture = TestBed.createComponent(OrderDocumentComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
