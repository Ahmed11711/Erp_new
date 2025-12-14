import { DatePipe } from '@angular/common';
import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { EmployeeService } from '../services/employee.service';

@Component({
  selector: 'app-account-statement',
  templateUrl: './account-statement.component.html',
  styleUrls: ['./account-statement.component.css']
})
export class AccountStatementComponent {

  data:any[]=[];
  date!:any;
  isData:boolean = false;

  constructor(private empService:EmployeeService){
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const day = today.getDate();
    this.date = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
  }

  ngOnInit(){
    this.search(arguments);
  }

  search(e:any){
    this.isData = false;
    this.empService.accountStatment(this.date).subscribe((result:any)=>{
      this.data = result;
      console.log(result);

      if (result.length ==0) {
        this.isData = true;
      }
    })
  }

  reviewed(e,id,type){
    this.empService.reviewd(id,type,e.target.checked).subscribe((result:any)=>{
      console.log(result);
      if (result) {
        this.search(arguments);
      }

    });
  }

  onDateChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.date = target.value;
    this.search(arguments);
  }

}
