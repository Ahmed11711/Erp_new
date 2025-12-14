import { ComponentFixture, TestBed } from '@angular/core/testing';

import { WorkingHoursDetailsComponent } from './working-hours-details.component';

describe('WorkingHoursDetailsComponent', () => {
  let component: WorkingHoursDetailsComponent;
  let fixture: ComponentFixture<WorkingHoursDetailsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [WorkingHoursDetailsComponent]
    });
    fixture = TestBed.createComponent(WorkingHoursDetailsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
