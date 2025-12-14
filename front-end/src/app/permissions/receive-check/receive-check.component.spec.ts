import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ReceiveCheckComponent } from './receive-check.component';

describe('ReceiveCheckComponent', () => {
  let component: ReceiveCheckComponent;
  let fixture: ComponentFixture<ReceiveCheckComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ReceiveCheckComponent]
    });
    fixture = TestBed.createComponent(ReceiveCheckComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
