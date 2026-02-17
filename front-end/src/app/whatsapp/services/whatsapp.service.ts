import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root',
})
export class WhatsAppService {
  constructor(private http: HttpClient) {}

  sendMessage(data: { customer_phone: string; message: string; order_id?: number }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/whatsapp/send`, data);
  }

  sendTemplateMessage(data: { customer_phone: string; template_id: number; order_id?: number }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/whatsapp/send-template`, data);
  }

  sendMessageFromOrder(data: { order_id: number; message: string }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/whatsapp/send-from-order`, data);
  }

  getChatMessages(customerId: number): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/chat/${customerId}`);
  }

  getCustomers(params?: any): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/customers`, { params });
  }

  getTemplates(): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/templates`);
  }

  createTemplate(data: { name: string; content: string; description?: string }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/whatsapp/templates`, data);
  }
}
