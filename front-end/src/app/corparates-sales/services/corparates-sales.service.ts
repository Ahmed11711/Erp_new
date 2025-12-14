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

  getLeads(params={}){
    return this.http.get(`${environment.Url}/lead` , {params});
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

}
