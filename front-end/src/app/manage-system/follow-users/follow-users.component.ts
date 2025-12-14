import { DatePipe } from '@angular/common';
import { Component } from '@angular/core';

@Component({
  selector: 'app-follow-users',
  templateUrl: './follow-users.component.html',
  styleUrls: ['./follow-users.component.css']
})
export class FollowUsersComponent {
  data:any[]=[];

  constructor(private datePipe:DatePipe){

  }


  ngOnInit(){
    this.getData();
  }

  dateSelected = false;
  date:any;

  myFilter = (d: Date | null): boolean => {
    const today = new Date();
    const selectedDate = d || today;
    const timeDifference = Math.floor((today.getTime() - selectedDate.getTime()) / (1000 * 60 * 60 * 24));
    return timeDifference >= 0 && timeDifference <= 3;
  };

  OnDateChange(event){
    const inputDate = new Date(event);
    this.date = this.datePipe.transform(inputDate, 'yyyy-M-d');
    this.dateSelected = true;
  }


  getData(){
    // return this.shippingWay.data().subscribe(result=>{
    //   this.data=result;
    // })
  }

  editData(){

  }

  deleteData(){

  }

}
