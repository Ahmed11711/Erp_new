import { Component } from '@angular/core';
import { CorparatesSalesService } from '../services/corparates-sales.service';
import { FormControl, FormGroup } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';

@Component({
  selector: 'app-lead-list',
  templateUrl: './lead-list.component.html',
  styleUrls: ['./lead-list.component.css']
})
export class LeadListComponent {

  leads:any[]=[];
  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];

  countries:any[]=[];
  leadSource:any[]=[];
  leadTools:any[]=[];
  leadIndustry:any[]=[];
  teamUsers: any[] = [];

  recommenderCount = 0;
  recommenders: any[] = [];
  showRecommenderDropdown = false;

  constructor(private CorparatesSalesService:CorparatesSalesService, private http:HttpClient, private router: Router){}

  ngOnInit(): void {

    this.form.valueChanges.subscribe(() => {
      this.getLeads();
    });

    this.http.get('assets/country/CountryCodes.json').subscribe((data:any)=>{
      this.countries=data;
    })
    this.getLeads();
    this.getLeadSource();
    this.getLeadTool();
    this.getLeadIndustry();
    this.loadTeamUsers();
    this.loadPendingRecommenders();
  }

  loadPendingRecommenders() {
    this.CorparatesSalesService.getPendingRecommenders().subscribe({
      next: (res: any) => {
        this.recommenderCount = res?.count ?? 0;
        this.recommenders = res?.recommenders ?? [];
      },
      error: () => {
        this.recommenderCount = 0;
        this.recommenders = [];
      }
    });
  }

  toggleRecommenderDropdown() {
    this.showRecommenderDropdown = !this.showRecommenderDropdown;
    if (this.showRecommenderDropdown) {
      this.loadPendingRecommenders();
    }
  }

  closeRecommenderDropdown() {
    this.showRecommenderDropdown = false;
  }

  goToLeadDetails(leadId: number) {
    this.closeRecommenderDropdown();
    this.router.navigate(['/dashboard/corparates-sales/leads', leadId]);
  }

  loadTeamUsers(): void {
    this.CorparatesSalesService.getLeadTeamUsers().subscribe({
      next: (res: any) => {
        this.teamUsers = Array.isArray(res) ? res : (res?.data || []);
      },
      error: () => {
        this.teamUsers = [];
      }
    });
  }

  form:FormGroup = new FormGroup({
    country: new FormControl('0'),
    leadSource: new FormControl('0'),
    leadTool: new FormControl('0'),
    leadIndustry: new FormControl('0'),
    userId: new FormControl(''),
  });

  getLeads(){
    const formVal = this.form.value;
    const params: any = {
      itemsPerPage: this.pageSize,
      page: this.page + 1,
      country: formVal.country,
      leadSource: formVal.leadSource,
      leadTool: formVal.leadTool,
      leadIndustry: formVal.leadIndustry,
    };
    if (formVal.userId) params.userId = formVal.userId;
    this.CorparatesSalesService.getLeads(params).subscribe((res:any)=>{
      this.leads = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    });

  }

  reset(){
    this.form.patchValue({
      country: '0',
      leadSource: '0',
      leadTool: '0',
      leadIndustry: '0',
      userId: '',
    })
  }

  getLeadSource(){
    this.CorparatesSalesService.getLeadSource().subscribe((data:any)=> this.leadSource = data);
  }

  getLeadTool(){
    this.CorparatesSalesService.getLeadTool().subscribe((data:any)=> this.leadTools = data);
  }

  getLeadIndustry(){
    this.CorparatesSalesService.getLeadIndustry().subscribe((data:any)=> this.leadIndustry = data);
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.getLeads();
  }

}
