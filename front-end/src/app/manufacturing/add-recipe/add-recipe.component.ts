import { Component, OnInit } from '@angular/core';
import { CategoryService } from 'src/app/categories/services/category.service';
import { ManufacturingService } from '../services/manufacturing.service';
import { Router } from '@angular/router';
import { environment } from 'src/env/env';

@Component({
  selector: 'app-add-recipe',
  templateUrl: './add-recipe.component.html',
  styleUrls: ['./add-recipe.component.css']
})
export class AddRecipeComponent implements OnInit{
  imgUrl!: string;

  constructor(private category:CategoryService , private manufacturingService:ManufacturingService , private route:Router){
    this.imgUrl = environment.imgUrl;
  }

  ngOnInit(): void {

  }

  products:any[]=[];
  catword = 'category_name';


  recipes:any[]=[];
  catword2 = 'category_name';

  tableData:any[]=[];

  productType(event){
    this.tableData = [];
    this.products = [];
    this.recipes = [];
    this.totalPrice=0;
    this.category.getCatBywarehouse(event.target.value).subscribe((result:any)=>{
      this.products = result
      if (event.target.value === 'مخزن منتج تحت التشغيل') {
        this.category.getCatBywarehouse('مخزن مواد خام').subscribe((result:any)=>{
          result.map(elm=>{
            elm.quantity = 1
            elm.total_price = elm.quantity * elm.category_price
          });
          this.recipes = result;
        });
      } else if(event.target.value === 'مخزن منتج تام'){
        this.category.getCatBywarehouse('مخزن مواد خام').subscribe((result:any)=>{
          result.map(elm=>{
            elm.quantity = 1
            elm.total_price = elm.quantity * elm.category_price
          });
          this.recipes = result;
        });
        this.category.getCatBywarehouse('مخزن منتج تحت التشغيل').subscribe((result:any)=>{
          result.map(elm=>{
            elm.quantity = 1
            elm.total_price = elm.quantity * elm.category_price
          });
          this.recipes.push(...result)
        });
      }
    })
  }

  productChange(event:any) {
    this.product_id = event.id;
    this.tableData = [];
  }

  recipesChange(event:any) {
    const foundElement = this.tableData.find(elm => elm.id === event.id);
    if (!foundElement) {
      this.tableData.push(event);
    }
    this.calcTotalPrice();
  }

  quantityChange(e:any , i:number){
    this.tableData.forEach((elm , index)=>{
      if (index===i) {
        elm.quantity = Number(e.target.value);
        elm.total_price = elm.quantity * elm.category_price
      }
    })
    this.calcTotalPrice();
  }

  totalPrice:number=0;
  calcTotalPrice(){
    this.totalPrice = 0;
    this.tableData.forEach(elm=>{
      this.totalPrice += elm.total_price;
    })
    if (this.changedPrice !== 0) {
      this.totalPrice += this.changedPrice;
    }
  }

  changedPrice:number=0;
  showChangedPrice:boolean=false;
  priceType(e:any){
    if (e.target.value === 'متغير') {
      this.showChangedPrice= true;
    } else {
      this.changedPrice=0;
      this.showChangedPrice= false;
    }
  }
  // data for backend
  product_id!:number;
  confirmOrder(){
    if (this.product_id && this.tableData.length !==0) {
      const products = this.tableData.map(elm=>{
        return {id:elm.id , quantity:elm.quantity , total_price:elm.total_price}
      })
      const data = {product_id:this.product_id , total:this.totalPrice , products}
      this.manufacturingService.addRecipe(data).subscribe(result=>{
        if (result === "success") {
          this.route.navigate(['/dashboard/manufacturing/recipes']);
        }
      })
    }
  }
  //end
}
