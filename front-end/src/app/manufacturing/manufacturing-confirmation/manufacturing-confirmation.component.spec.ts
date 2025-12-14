import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ManufacturingConfirmationComponent } from './manufacturing-confirmation.component';

describe('ManufacturingConfirmationComponent', () => {
  let component: ManufacturingConfirmationComponent;
  let fixture: ComponentFixture<ManufacturingConfirmationComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ManufacturingConfirmationComponent]
    });
    fixture = TestBed.createComponent(ManufacturingConfirmationComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
