import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of } from 'rxjs';
import { MatPaginatorModule } from '@angular/material/paginator';

import { ProcurementReportComponent } from './procurement-report.component';
import { InvoiceService } from 'src/app/purchases/service/invoice.service';
import { SharedModule } from 'src/app/shared/shared.module';

describe('ProcurementReportComponent', () => {
  let component: ProcurementReportComponent;
  let fixture: ComponentFixture<ProcurementReportComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ProcurementReportComponent],
      imports: [SharedModule, MatPaginatorModule],
      providers: [
        { provide: InvoiceService, useValue: { search: () => of({ data: [], total: 0 }) } },
        { provide: Router, useValue: { navigateByUrl: () => {} } },
      ],
    });
    fixture = TestBed.createComponent(ProcurementReportComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
