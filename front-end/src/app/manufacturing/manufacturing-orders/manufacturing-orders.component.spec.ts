import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ManufacturingOrdersComponent } from './manufacturing-orders.component';

describe('ManufacturingOrdersComponent', () => {
  let component: ManufacturingOrdersComponent;
  let fixture: ComponentFixture<ManufacturingOrdersComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ManufacturingOrdersComponent]
    });
    fixture = TestBed.createComponent(ManufacturingOrdersComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
