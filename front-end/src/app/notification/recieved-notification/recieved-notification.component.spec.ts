import { ComponentFixture, TestBed } from '@angular/core/testing';

import { RecievedNotificationComponent } from './recieved-notification.component';

describe('RecievedNotificationComponent', () => {
  let component: RecievedNotificationComponent;
  let fixture: ComponentFixture<RecievedNotificationComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [RecievedNotificationComponent]
    });
    fixture = TestBed.createComponent(RecievedNotificationComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
