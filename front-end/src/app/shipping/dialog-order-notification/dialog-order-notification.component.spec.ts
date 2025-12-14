import { ComponentFixture, TestBed } from '@angular/core/testing';

import { DialogOrderNotificationComponent } from './dialog-order-notification.component';

describe('DialogOrderNotificationComponent', () => {
  let component: DialogOrderNotificationComponent;
  let fixture: ComponentFixture<DialogOrderNotificationComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [DialogOrderNotificationComponent]
    });
    fixture = TestBed.createComponent(DialogOrderNotificationComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
