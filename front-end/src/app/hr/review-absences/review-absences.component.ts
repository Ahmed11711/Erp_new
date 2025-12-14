import { Component } from '@angular/core';
import { EmployeeService } from '../services/employee.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-review-absences',
  templateUrl: './review-absences.component.html',
  styleUrls: ['./review-absences.component.css']
})
export class ReviewAbsencesComponent {

  data:any[]=[];
  employees:any[]=[];
  catword:any="name";
  id!:number;



  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50];


  constructor(private empService:EmployeeService){

  }

  ngOnInit(){
    this.empService.data().subscribe(result=>this.employees=result);
    this.search(arguments);
  }

  empChange(e:any){
    this.id = e.id;
    this.search(arguments);

  }

  resetData(e:any){
    this.id=0;
    delete this.param['employee_id'];
    this.search(arguments);

  }

  param:any = {};
  search(e:any){

    if (e?.target?.id == 'date') {
      this.param['date'] = e.target.value;
    }

    if (e?.target?.id == 'code') {
      this.param['code'] = e.target.value;
    }

    if (this.id && this.id != 0) {
      this.param['employee_id'] = this.id;
    }

    this.empService.absences(this.pageSize,this.page+1,this.param).subscribe((data:any)=>{
      this.data=data.data;
      this.length=data.total;
      this.pageSize=data.per_page;
    })
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.search(arguments);
  }

  sendStatus(status:string , id:number){
    let data = {'absence_status':status , 'id':id}
    if (status == 'تم باستاذان') {
      Swal.fire({
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'بدون خصم',
        cancelButtonText: 'خصم يوم بيوم',
      }).then((result:any) => {
        if (result.isConfirmed) {
          data['absence_count']='0';
          this.empService.employeeAbsenseStatus(data).subscribe(result=>{
            if (result) {
              Swal.fire({
                icon:'success',
                timer:2000,
                showConfirmButton:false
              })
              this.search(arguments);
            }
          })
        }
        if (result.dismiss == 'cancel') {
          data['absence_count']='1';
          this.empService.employeeAbsenseStatus(data).subscribe(result=>{
            if (result) {
              Swal.fire({
                icon:'success',
                timer:2000,
                showConfirmButton:false
              })
              this.search(arguments);
            }
          })
        }
      })
    } else if(status == 'خصم'){
      Swal.fire({
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'خصم يوم بيوم',
        cancelButtonText: 'خصم يوم بيومين',
      }).then((result:any) => {
        if (result.isConfirmed) {
          data['absence_count']='1';
          this.empService.employeeAbsenseStatus(data).subscribe(result=>{
            if (result) {
              Swal.fire({
                icon:'success',
                timer:2000,
                showConfirmButton:false
              })
              this.search(arguments);
            }
          })
        }
        if (result.dismiss == 'cancel') {
          data['absence_count']='2';
          this.empService.employeeAbsenseStatus(data).subscribe(result=>{
            if (result) {
              Swal.fire({
                icon:'success',
                timer:2000,
                showConfirmButton:false
              })
              this.search(arguments);
            }
          })
        }
      })
    }
  }


}
