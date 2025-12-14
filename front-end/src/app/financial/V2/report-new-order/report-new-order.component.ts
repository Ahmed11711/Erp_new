import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from 'src/app/auth/auth.service';
import { ReportNewOrderService } from '../../services/report-New-order.service';
import { Location } from '@angular/common';

@Component({
  selector: 'report-new-order',
  templateUrl: './report-new-order.component.html',
  styleUrls: ['./report-new-order.component.css']
})
export class ReportNewOrdersComponent implements OnInit {

  data: any[] = [];
  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];
  user: any;

  param = {}; 

  constructor(
    private reportService: ReportNewOrderService,
    private route: Router,
    private authService: AuthService,
    private location: Location 

  ) {}

  ngOnInit() {
    this.user = this.authService.getUser();
    this.getAll(); 
  }

   getAll() {
    this.reportService.getAll().subscribe((res: any) => {
      this.data = res.data;
      this.length = res.data.length; 
      this.pageSize = res.data.length; 
    }, err => {
      console.error('Error fetching all orders', err);
    });
  }

   goBack() {
    this.location.back(); 
  }
  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search();
  }

  search() {
    this.reportService.searchCustomer(this.pageSize, this.page + 1, this.param).subscribe((res: any) => {
      this.data = res.data;
      this.length = res.total;
      this.pageSize = res.per_page;
    });
  }
}
