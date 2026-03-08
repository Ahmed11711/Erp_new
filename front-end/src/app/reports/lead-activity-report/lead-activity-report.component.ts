import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { CorparatesSalesService } from '../../corparates-sales/services/corparates-sales.service';
import { LeadStatusService } from '../../services/lead-status.service';
import { FormControl, FormGroup } from '@angular/forms';
import { debounceTime } from 'rxjs/operators';

@Component({
  selector: 'app-lead-activity-report',
  templateUrl: './lead-activity-report.component.html',
  styleUrls: ['./lead-activity-report.component.css']
})
export class LeadActivityReportComponent implements OnInit {
  leads: any[] = [];
  activityStats: any[] = [];
  teamUsers: any[] = [];
  leadStatuses: any[] = [];
  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15, 50, 100];
  isLoading = false;

  recommenderCount = 0;
  recommenders: any[] = [];
  showRecommenderDropdown = false;

  form: FormGroup = new FormGroup({
    sortBy: new FormControl('last_activity'),
    sortOrder: new FormControl('desc'),
    userId: new FormControl(''),
    statusId: new FormControl(''),
    activityDateFrom: new FormControl(''),
    activityDateTo: new FormControl(''),
  });

  constructor(
    private corparatesSalesService: CorparatesSalesService,
    private leadStatusService: LeadStatusService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.loadTeamUsers();
    this.loadLeadStatuses();
    this.loadPendingRecommenders();

    this.form.valueChanges.pipe(debounceTime(350)).subscribe(() => {
      this.page = 0;
      this.loadData();
    });
    this.loadData();
  }

  loadPendingRecommenders(): void {
    this.corparatesSalesService.getPendingRecommenders().subscribe({
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

  toggleRecommenderDropdown(): void {
    this.showRecommenderDropdown = !this.showRecommenderDropdown;
    if (this.showRecommenderDropdown) {
      this.loadPendingRecommenders();
    }
  }

  goToLeadFromReminder(leadId: number): void {
    this.showRecommenderDropdown = false;
    this.router.navigate(['/dashboard/corparates-sales/leads', leadId]);
  }

  loadTeamUsers(): void {
    this.corparatesSalesService.getLeadTeamUsers().subscribe({
      next: (res: any) => {
        this.teamUsers = Array.isArray(res) ? res : (res?.data || []);
      },
      error: () => {
        this.teamUsers = [];
      }
    });
  }

  loadLeadStatuses(): void {
    this.leadStatusService.getAllStatuses().subscribe({
      next: (data: any) => {
        this.leadStatuses = (Array.isArray(data) ? data : (data?.data || [])).sort((a: any, b: any) => (a.order || 0) - (b.order || 0));
      },
      error: () => {
        this.leadStatuses = [];
      }
    });
  }

  loadData(): void {
    this.isLoading = true;
    const formVal = this.form.value;
    const params: any = {
      itemsPerPage: this.pageSize,
      page: this.page + 1,
      sortBy: formVal.sortBy,
      sortOrder: formVal.sortOrder,
    };
    if (formVal.userId) {
      params.userId = formVal.userId;
    }
    if (formVal.statusId) {
      params.statusId = formVal.statusId;
    }
    if (formVal.activityDateFrom) {
      params.activityDateFrom = formVal.activityDateFrom;
    }
    if (formVal.activityDateTo) {
      params.activityDateTo = formVal.activityDateTo;
    }

    this.corparatesSalesService.getLeads(params).subscribe({
      next: (res: any) => {
        this.leads = res.data || [];
        this.length = res.total || 0;
        this.pageSize = res.per_page || this.pageSize;
        this.isLoading = false;
      },
      error: () => {
        this.leads = [];
        this.isLoading = false;
      }
    });

    const statsParams: any = {};
    if (formVal.userId) statsParams.userId = formVal.userId;
    if (formVal.activityDateFrom) statsParams.activityDateFrom = formVal.activityDateFrom;
    if (formVal.activityDateTo) statsParams.activityDateTo = formVal.activityDateTo;
    this.corparatesSalesService.getLeadActivityStats(statsParams).subscribe({
      next: (res: any) => {
        this.activityStats = res.stats || [];
      },
      error: () => {
        this.activityStats = [];
      }
    });
  }

  onPageChange(event: any): void {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.loadData();
  }

  goToDetails(id: number): void {
    this.router.navigate(['/dashboard/corparates-sales/leads', id]);
  }

  reset(): void {
    this.page = 0;
    this.form.patchValue({
      sortBy: 'last_activity',
      sortOrder: 'desc',
      userId: '',
      statusId: '',
      activityDateFrom: '',
      activityDateTo: '',
    });
  }

  formatLastActivity(dateStr: string | null): string {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - d.getTime();
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    if (diffDays === 0) return 'اليوم';
    if (diffDays === 1) return 'أمس';
    if (diffDays < 7) return 'منذ ' + diffDays + ' أيام';
    return d.toLocaleDateString('ar-EG');
  }
}
