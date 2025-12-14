import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AddEstateComponent } from './add-estate.component';

describe('AddEstateComponent', () => {
  let component: AddEstateComponent;
  let fixture: ComponentFixture<AddEstateComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [AddEstateComponent]
    });
    fixture = TestBed.createComponent(AddEstateComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
