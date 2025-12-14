import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ReviewAbsencesComponent } from './review-absences.component';

describe('ReviewAbsencesComponent', () => {
  let component: ReviewAbsencesComponent;
  let fixture: ComponentFixture<ReviewAbsencesComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ReviewAbsencesComponent]
    });
    fixture = TestBed.createComponent(ReviewAbsencesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
