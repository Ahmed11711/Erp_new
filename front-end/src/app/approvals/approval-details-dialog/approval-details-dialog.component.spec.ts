import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ApprovalDetailsDialogComponent } from './approval-details-dialog.component';

describe('ApprovalDetailsDialogComponent', () => {
  let component: ApprovalDetailsDialogComponent;
  let fixture: ComponentFixture<ApprovalDetailsDialogComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ApprovalDetailsDialogComponent]
    });
    fixture = TestBed.createComponent(ApprovalDetailsDialogComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
