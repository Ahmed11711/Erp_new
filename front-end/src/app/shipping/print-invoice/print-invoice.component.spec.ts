import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PrintInvoiceComponent } from './print-invoice.component';
import { OrderInvoicePrintService } from '../services/order-invoice-print.service';

describe('PrintInvoiceComponent', () => {
  let component: PrintInvoiceComponent;
  let fixture: ComponentFixture<PrintInvoiceComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [PrintInvoiceComponent],
      providers: [OrderInvoicePrintService],
    });
    fixture = TestBed.createComponent(PrintInvoiceComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
