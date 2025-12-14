import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SentNotificationComponent } from './sent-notification.component';

describe('SentNotificationComponent', () => {
  let component: SentNotificationComponent;
  let fixture: ComponentFixture<SentNotificationComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [SentNotificationComponent]
    });
    fixture = TestBed.createComponent(SentNotificationComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
