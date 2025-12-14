import { Component, ViewChild } from '@angular/core';
import { NgForm } from '@angular/forms';
import { MatSnackBar } from '@angular/material/snack-bar';
import { SnackBarComponent } from 'src/app/shared/snack-bar/snack-bar.component';
import { CategoryService } from '../services/category.service';
import { ProductionService } from '../services/production.service';
import { UnitsService } from '../services/units.service';
import {map, startWith} from 'rxjs/operators';
import { AuthService } from 'src/app/auth/auth.service';
import { OrderService } from 'src/app/shipping/services/order.service';
import { AssetService } from 'src/app/financial/services/asset.service';
import { StockService } from 'src/app/warehouse/services/stock.service';

@Component({
  selector: 'app-add-category',
  templateUrl: './add-category.component.html',
  styleUrls: ['./add-category.component.css']
})
export class AddCategoryComponent {
user!:string;
productionData:any;
stockData:any=[];
unitsData:any;
warehouse:string='';
imgtext:string = "اختر صورة";
errorMessage!:any;
fileopend:boolean=false;

@ViewChild('addCat', {static: false}) addCat!: NgForm;
  constructor(private _snackBar:MatSnackBar , private production:ProductionService, private units:UnitsService, private StockService:StockService,
    private category:CategoryService, private authService:AuthService, private orderService:OrderService) { }

  ngOnInit() {
    this.user = this.authService.getUser();
    this.category.allCategories().subscribe((result:any)=>this.products = result);
    this.getStockData();

  }

  getStockData(){
    this.StockService.list().subscribe(res=>{
      this.stockData = res.data;
      console.log(this.stockData);

    })
  }

  products:any[]=[];
  catword:any="category_name";
  category_name!:any;
  productChange(event) {

  }
  resetInp(){
    this.category_name = undefined;
  }

  openFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
      this.fileopend=true;
    }
  }
  selectedFile: any;
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.imgtext = this.selectedFile?.name || 'No image selected';
    console.log(this.selectedFile);
  }


  addCategory(data:any){
    const formData = new FormData();

    // // Append the category data to FormData
    formData.append('category_name', this.category_name);
    formData.append('category_price', data.value.price);
    formData.append('initial_balance', data.value.inital_price);
    formData.append('minimum_quantity', data.value.min_quantity);
    formData.append('warehouse', data.value.warehouse);
    formData.append('measurement_id', data.value.unit);
    formData.append('production_id', data.value.production);
    formData.append('stock_id', this.stockData.find(elm=>elm.name == data.value.warehouse).id);

    // Append the image file to FormData
    if (this.selectedFile) {
      formData.append('category_image', this.selectedFile, this.selectedFile.name);
    }
    console.log(formData);
      this.category.addCategory(formData).subscribe((data)=>{
        console.log(data);

        this.errorMessage = null;
        this.clr();
        this.showmsg();
      } ,(error)=>{
        console.log(error.error.message);
        this.errorMessage = error.error.message;

      }
    )
  }

  durationInSeconds = 2;
  showmsg(){
  const snackBarRef = this._snackBar.openFromComponent(SnackBarComponent, {
      duration: this.durationInSeconds * 1000,
    });
    snackBarRef.instance.message = 'تم اضافة الصنف بنجاح  ';
  }
  getProduction(){
     this.production.getProductions().subscribe((data:any)=>{
      // this.productionData = data;
      this.productionData=data.filter((item)=>{
        if(item.warehouse==this.warehouse)
        return item;
      })
      console.log(this.productionData)
    })
  }

  getUnits(){
    this.units.getUnits().subscribe((data:any)=>{
      this.unitsData=data.filter((item)=>{
        if(item.warehouse==this.warehouse)
        return item;
      })
    })
 }

  onWarehouseChange(event:any){
    this.warehouse = event.target.value;
    this.getProduction();
    this.getUnits();
// console.log(event.target.value);
  }


  clr(){
    this.imgtext="اختر صورة";
    this.fileopend=false;
    this.addCat.resetForm();
  }
}
