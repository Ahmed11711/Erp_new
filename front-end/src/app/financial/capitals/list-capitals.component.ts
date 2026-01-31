import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/env/env';

@Component({
    selector: 'app-list-capitals',
    template: `
    <div class="container-fluid">
       <div class="d-flex justify-content-between align-items-center mb-3">
          <h2>مبالغ رأس المال المضافة</h2>
          <button class="btn btn-primary" routerLink="create">إضافة رأس مال</button>
       </div>
       <div class="card p-3">
          <table class="table table-bordered table-striped">
             <thead>
                <tr>
                   <th>#</th>
                   <th>التاريخ</th>
                   <th>المبلغ</th>
                   <th>مكان الإيداع</th>
                   <th>حساب حقوق الملكية</th>
                   <th>ملاحظات</th>
                </tr>
             </thead>
             <tbody>
                <tr *ngFor="let capital of capitals | paginate: { itemsPerPage: 20, currentPage: p, totalItems: total }">
                   <td>{{capital.id}}</td>
                   <td>{{capital.date}}</td>
                   <td>{{capital.amount | number}}</td>
                   <td>
                       {{ capital.target_type === 'bank' ? 'بنك' : 'خزينة' }}
                       (#{{capital.target_id}})
                   </td>
                   <td>{{capital.equity_account?.name}}</td>
                   <td>{{capital.notes}}</td>
                </tr>
                <tr *ngIf="capitals.length === 0">
                   <td colspan="6" class="text-center">لا توجد بيانات</td>
                </tr>
             </tbody>
          </table>
            <div class="text-center mt-3">
                <pagination-controls (pageChange)="p = $event; getData($event)"></pagination-controls>
            </div>
       </div>
    </div>
  `,
    styles: []
})
export class ListCapitalsComponent implements OnInit {
    capitals: any[] = [];
    loading = false;
    p = 1;
    total = 0;
    private apiUrl = environment.Url + '/capitals';

    constructor(private http: HttpClient) { }

    ngOnInit() {
        this.getData(1);
    }

    getData(page: number) {
        this.loading = true;
        this.p = page;
        this.http.get<any>(`${this.apiUrl}?page=${page}`).subscribe({
            next: (res) => {
                this.capitals = res.data || [];
                this.total = res.total || 0;
                this.loading = false;
            },
            error: (err) => {
                console.error(err);
                this.loading = false;
            }
        });
    }
}
