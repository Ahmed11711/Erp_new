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
    this.products = [];
    const seen = new Set<number>();
    this.tableData.forEach(elm => {
      const p = elm.product;
      if (p?.id != null && !seen.has(p.id)) {
        seen.add(p.id);
        this.products.push(p);
      }
    });
  }

  productType(e:any){
    this.products = [];
    this.manufacturingService.getAllRecipes().subscribe((result:any)=>{
      this.tableData= result.filter(elm=>elm.product.warehouse == e.target.value);
      this.getProducts();
    })
  }

  productChange(event: { id?: number }) {
    const productId = event?.id;
    if (productId == null) {
      return;
    }
    this.manufacturingService.getAllRecipes().subscribe((result) => {
      this.tableData = result.filter(
        elm => Number(elm.product_id) === Number(productId)
      );
    });
  }

  resetProductFilter() {
    this.getData();
  }

}
