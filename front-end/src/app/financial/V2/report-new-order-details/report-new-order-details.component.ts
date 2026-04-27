import { Component, OnInit, ViewChild } from '@angular/core';
import { Location } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { PageEvent } from '@angular/material/paginator';
import { MatPaginator } from '@angular/material/paginator';
import { AuthService } from 'src/app/auth/auth.service';
import { ReportNewOrderService } from '../../services/report-New-order.service';

@Component({
  selector: 'report-order-new-details',
  templateUrl: './report-new-order-details.component.html',
  styleUrls: [
    '../../../shared/styles/report-page-shell.css',
    './report-new-order-details.component.css',
  ],
})
export class ReportNewOrdersComponentDetails implements OnInit {
  @ViewChild(MatPaginator) paginator?: MatPaginator;

  rawData: any[] = [];
  data: any[] = [];
  length = 0;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15, 50, 100];
  user: any;
  reportType?: string;
  searchTerm = '';
  loading = false;

  constructor(
    private reportService: ReportNewOrderService,
    private route: ActivatedRoute,
    private authService: AuthService,
    private location: Location,
  ) {}

  ngOnInit() {
    this.user = this.authService.getUser();

    const orderId = this.route.snapshot.queryParamMap.get('order_id') ?? undefined;
    const assetId = this.route.snapshot.queryParamMap.get('asset_id') ?? undefined;

    if (orderId) {
      this.reportType = 'order';
      this.load(orderId, undefined);
    } else if (assetId) {
      this.reportType = 'asset';
      this.load(undefined, assetId);
    } else {
      this.reportType = 'general';
      this.load();
    }
  }

  load(orderId?: string, assetId?: string) {
    this.loading = true;
    this.reportService.getAllByKey(orderId, assetId).subscribe({
      next: (res: any) => {
        this.rawData = Array.isArray(res?.data) ? res.data : [];
        this.page = 0;
        this.paginator?.firstPage();
        this.applyFilterAndPage();
        this.loading = false;
      },
      error: (err) => {
        console.error('Error fetching order details', err);
        this.rawData = [];
        this.applyFilterAndPage();
        this.loading = false;
      },
    });
  }

  private assetLabel(row: any): string {
    const a = row?.assets;
    if (!a) {
      return '';
    }
    if (typeof a === 'object') {
      return String(a.name ?? a.code ?? '');
    }
    return String(a);
  }

  private getFilteredRows(): any[] {
    const q = this.searchTerm.trim().toLowerCase();
    if (!q) {
      return [...this.rawData];
    }
    return this.rawData.filter((row) => {
      const hay = [
        row?.order_id,
        this.assetLabel(row),
        row?.entry_batch_code,
        row?.debit,
        row?.credit,
      ]
        .map((v) => String(v ?? '').toLowerCase())
        .join(' ');
      return hay.includes(q);
    });
  }

  applyFilterAndPage() {
    const filtered = this.getFilteredRows();
    this.length = filtered.length;
    let start = this.page * this.pageSize;
    if (this.length > 0 && start >= this.length) {
      this.page = 0;
      queueMicrotask(() => this.paginator?.firstPage());
      start = 0;
    }
    this.data = filtered.slice(start, start + this.pageSize);
  }

  onSearchInput() {
    this.page = 0;
    this.paginator?.firstPage();
    this.applyFilterAndPage();
  }

  reload() {
    const orderId = this.route.snapshot.queryParamMap.get('order_id') ?? undefined;
    const assetId = this.route.snapshot.queryParamMap.get('asset_id') ?? undefined;
    if (orderId) {
      this.load(orderId, undefined);
    } else if (assetId) {
      this.load(undefined, assetId);
    } else {
      this.load();
    }
  }

  goBack() {
    this.location.back();
  }

  onPageChange(event: PageEvent) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.applyFilterAndPage();
  }

  rowDisplayIndex(i: number): number {
    return this.page * this.pageSize + i + 1;
  }
}
