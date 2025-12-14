import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CategoriesReportComponent } from './categories-report.component';

describe('CategoriesReportComponent', () => {
  let component: CategoriesReportComponent;
  let fixture: ComponentFixture<CategoriesReportComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [CategoriesReportComponent]
    });
    fixture = TestBed.createComponent(CategoriesReportComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
