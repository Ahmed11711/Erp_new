import { ComponentFixture, TestBed } from '@angular/core/testing';

import { DialogPayMoneyForSupplierComponent } from './dialog-pay-money-for-supplier.component';

describe('DialogPayMoneyForSupplierComponent', () => {
  let component: DialogPayMoneyForSupplierComponent;
  let fixture: ComponentFixture<DialogPayMoneyForSupplierComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [DialogPayMoneyForSupplierComponent]
    });
    fixture = TestBed.createComponent(DialogPayMoneyForSupplierComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
