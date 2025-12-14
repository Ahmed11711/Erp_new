import { Component } from '@angular/core';

@Component({
  selector: 'app-admin-order',
  templateUrl: './admin-order.component.html',
  styleUrls: ['./admin-order.component.css']
})
export class AdminOrderComponent {

  data:any[]=[];

  constructor(){

  }

  ngOnInit(){
    this.getData();
  }

  getData(){
    // return this.employeeService.data().subscribe(result=>{
    //   this.data=result;
    // })
  }

}
