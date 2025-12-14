import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ManufacturingAdditionsComponent } from './manufacturing-additions.component';

describe('ManufacturingAdditionsComponent', () => {
  let component: ManufacturingAdditionsComponent;
  let fixture: ComponentFixture<ManufacturingAdditionsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ManufacturingAdditionsComponent]
    });
    fixture = TestBed.createComponent(ManufacturingAdditionsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
