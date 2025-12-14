import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AddCommitmentComponent } from './add-commitment.component';

describe('AddCommitmentComponent', () => {
  let component: AddCommitmentComponent;
  let fixture: ComponentFixture<AddCommitmentComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [AddCommitmentComponent]
    });
    fixture = TestBed.createComponent(AddCommitmentComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
