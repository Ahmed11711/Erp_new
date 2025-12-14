import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { IncomeService } from '../services/income.service';

@Component({
  selector: 'app-other-income',
  templateUrl: './other-income.component.html',
  styleUrls: ['./other-income.component.css']
})
export class OtherIncomeComponent {

  data:any[]=[];
  tableData:any[]=[];
  dateFrom!:any;
  dateTo!:any;
  total:number=0;

  constructor(private incomeService:IncomeService){}

  ngOnInit(){
    this.incomeService.data().subscribe((result:any)=>{
      this.data=result;
      this.tableData=result;
      this.calcTotal();
    })
  }


  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateFrom = target.value;
    this.filterData('');
  }

  onDateToChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateTo = target.value;
    this.filterData('');
  }

  calcTotal(){
    this.tableData.forEach(result=>{
      this.total+=Number(result['income_amount']);
    })
  }
  searchWord!:string
  filterData(e) {
    let filteredData = [...this.data];

    if (this.dateFrom && this.dateTo) {
      filteredData = filteredData.filter(item => item.date >= this.dateFrom && item.date <= this.dateTo);
    }

    if (this.searchWord) {
      filteredData = filteredData.filter(item => item.type.toLowerCase().includes(this.searchWord.toLowerCase()));
    }

    this.tableData = filteredData;
    this.total=0;
    this.calcTotal();
  }

}
