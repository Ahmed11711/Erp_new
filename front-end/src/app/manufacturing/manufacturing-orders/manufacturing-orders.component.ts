import { DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ManufacturingService } from '../services/manufacturing.service';

@Component({
  selector: 'app-manufacturing-orders',
  templateUrl: './manufacturing-orders.component.html',
  styleUrls: ['./manufacturing-orders.component.css']
})
export class ManufacturingOrdersComponent implements OnInit {

  data: any[] = [];
  tableData: any[] = [];

  private _name: string = '';
  status: string = 'حاله التصنيع';
  private _date: any;

  constructor(private datePipe: DatePipe, private manufacturingService: ManufacturingService) {}

  ngOnInit(): void {
    this.getData();
  }

  getData(){
    this.manufacturingService.confirmed().subscribe((result: any) => {
      this.data = result;
      this.tableData = result;
      result.forEach(elm => {
        this.products.push(elm.product);
      });
    });
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
}
