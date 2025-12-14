import { ComponentFixture, TestBed } from '@angular/core/testing';

import { DialogNotificationNoteComponent } from './dialog-notification-note.component';

describe('DialogNotificationNoteComponent', () => {
  let component: DialogNotificationNoteComponent;
  let fixture: ComponentFixture<DialogNotificationNoteComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [DialogNotificationNoteComponent]
    });
    fixture = TestBed.createComponent(DialogNotificationNoteComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
