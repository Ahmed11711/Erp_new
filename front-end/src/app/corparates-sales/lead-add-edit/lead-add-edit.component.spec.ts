import { ComponentFixture, TestBed } from '@angular/core/testing';

import { LeadAddEditComponent } from './lead-add-edit.component';

describe('LeadAddEditComponent', () => {
  let component: LeadAddEditComponent;
  let fixture: ComponentFixture<LeadAddEditComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [LeadAddEditComponent]
    });
    fixture = TestBed.createComponent(LeadAddEditComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
