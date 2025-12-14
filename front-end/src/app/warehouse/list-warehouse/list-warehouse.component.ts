import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { CategoryService } from 'src/app/categories/services/category.service';
import { StockService } from '../services/stock.service';
import { MatDialog } from '@angular/material/dialog';
import { DialogComponent } from '../dialog/dialog.component';

@Component({
  selector: 'app-list-warehouse',
  templateUrl: './list-warehouse.component.html',
  styleUrls: ['./list-warehouse.component.css']
})
export class ListWarehouseComponent {

  data!:any;
  url!:string;

  constructor(private cat:CategoryService,private matDialog:MatDialog ,private route:Router, private stockService:StockService){}

  ngOnInit(){
    const currentUrl = this.route.url;
    const lastIndex = currentUrl.lastIndexOf('/');
    this.url = currentUrl.slice(lastIndex + 1);
    this.getData();

    // this.cat.warehousebalance().subscribe((res:any)=>{
    //   this.data=res;
    // })
  }

  getData(){
    this.stockService.list().subscribe(res=>{
      this.data = res.data
      console.log(res.data);

    })
  }

openDialog(data = {}) {
    const dialogRef = this.matDialog.open(DialogComponent, {
      data
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.getData();
      }
    });
  }

  deleteWarehouse(id:number){
    this.stockService.delete(id).subscribe(res=>{
      if (res) {
        this.getData();
      }
    })
  }


}
