import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { CategoryService } from 'src/app/categories/services/category.service';
import {Location} from '@angular/common';
import Swal from 'sweetalert2';
@Component({
  selector: 'app-cat',
  templateUrl: './cat.component.html',
  styleUrls: ['./cat.component.css']
})
export class CatComponent {

  warehouse:string = "";
  categories:any;
  balance:number= 0;

  length = 50;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15,50];

  constructor(private category:CategoryService, private route:ActivatedRoute, private _location:Location) {
    this.warehouse =  this.route.snapshot.queryParams['warehouse'];
    this.balance = Number( this.route.snapshot.queryParams['balance']);
  }


  ngOnInit(){
    this.getData();
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

  addQuantiy(id , name){
    Swal.fire({
      title: ` ( ${name} ) اضافة كمية الى  `,
      input: 'number',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة'
        }
        if (value !== '') {
          this.category.changeCategoryQuantity(id , 'add' , value).subscribe(res =>{
            console.log(res);

            this.getData();

          })
        }
        return undefined
      }
    });
  }

  removeQuantiy(id , name){
    Swal.fire({
      title: ` ( ${name} )  تقليل كمية من `,
      input: 'number',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة'
        }
        if (value !== '') {
          this.category.changeCategoryQuantity(id , 'add' , -value).subscribe(res =>{
            this.getData();
          })
        }
        return undefined
      }
    });
  }

  editQuantiy(id , name){
    Swal.fire({
      title: ` ( ${name} )  تعديل كمية  `,
      input: 'number',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة'
        }
        if (value !== '') {
          this.category.changeCategoryQuantity(id , 'edit' , value).subscribe(res =>this.getData());
        }
        return undefined
      }
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
