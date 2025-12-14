import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ShippincompanyReportsComponent } from './shippincompany-reports.component';

describe('ShippincompanyReportsComponent', () => {
  let component: ShippincompanyReportsComponent;
  let fixture: ComponentFixture<ShippincompanyReportsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ShippincompanyReportsComponent]
    });
    fixture = TestBed.createComponent(ShippincompanyReportsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
