import { Component, ViewChild } from '@angular/core';
import { FormControl, FormGroup, NgForm, Validators } from '@angular/forms';
import { MatSnackBar } from '@angular/material/snack-bar';
import { AuthService } from 'src/app/auth/auth.service';
import { SnackBarComponent } from 'src/app/shared/snack-bar/snack-bar.component';
import { CategoryService } from '../services/category.service';
import { ProductionService } from '../services/production.service';
import { UnitsService } from '../services/units.service';
import { ActivatedRoute, Router } from '@angular/router';
import { environment } from 'src/env/env';
import { StockService } from 'src/app/warehouse/services/stock.service';

@Component({
  selector: 'app-edit-category',
  templateUrl: './edit-category.component.html',
  styleUrls: ['./edit-category.component.css']
})
export class EditCategoryComponent {
  user!:string;
  productionData:any;
  unitsData:any;
  warehouse:string='';
  stockData:any=[];
  imgtext:string="صورة "
  fileopend:boolean=false;
  price:number=0;
  imgUrl!:string;
  id!:any;

  @ViewChild('addCat', {static: false}) addCat!: NgForm;
    constructor(private _snackBar:MatSnackBar , private production:ProductionService, private units:UnitsService, private category:CategoryService,private StockService:StockService, private authService:AuthService, private route:ActivatedRoute , private router:Router) {
      this.imgUrl = environment.imgUrl;
    }

  ngOnInit() {
    this.user = this.authService.getUser();
    this.getStockData();

    this.id = this.route.snapshot.paramMap.get('id');

    this.category.getCategoryById(this.id).subscribe((res:any)=>{
      this.warehouse = res.warehouse;
      this.getProduction();
      this.getUnits();
      this.form.patchValue({
        category_name:res.category_name,
        category_price:res.category_price,
        initial_balance:res.initial_balance,
        minimum_quantity:res.minimum_quantity,
        warehouse:res.warehouse,
        measurement_id:res.measurement_id,
        production_id:res.production_id,
      })
    });

  }

  getStockData(){
    this.StockService.list().subscribe(res=>{
      this.stockData = res.data;
      console.log(this.stockData);

    })
  }

  form:FormGroup = new FormGroup({
    'category_name' :new FormControl(null, [Validators.required ]),
    'category_price' :new FormControl(null, [Validators.required ]),
    'initial_balance' :new FormControl(null, [Validators.required ] ),
    'minimum_quantity' :new FormControl(null, [Validators.required ] ),
    'warehouse' :new FormControl(null, [Validators.required ] ),
    'measurement_id' :new FormControl(null, [Validators.required ] ),
    'production_id' :new FormControl(null , [Validators.required ]),
    'category_image' :new FormControl(null),
  })

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
  }


  submitform(){
    let data = this.form.value
    const formData = new FormData();

    console.log(this.stockData.find(elm=>elm.name == data.warehouse));
    formData.append('category_name', data.category_name);
    formData.append('category_price', data.category_price);
    formData.append('initial_balance', data.initial_balance);
    formData.append('minimum_quantity', data.minimum_quantity);
    formData.append('warehouse', data.warehouse);
    formData.append('measurement_id', data.measurement_id);
    formData.append('production_id', data.production_id);
    formData.append('stock_id', this.stockData.find(elm=>elm.name == data.warehouse).id);


    if (this.selectedFile) {
      formData.append('category_image', this.selectedFile, this.selectedFile.name);
    }


    this.category.editCategory(this.id,formData).subscribe((res)=>{
      if (res) {
        this.showmsg();
        this.router.navigate(['dashboard/categories/all_categories']);
      }
    })
  }

  durationInSeconds = 2;
  showmsg(){
    const snackBarRef = this._snackBar.openFromComponent(SnackBarComponent, {
        duration: this.durationInSeconds * 1000,
      });
    snackBarRef.instance.message = 'تم تعديل الصنف بنجاح  ';
  }

  getProduction(){
    this.production.getProductions().subscribe((data:any)=>{
      this.productionData=data.filter((item)=>{
        if(item.warehouse==this.warehouse)
        return item;
      })
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
  }


}
