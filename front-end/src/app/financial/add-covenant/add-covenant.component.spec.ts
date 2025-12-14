import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AddCovenantComponent } from './add-covenant.component';

describe('AddCovenantComponent', () => {
  let component: AddCovenantComponent;
  let fixture: ComponentFixture<AddCovenantComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [AddCovenantComponent]
    });
    fixture = TestBed.createComponent(AddCovenantComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
