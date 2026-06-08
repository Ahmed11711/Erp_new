import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';
import { WHATSAPP_SKIP_GLOBAL_LOADING } from './whatsapp-http.util';

@Injectable({
  providedIn: 'root',
})
export class WhatsAppService {
  constructor(private http: HttpClient) {}

  sendMessage(data: { customer_phone: string; message: string; order_id?: number; phone_number_id?: string }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/whatsapp/send`, data, {
      headers: WHATSAPP_SKIP_GLOBAL_LOADING,
    });
  }

  sendTemplateMessage(data: { customer_phone: string; template_id: number; order_id?: number }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/whatsapp/send-template`, data);
  }

  sendMessageFromOrder(data: { order_id: number; message: string }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/whatsapp/send-from-order`, data);
  }

  /** List of Meta-approved templates (from backend config). Pass phone_number_id to filter by number. */
  getMetaTemplates(phoneNumberId?: string): Observable<any> {
    const url = `${environment.Url}/whatsapp/meta-templates`;
    const opts = { headers: WHATSAPP_SKIP_GLOBAL_LOADING };
    return phoneNumberId
      ? this.http.get<any>(url, {
          ...opts,
          params: { phone_number_id: phoneNumberId },
        })
      : this.http.get<any>(url, opts);
  }

  /** Send Meta WhatsApp template from order (for 24h window / first contact) */
  sendMetaTemplateFromOrder(data: {
    order_id: number;
    template_name: string;
    language_code?: string;
    body_parameters?: string[];
    phone_number_id?: string;
  }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/whatsapp/send-meta-template-from-order`, data);
  }

  getChatMessages(customerId: number): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/chat/${customerId}`, {
      headers: WHATSAPP_SKIP_GLOBAL_LOADING,
    });
  }

  /** Resolve customer by phone (same matching as backend; fixes +210… vs +2010…). */
  findCustomerByPhone(phone: string): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/customers/by-phone`, {
      params: { phone },
      headers: WHATSAPP_SKIP_GLOBAL_LOADING,
    });
  }

  /** Last few chat lines for tooltips (order list). */
  getWhatsAppSnippet(phone: string): Observable<any> {
    return this.http.get<any>(
      `${environment.Url}/whatsapp/customers/whatsapp-snippet`,
      { params: { phone }, headers: WHATSAPP_SKIP_GLOBAL_LOADING }
    );
  }

  getCustomers(params?: any): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/customers`, {
      params,
      headers: WHATSAPP_SKIP_GLOBAL_LOADING,
    });
  }

  getTemplates(): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/templates`, {
      headers: WHATSAPP_SKIP_GLOBAL_LOADING,
    });
  }

  createTemplate(data: { name: string; content: string; description?: string }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/whatsapp/templates`, data);
  }

  // New methods for WhatsApp number management
  /** يطابق صلاحيات صفحة admin/whatsapp-management (whatsapp.assign_numbers | system.rbac) */
  getAssignableUsers(): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/assignable-users`);
  }

  getAvailablePhoneNumbers(): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/phone-numbers`);
  }

  getAllAssignments(): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/assignments`);
  }

  getUserPhoneNumbers(): Observable<any> {
    return this.http.get<any>(`${environment.Url}/whatsapp/user-phone-numbers`);
  }

  assignUsersToPhoneNumber(data: { phone_number_id: string; user_ids: number[] }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/whatsapp/assign-users`, data);
  }

  removeUserAssignment(data: { phone_number_id: string; user_id: number }): Observable<any> {
    return this.http.delete<any>(`${environment.Url}/whatsapp/remove-assignment`, { body: data });
  }
}
