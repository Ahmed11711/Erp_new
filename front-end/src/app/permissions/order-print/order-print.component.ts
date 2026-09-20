import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/env/env';

@Component({
  selector: 'app-order-print',
  templateUrl: './order-print.component.html',
  styleUrls: ['./order-print.component.css']
})
export class OrderPrintComponent implements OnInit {
  showInvoiceDate = true;
  saving = false;
  saveMessage = '';

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.http.get<Record<string, string>>(`${environment.Url}/accounting/settings`).subscribe({
      next: (settings) => {
        this.showInvoiceDate = this.parseShowInvoiceDateSetting(settings?.show_invoice_date);
      },
      error: () => {
        this.showInvoiceDate = true;
      },
    });
  }

  onShowInvoiceDateChange(): void {
    this.saving = true;
    this.saveMessage = '';
    this.http.post(`${environment.Url}/accounting/settings`, {
      show_invoice_date: this.showInvoiceDate ? '1' : '0',
    }).subscribe({
      next: () => {
        this.saving = false;
        this.saveMessage = 'تم حفظ الإعداد';
      },
      error: () => {
        this.saving = false;
        this.saveMessage = 'تعذر حفظ الإعداد';
      },
    });
  }

  private parseShowInvoiceDateSetting(value: unknown): boolean {
    if (value === undefined || value === null || value === '') {
      return true;
    }
    return !['0', 'false', 'off', 'no'].includes(String(value).toLowerCase());
  }
}
