import { HttpClientTestingModule } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormsModule } from '@angular/forms';

import { OtherIncomeComponent } from './other-income.component';

describe('OtherIncomeComponent', () => {
  let component: OtherIncomeComponent;
  let fixture: ComponentFixture<OtherIncomeComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule, FormsModule],
      declarations: [OtherIncomeComponent]
    });
    fixture = TestBed.createComponent(OtherIncomeComponent);
    component = fixture.componentInstance;
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
