import { Component } from '@angular/core';
import { CorparatesSalesService } from '../services/corparates-sales.service';
import { FormControl, FormGroup } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

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

  constructor(private CorparatesSalesService:CorparatesSalesService, private http:HttpClient){}

  ngOnInit(): void {

    this.form.valueChanges.subscribe(() => {
      this.getLeads();
    });

    this.http.get('assets/country/CountryCodes.json').subscribe((data:any)=>{
      this.countries=data;
      console.log(this.countries);

    })
    this.getLeads();
    this.getLeadSource();
    this.getLeadTool();
    this.getLeadIndustry();
  }

  form:FormGroup = new FormGroup({
    country: new FormControl('0'),
    leadSource: new FormControl('0'),
    leadTool: new FormControl('0'),
    leadIndustry: new FormControl('0'),
  });

  getLeads(){
    let params = {
      itemsPerPage:this.pageSize,page:this.page+1,...this.form.value
    }
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
