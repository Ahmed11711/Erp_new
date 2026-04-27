import { HttpClientTestingModule } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { RouterTestingModule } from '@angular/router/testing';

import { AddCovenantComponent } from './add-covenant.component';

describe('AddCovenantComponent', () => {
  let component: AddCovenantComponent;
  let fixture: ComponentFixture<AddCovenantComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule, ReactiveFormsModule, RouterTestingModule],
      declarations: [AddCovenantComponent]
    });
    fixture = TestBed.createComponent(AddCovenantComponent);
    component = fixture.componentInstance;
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
