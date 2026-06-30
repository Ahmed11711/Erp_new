import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl } from '@angular/forms';
import { UserService } from 'src/app/manage-system/services/user.service';
import { OrderService } from 'src/app/shipping/services/order.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-trackings',
  templateUrl: './trackings.component.html',
  styleUrls: ['./trackings.component.css']
})
export class TrackingsComponent implements OnInit {

  dataList: any[] = [];
  userdata: any[] = [];
  selectedTrackingId: number | null = null;
  loadError = '';

  length = 0;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15, 50, 100];

  constructor(
    private OrderService: OrderService,
    private userService: UserService,
    public rbac: RbacService,
  ) {}

  /** فتح الأوردر في شاشة التعديل — للأدمن فقط */
  get canEditTracking(): boolean {
    return this.rbac.can('system.activity_log.edit');
  }

  editLink(elm: any): any[] | null {
    const id = Number(elm?.order_id ?? 0);
    return id ? ['/dashboard/shipping/editorder', id] : null;
  }

  canOpenEdit(elm: any): boolean {
    return this.canEditTracking && !!this.editLink(elm);
  }

  form: FormGroup = new FormGroup({
    created_at: new FormControl(''),
    user_id: new FormControl('0'),
  });

  ngOnInit(): void {
    this.form.valueChanges.subscribe(() => {
      this.page = 0;
      this.getData();
    });

    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const day = today.getDate();
    this.form.patchValue({
      created_at: `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`,
    }, { emitEvent: false });

    this.getData();
    this.getUsers();
  }

  getUsers() {
    this.userService.compactDirectory().subscribe((res: any) => {
      this.userdata = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
    });
  }

  private buildParams(): Record<string, string | number> {
    const params: Record<string, string | number> = {
      itemsPerPage: this.pageSize,
      page: this.page + 1,
    };

    const createdAt = String(this.form.value.created_at ?? '').trim();
    if (createdAt && createdAt !== '0') {
      params.created_at = createdAt;
    }

    const userId = Number(this.form.value.user_id ?? 0);
    if (userId > 0) {
      params.user_id = userId;
    }

    return params;
  }

  getData() {
    this.loadError = '';
    this.OrderService.getTrackings(this.buildParams()).subscribe({
      next: (res: any) => {
        this.dataList = Array.isArray(res?.data) ? res.data : [];
        this.length = Number(res?.total ?? 0);
        this.pageSize = Number(res?.per_page ?? this.pageSize);
      },
      error: (err) => {
        this.dataList = [];
        this.length = 0;
        this.loadError = err?.error?.message || 'تعذّر تحميل سجل التتبع.';
      },
    });
  }

  undo(id: number) {
    this.OrderService.undo(id).subscribe({
      next: () => {
        Swal.fire({
          icon: 'success',
          showConfirmButton: false,
          timer: 1500,
        });
        this.getData();
      },
      error: (err) => {
        Swal.fire({
          icon: 'error',
          title: err?.error?.message || 'تعذّر تنفيذ التراجع.',
          showConfirmButton: false,
          timer: 2000,
        });
      },
    });
  }

  canUndo(elm: any): boolean {
    const action = String(elm?.action ?? '').trim();
    const currentStatus = String(elm?.order?.order_status ?? '').trim();
    return !!action && currentStatus === action;
  }

  onPageChange(event: any) {
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.getData();
  }
}
