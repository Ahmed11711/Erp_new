import { ComponentFixture, TestBed } from '@angular/core/testing';

import { DialogCollectFromCustomerCompanyComponent } from './dialog-collect-from-customer-company.component';

describe('DialogCollectFromCustomerCompanyComponent', () => {
  let component: DialogCollectFromCustomerCompanyComponent;
  let fixture: ComponentFixture<DialogCollectFromCustomerCompanyComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [DialogCollectFromCustomerCompanyComponent]
    });
    fixture = TestBed.createComponent(DialogCollectFromCustomerCompanyComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
