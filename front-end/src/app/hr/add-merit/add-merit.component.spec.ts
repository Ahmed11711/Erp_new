import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AddMeritComponent } from './add-merit.component';

describe('AddMeritComponent', () => {
  let component: AddMeritComponent;
  let fixture: ComponentFixture<AddMeritComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [AddMeritComponent]
    });
    fixture = TestBed.createComponent(AddMeritComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
