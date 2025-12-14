import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { EmployeeService } from '../services/employee.service';

@Component({
  selector: 'app-employee',
  templateUrl: './employee.component.html',
  styleUrls: ['./employee.component.css']
})
export class EmployeeComponent {

  data:any[]=[];
  tableData:any[]=[];

  length = 50;
  pageSize = 20;
  page = 0;
  pageSizeOptions = [20,50];

  constructor(private employeeService:EmployeeService){

  }

  ngOnInit(){
    this.search(arguments);
    // this.getData();
  }


onPageChange(event:any){
  this.pageSize = event.pageSize;
  this.page = event.pageIndex;
  this.search(arguments);
}

search(e:any){
  const param = {};
  if(e?.target?.id === "name"){
    param['name']=e?.target?.value;
  }
  if(e?.target?.id === "code"){
    param['code']=e?.target?.value;
  }

  this.employeeService.searchEmployee(this.pageSize,this.page+1,param).subscribe((data:any)=>{
    this.data=data.data;
    this.length=data.total;
    this.pageSize=data.per_page;
  })
}


  deleteData(id:number){
    this.employeeService.deleteEmp(id).subscribe(result=>{
      if (result == "deleted sucuessfully") {
        this.search('');
      }
    },
    (error)=>{
      if (error.status == 404 && error.statusText == 'Not Found') {
        alert(error.statusText);
      }
    }
    )
  }

}
