import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AddSubtractionComponent } from './add-subtraction.component';

describe('AddSubtractionComponent', () => {
  let component: AddSubtractionComponent;
  let fixture: ComponentFixture<AddSubtractionComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [AddSubtractionComponent]
    });
    fixture = TestBed.createComponent(AddSubtractionComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
