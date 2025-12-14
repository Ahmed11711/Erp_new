import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from 'src/app/auth/auth.service';
import { ReportNewOrderService } from '../../services/report-New-order.service';
import { Location } from '@angular/common';
import { ActivatedRoute } from '@angular/router';

@Component({
  selector: 'report-order-new-details',
  templateUrl: './report-new-order-details.component.html',
  styleUrls: ['./report-new-order-details.component.css']
})
export class ReportNewOrdersComponentDetails implements OnInit {

  data: any[] = [];
  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];
  user: any;
reportType?: string;

  param = {}; 

  constructor(
    private reportService: ReportNewOrderService,
 private route: ActivatedRoute,
     private authService: AuthService,
    private location: Location 

  ) {}

ngOnInit() {
  this.user = this.authService.getUser();

  const orderId = this.route.snapshot.queryParamMap.get('order_id') ?? undefined;
  const assetId = this.route.snapshot.queryParamMap.get('asset_id') ?? undefined;

  if (orderId) {
    this.reportType = 'order';
    this.getAll(orderId, undefined);
  } else if (assetId) {
    this.reportType = 'asset';
    this.getAll(undefined, assetId);
  } else {
    this.reportType = 'general';
    this.getAll(); // ممكن تعمل جلب عام أو رسالة
  }
}



getAll(orderId?: string, assetId?: string) {
  this.reportService.getAllByKey(orderId, assetId).subscribe((res: any) => {
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
