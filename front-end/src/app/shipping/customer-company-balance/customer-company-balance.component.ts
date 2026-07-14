import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { CompaniesService } from '../services/companies.service';

@Component({
  selector: 'app-customer-company-balance',
  templateUrl: './customer-company-balance.component.html',
  styleUrls: ['./customer-company-balance.component.css']
})
export class CustomerCompanyBalanceComponent implements OnInit {
  id!: any;
  data: any[] = [];
  name = '';
  balance = 0;
  loading = false;
  loadError = '';

  length = 0;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15, 50];

  constructor(private company: CompaniesService, private router: ActivatedRoute) {
    this.id = this.router.snapshot.paramMap.get('id');
  }

  ngOnInit() {
    this.getData();
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.getData();
  }

  getRouterLink(ref: string): any[] {
    if (ref && /^\d/.test(ref)) {
      return ['/dashboard/shipping/orderdetails', ref];
    }
    return [];
  }

  getData() {
    this.loading = true;
    this.loadError = '';
    this.company.companyBalanceDetails(this.id, this.pageSize, this.page + 1).subscribe({
      next: (res: any) => {
        this.name = res.name ?? res[0] ?? '';
        this.balance = res.balance ?? 0;
        this.data = res.data?.data ?? [];
        this.length = res.data?.total ?? 0;
        this.pageSize = res.data?.per_page ?? this.pageSize;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.loadError = 'تعذر تحميل كشف الحساب';
        this.data = [];
        this.length = 0;
      },
    });
  }
}
