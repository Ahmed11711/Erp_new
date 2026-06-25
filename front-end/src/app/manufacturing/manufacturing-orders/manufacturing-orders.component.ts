import { DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ManufacturingService } from '../services/manufacturing.service';
import { RbacService } from 'src/app/core/rbac/rbac.service';

@Component({
  selector: 'app-manufacturing-orders',
  templateUrl: './manufacturing-orders.component.html',
  styleUrls: ['./manufacturing-orders.component.css']
})
export class ManufacturingOrdersComponent implements OnInit {

  data: any[] = [];
  tableData: any[] = [];

  /** Cleared after first HTTP response (success or error). */
  listPending = true;
  listError: string | null = null;
  deleteError: string | null = null;
  deletingId: number | null = null;

  deletedData: any[] = [];
  deletedPending = false;
  deletedError: string | null = null;
  showDeletedLog = false;

  private _name: string = '';
  status: string = 'حاله التصنيع';
  private _date: any;

  constructor(
    private datePipe: DatePipe,
    private manufacturingService: ManufacturingService,
    readonly rbac: RbacService,
  ) {}

  ngOnInit(): void {
    this.getData();
    if (this.canDeleteOrder()) {
      this.loadDeletedLog();
    }
  }

  getData(): void {
    this.listPending = true;
    this.listError = null;
    this.products = [];
    this.manufacturingService.confirmed().subscribe({
      next: (result: any) => {
        const rows = Array.isArray(result) ? result : [];
        this.data = rows;
        this.tableData = [...rows];
        const seen = new Set<number>();
        rows.forEach((elm) => {
          const p = elm?.product;
          if (p?.id != null && !seen.has(p.id)) {
            seen.add(p.id);
            this.products.push(p);
          }
        });
        this.listPending = false;
      },
      error: () => {
        this.data = [];
        this.tableData = [];
        this.listError =
          'تعذر تحميل أوامر التصنيع. تحقق من تسجيل الدخول أو صلاحيات القسم.';
        this.listPending = false;
      },
    });
  }

  loadDeletedLog(): void {
    if (!this.canDeleteOrder()) {
      return;
    }
    this.deletedPending = true;
    this.deletedError = null;
    this.manufacturingService.confirmedDeleted().subscribe({
      next: (rows) => {
        this.deletedData = Array.isArray(rows) ? rows : [];
        this.deletedPending = false;
      },
      error: () => {
        this.deletedData = [];
        this.deletedError = 'تعذر تحميل سجل الأوامر المحذوفة.';
        this.deletedPending = false;
      },
    });
  }

  openDeletedLog(): void {
    this.showDeletedLog = true;
    this.loadDeletedLog();
  }

  closeDeletedLog(): void {
    this.showDeletedLog = false;
  }

  formatDeletedAt(value: string | null | undefined): string {
    if (!value) {
      return '-';
    }
    return this.datePipe.transform(value, 'yyyy-MM-dd HH:mm') ?? value;
  }

  get name(): string {
    return this._name;
  }

  set name(value: string) {
    this._name = value;
    this.filterData('');
  }

  get date(): any {
    return this._date;
  }

  set date(value: any) {
    this._date = value;
    this.filterData('');
  }

  products: any[] = [];
  catword = 'category_name';

  more: any[] = [];
  catword2 = 'category_name';

  productChange(event) {
    this.name = event.category_name;
  }


  OnDateChange(event) {
    const inputDate = new Date(event);
    this.date = this.datePipe.transform(inputDate, 'yyyy-MM-dd');
  }

  filterData(e) {
    let filteredData = [...this.data];

    if (this._name != '') {
      filteredData = filteredData.filter(item => item.product.category_name === this.name);
    }

    if (e === undefined) {
      filteredData = [...this.data];
    }


    if (this.date) {
      filteredData = filteredData.filter(item => item.date === this.date);
    }

    if (this.status !=  'حاله التصنيع') {
      filteredData = filteredData.filter(item => item.status === this.status);
    }

    this.tableData = filteredData;
  }

  finish(id:number){
    this.manufacturingService.done(id).subscribe(result=>{
      console.log(result);

      if (result == "success") {
        this.getData();
      }
    })
  }

  canDeleteOrder(): boolean {
    return this.rbac.can('manufacturing.delete_order') || this.rbac.can('system.rbac');
  }

  deleteOrder(elm: { id: number; product?: { category_name?: string } }): void {
    if (!this.canDeleteOrder() || this.deletingId != null) {
      return;
    }

    const label = elm.product?.category_name ?? `#${elm.id}`;
    const ok = window.confirm(
      `هل تريد حذف أمر التصنيع «${label}»؟\n\nسيتم عكس جميع حركات المخزون كأن الأمر لم يُنفَّذ.`
    );
    if (!ok) {
      return;
    }

    this.deleteError = null;
    this.deletingId = elm.id;
    this.manufacturingService.deleteConfirmedOrder(elm.id).subscribe({
      next: () => {
        this.deletingId = null;
        this.getData();
        this.openDeletedLog();
      },
      error: (err) => {
        this.deletingId = null;
        this.deleteError = err?.error?.message ?? 'تعذر حذف أمر التصنيع.';
      },
    });
  }
}
