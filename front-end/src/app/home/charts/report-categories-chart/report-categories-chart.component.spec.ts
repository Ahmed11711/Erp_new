import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ReportCategoriesChartComponent } from './report-categories-chart.component';

describe('ReportCategoriesChartComponent', () => {
  let component: ReportCategoriesChartComponent;
  let fixture: ComponentFixture<ReportCategoriesChartComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ReportCategoriesChartComponent]
    });
    fixture = TestBed.createComponent(ReportCategoriesChartComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
