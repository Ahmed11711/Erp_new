import { Component, OnInit } from '@angular/core';
import { ApproveService } from './services/approve.service';
import { MatDialog } from '@angular/material/dialog';
import { DialogOrderNotificationComponent } from '../shipping/dialog-order-notification/dialog-order-notification.component';
import { ApprovalDetailsDialogComponent } from './approval-details-dialog/approval-details-dialog.component';
import { BreakpointObserver, BreakpointState } from '@angular/cdk/layout';

@Component({
  selector: 'app-approvals',
  templateUrl: './approvals.component.html',
  styleUrls: ['./approvals.component.css']
})
export class ApprovalsComponent implements OnInit{
  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50,100];
  data:any[]=[];

  constructor(private approvalService:ApproveService, public dialog: MatDialog, private breakpointObserver: BreakpointObserver){}

  ngOnInit(): void {
    this.search(arguments);
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
  }

  param:any = {};
  search(e){
    if (e?.target?.id == 'type') {
      this.param['type'] = e.target.value;
    }
    if (e?.target?.id == 'status') {
      this.param['status'] = e.target.value;
    }
    if (e?.target?.id == 'table_name') {
      this.param['table_name'] = e.target.value;
    }
    if (e?.target?.id == 'date') {
      this.param['date'] = e.target.value;
    }
    this.approvalService.getApprovals(this.pageSize , this.page+1 , this.param).subscribe((res:any)=>{
      this.data = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
      this.data = this.data.map(elm => {
        return {
          id: elm.id,
          created_at: elm.created_at,
          column_values: elm.column_values,
          status: this.transformStatus(elm.status),
          details: elm.details,
          user: elm.user.name,
          type: this.transformType(elm.type),
          table_name: this.transformTableName(elm.table_name)
        };
      });
    })
  }

  clearSearch() {
    this.param = {};
    let type:any = document.getElementById('type');
    type.value = 'النوع';
    let status:any = document.getElementById('status');
    status.value = 'الحالة';
    let table_name:any = document.getElementById('table_name');
    table_name.value = 'المكان';
    this.search(arguments);
  }

  transformType(type: string): string {
    switch (type) {
      case 'update':
        return 'تعديل';
      case 'add':
        return 'اضافة';
      case 'delete':
        return 'حذف';
      default:
        return type;
    }
  }

  transformStatus(status: string): string {
    switch (status) {
      case 'approved':
        return 'تم الموافقة';
      case 'rejected':
        return 'مرفوضه';
      case 'pending':
        return 'قيد الانتظار';
      default:
        return status;
    }
  }
  filterPlace:any[]=[
    {table_name:'employee_finger_print_sheets' , arabic:'كشف الحضور والانصراف'},
    {table_name:'purchases' , arabic:'مشتريات'},
  ];
  transformTableName(tableName: string): string {
    if (tableName === 'employee_finger_print_sheets') {
      return 'كشف الحضور والانصراف';
    }
    if (tableName === 'purchases') {
      return 'مشتريات';
    }
    return tableName;
  }

  sendApprovelStatus(id , status){
    this.approvalService.approvalStatus({id,status}).subscribe((res:any)=>{
      if (res) {
        this.search(arguments);
      }
    })
  }

  showDetails(data: any) {
  this.breakpointObserver.observe(['(max-width: 600px)']).subscribe((state: BreakpointState) => {
    const width = state.matches ? '100%' : '50%';

    const dialogRef = this.dialog.open(ApprovalDetailsDialogComponent, {
      width: width,
      data: { data, refreshData: () => this.search(arguments) },
    });

    dialogRef.afterClosed().subscribe(result => {
      // this.search();
    });
  });
  }

}
