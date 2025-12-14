import { ComponentFixture, TestBed } from '@angular/core/testing';

import { TrackingsComponent } from './trackings.component';

describe('TrackingsComponent', () => {
  let component: TrackingsComponent;
  let fixture: ComponentFixture<TrackingsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [TrackingsComponent]
    });
    fixture = TestBed.createComponent(TrackingsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
