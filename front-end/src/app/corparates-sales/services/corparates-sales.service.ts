import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class CorparatesSalesService {

  constructor( private http:HttpClient) { }

  getLeadSource(){
    return this.http.get(`${environment.Url}/lead-source`);
  }

  getLeadTool(){
    return this.http.get(`${environment.Url}/lead-tool`);
  }

  getLeadIndustry(){
    return this.http.get(`${environment.Url}/lead-industry`);
  }

  addLeadSource(data){
    return this.http.post(`${environment.Url}/lead-source`, data);
  }

  addLeadTool(data){
    return this.http.post(`${environment.Url}/lead-tool`, data);
  }

  getLeads(params: any = {}){
    return this.http.get(`${environment.Url}/lead` , {params});
  }

  getLeadTeamUsers(){
    return this.http.get(`${environment.Url}/lead/team-users`);
  }

  getLeadActivityStats(params: any = {}){
    return this.http.get(`${environment.Url}/lead/activity-stats`, {params});
  }

  getLeadsById(id:any){
    return this.http.get(`${environment.Url}/lead/${id}`);
  }

  addLead(formData:any){
    return this.http.post(`${environment.Url}/lead` , formData);
  }

  addToLead(formData:any){
    return this.http.post(`${environment.Url}/edit-lead` , formData);
  }

  getPendingRecommenders(params?: { userId?: number; from_date?: string }) {
    return this.http.get(`${environment.Url}/lead/recommenders/pending`, { params: params || {} });
  }

  deleteRecommender(id: number) {
    return this.http.delete(`${environment.Url}/lead/recommenders/${id}`);
  }

  toggleRecommenderDone(id: number) {
    return this.http.patch(`${environment.Url}/lead/recommenders/${id}/toggle-done`, {});
  }

}
