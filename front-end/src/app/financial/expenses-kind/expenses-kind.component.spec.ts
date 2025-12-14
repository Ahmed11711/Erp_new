import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ExpensesKindComponent } from './expenses-kind.component';

describe('ExpensesKindComponent', () => {
  let component: ExpensesKindComponent;
  let fixture: ComponentFixture<ExpensesKindComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ExpensesKindComponent]
    });
    fixture = TestBed.createComponent(ExpensesKindComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
