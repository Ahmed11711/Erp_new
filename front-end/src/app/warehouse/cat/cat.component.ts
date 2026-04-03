import { Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { CategoryService } from 'src/app/categories/services/category.service';
import {Location} from '@angular/common';
import Swal from 'sweetalert2';
import { Subscription } from 'rxjs';
@Component({
  selector: 'app-cat',
  templateUrl: './cat.component.html',
  styleUrls: ['./cat.component.css']
})
export class CatComponent implements OnInit, OnDestroy {

  warehouse:string = "";
  categories:any;
  balance:number= 0;

  length = 50;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15,50];

  private querySub?: Subscription;

  constructor(private category:CategoryService, private route:ActivatedRoute, private _location:Location) {}


  ngOnInit(){
    this.querySub = this.route.queryParams.subscribe((params) => {
      const next = params['warehouse'] ?? '';
      if (next !== this.warehouse) {
        this.page = 0;
        this.param = {};
      }
      this.warehouse = next;
      this.balance = Number(params['balance']);
      if (Number.isNaN(this.balance)) {
        this.balance = 0;
      }
      this.getData();
    });
  }

  ngOnDestroy(): void {
    this.querySub?.unsubscribe();
  }

  getData(){
    this.category.categoryDetails(this.warehouse, this.pageSize,this.page+1 , this.param).subscribe((res:any)=>{
      this.categories = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
      this.page = event.pageIndex;
      this.getData();
  }

  back() {
    this._location.back();
  }

  param = {};
  onCategorychange(event :any){
    if (event.target.id == 'type') {
      this.param['name']=event.target.value;
    }
    if (event.target.id == 'check') {
      console.log(event.target.checked);
      if (event.target.checked) {
        this.param['sort']= true;
      } else {
        delete this.param['sort'];
      }

    }
    this.getData();
  }

  private parseQuantityInput(value: unknown): number | null {
    if (value === null || value === undefined || value === '') {
      return null;
    }
    const n = typeof value === 'number' ? value : Number(String(value).trim());
    if (Number.isNaN(n)) {
      return null;
    }
    return n;
  }

  addQuantiy(id: number, name: string) {
    Swal.fire({
      title: ` ( ${name} ) اضافة كمية الى  `,
      input: 'number',
      inputAttributes: { min: '0', step: 'any' },
      showCancelButton: true,
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      const qty = this.parseQuantityInput(result.value);
      if (qty === null || qty <= 0) {
        Swal.fire({ icon: 'warning', text: 'يجب ادخال قيمة صحيحة أكبر من صفر' });
        return;
      }
      this.category.changeCategoryQuantity(id, 'add', qty).subscribe({
        next: () => this.getData(),
        error: () => Swal.fire({ icon: 'error', title: 'فشل تحديث الكمية' }),
      });
    });
  }

  removeQuantiy(id: number, name: string) {
    Swal.fire({
      title: ` ( ${name} )  تقليل كمية من `,
      input: 'number',
      inputAttributes: { min: '0', step: 'any' },
      showCancelButton: true,
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      const qty = this.parseQuantityInput(result.value);
      if (qty === null || qty <= 0) {
        Swal.fire({ icon: 'warning', text: 'يجب ادخال قيمة صحيحة أكبر من صفر' });
        return;
      }
      this.category.changeCategoryQuantity(id, 'add', -qty).subscribe({
        next: () => this.getData(),
        error: () => Swal.fire({ icon: 'error', title: 'فشل تحديث الكمية' }),
      });
    });
  }

  editQuantiy(id: number, name: string) {
    Swal.fire({
      title: ` ( ${name} )  تعديل كمية  `,
      input: 'number',
      inputAttributes: { min: '0', step: 'any' },
      showCancelButton: true,
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      const qty = this.parseQuantityInput(result.value);
      if (qty === null || qty < 0) {
        Swal.fire({ icon: 'warning', text: 'يجب ادخال قيمة صحيحة' });
        return;
      }
      this.category.changeCategoryQuantity(id, 'edit', qty).subscribe({
        next: () => this.getData(),
        error: () => Swal.fire({ icon: 'error', title: 'فشل تحديث الكمية' }),
      });
    });
  }

  monthlyInventory(){
    Swal.fire({
      title: ' تأكيد الجرد الشهري ؟',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم',
      cancelButtonText: 'لا',
    }).then((result:any) => {
      if (result.isConfirmed) {
        this.category.monthlyInventory(this.warehouse).subscribe(res =>{
          if (res == 'success') {
            Swal.fire({
              icon:'success',
              showConfirmButton:false,
              timer:1500,
            });
          }
        } ,
        (err:any) =>{
          Swal.fire({
            icon:'error',
            text:"!!تم الجرد بالفعل",
            showConfirmButton:false,
            timer:2000,
          });
        }
      );
      }
    })

  }

}
