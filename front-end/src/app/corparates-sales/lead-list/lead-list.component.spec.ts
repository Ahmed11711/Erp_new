import { ComponentFixture, TestBed } from '@angular/core/testing';

import { LeadListComponent } from './lead-list.component';

describe('LeadListComponent', () => {
  let component: LeadListComponent;
  let fixture: ComponentFixture<LeadListComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [LeadListComponent]
    });
    fixture = TestBed.createComponent(LeadListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
