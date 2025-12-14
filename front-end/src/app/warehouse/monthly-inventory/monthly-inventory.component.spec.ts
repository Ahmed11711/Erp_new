import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MonthlyInventoryComponent } from './monthly-inventory.component';

describe('MonthlyInventoryComponent', () => {
  let component: MonthlyInventoryComponent;
  let fixture: ComponentFixture<MonthlyInventoryComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [MonthlyInventoryComponent]
    });
    fixture = TestBed.createComponent(MonthlyInventoryComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
