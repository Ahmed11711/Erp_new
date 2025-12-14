import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SupplierAccountsComponent } from './supplier-accounts.component';

describe('SupplierAccountsComponent', () => {
  let component: SupplierAccountsComponent;
  let fixture: ComponentFixture<SupplierAccountsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [SupplierAccountsComponent]
    });
    fixture = TestBed.createComponent(SupplierAccountsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
