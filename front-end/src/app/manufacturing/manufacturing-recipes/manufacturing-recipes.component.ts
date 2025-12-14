import { Component, OnInit } from '@angular/core';
import { ManufacturingService } from '../services/manufacturing.service';

@Component({
  selector: 'app-manufacturing-recipes',
  templateUrl: './manufacturing-recipes.component.html',
  styleUrls: ['./manufacturing-recipes.component.css']
})
export class ManufacturingRecipesComponent implements OnInit{

  products:any[]=[];
  catword = 'category_name';

  tableData:any[]=[]

  constructor(private manufacturingService:ManufacturingService){}

  ngOnInit(): void {
    this.getData();
  }

  getData(){
    this.manufacturingService.getAllRecipes().subscribe((result:any)=>{
      this.tableData=result;
      this.getProducts();
    });

  }

  getProducts(){
    this.tableData.forEach(elm=>this.products.push(elm.product));
  }

  productType(e:any){
    this.products = [];
    this.manufacturingService.getAllRecipes().subscribe((result:any)=>{
      this.tableData= result.filter(elm=>elm.product.warehouse == e.target.value);
      this.getProducts();
    })
  }

  productChange(event) {
    this.manufacturingService.getAllRecipes().subscribe((result:any)=>{
      this.tableData= result.filter(elm=>elm.id == event.id);
    })

  }

}
