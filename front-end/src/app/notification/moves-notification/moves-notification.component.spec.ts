import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MovesNotificationComponent } from './moves-notification.component';

describe('MovesNotificationComponent', () => {
  let component: MovesNotificationComponent;
  let fixture: ComponentFixture<MovesNotificationComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [MovesNotificationComponent]
    });
    fixture = TestBed.createComponent(MovesNotificationComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
