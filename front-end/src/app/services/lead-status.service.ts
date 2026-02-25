import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class LeadStatusService {
  private apiUrl = 'http://localhost:8000/api/lead-statuses';

  constructor(private http: HttpClient) { }

  // Get all lead statuses
  getAllStatuses(): Observable<any[]> {
    return this.http.get<any[]>(this.apiUrl);
  }

  // Get active statuses (excluding Archived)
  getActiveStatuses(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/active`);
  }

  // Get closed/won statuses
  getClosedWonStatuses(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/closed-won`);
  }

  // Get lost/closed statuses
  getClosedLostStatuses(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/closed-lost`);
  }

  // Get follow-up leads
  getFollowUpLeads(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/follow-up-leads`);
  }

  // Create new lead status
  createStatus(status: any): Observable<any> {
    return this.http.post<any>(this.apiUrl, status);
  }

  // Update lead status
  updateStatus(id: number, status: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/${id}`, status);
  }

  // Delete lead status
  deleteStatus(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }

  // Update lead status with next action
  updateLeadStatus(leadId: number, data: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/lead/${leadId}`, data);
  }
}
