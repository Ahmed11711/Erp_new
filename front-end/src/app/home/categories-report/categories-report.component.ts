import { Component } from '@angular/core';
import { Route, Router } from '@angular/router';
import { AuthService } from 'src/app/auth/auth.service';
import { CategoryService } from 'src/app/categories/services/category.service';
import { ProductionService } from 'src/app/categories/services/production.service';
import { UnitsService } from 'src/app/categories/services/units.service';
import { ExcelService } from 'src/app/excel.service';
import { PdfService } from 'src/app/pdf.service';

@Component({
  selector: 'app-categories-report',
  templateUrl: './categories-report.component.html',
  styleUrls: ['./categories-report.component.css']
})
export class CategoriesReportComponent {
  user!:string;
  productionData:any[]=[];
  url!:string;
  totalOrders:number=0;
  totalNewOrders:number=0;
  totalReturnedOrders:number=0;
  totalPriceNewOrders:number=0;
  totalPriceReturnedOrders:number=0;

  constructor(private categoryService:CategoryService , private production:ProductionService , private route:Router , private authService:AuthService, private pdfService:PdfService,
    private excelService:ExcelService
  ){
    this.url = this.route.url;
    const today = new Date();
    this.dateTo = this.formatDate(today);

    const dateTenDaysAgo = new Date(today);
    dateTenDaysAgo.setDate(today.getDate() - 1);
    this.dateFrom = this.formatDate(dateTenDaysAgo);
    this.dateTo = this.formatDate(dateTenDaysAgo);
  }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    this.getProductionLine();
    this.categoriesSellReports();
  }

  getProductionLine(){
    this.production.getProductions().subscribe((data:any)=>{
      this.productionData=data.filter((item)=>{
        if(item.warehouse=='مخزن منتج تام')
        return item;
      })
    })
  }

  selectProductionLine(e){
    this.param['production_id'] = e.target.value;
    this.categoriesSellReports();
  }

  export(status) {
    let fileName;
    if (this.dateFrom == this.dateTo) {
      fileName = `Report_${this.dateFrom}`;
    } else {
      fileName = `Report_${this.dateFrom}_to_${this.dateTo}`;
    }
    var element = document.getElementById('capture');
    this.pdfService.generatePdf(element, status, fileName)
  }

  exportTableToExcel() {
    let fileName;
    if (this.dateFrom == this.dateTo) {
      fileName = `Report_${this.dateFrom}`;
    } else {
      fileName = `Report_${this.dateFrom}_to_${this.dateTo}`;
    }
    const tableElement:any = document.getElementById('capture');
    this.excelService.generateExcel(fileName, tableElement, 1);
  }

  formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = ('0' + (date.getMonth() + 1)).slice(-2);
    const day = ('0' + date.getDate()).slice(-2);
    return `${year}-${month}-${day}`;
  }

  soldCategories:any[]=[];
  length = 50;
  pageSize = 50;
  page = 0;
  pageSizeOptions = [50 , 100 , 1000];
  param:any = {};
  dateFrom:string='';
  dateTo:string='';
  categoriesSellReports(){
    this.param['date_from'] = this.dateFrom;
    this.param['date_to'] = this.dateTo;
    this.categoryService.categoriesSellReports(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      this.soldCategories=res.data;
      this.totalOrders = this.soldCategories.reduce((sum, elm) => sum + elm.total_orders, 0);
      this.totalNewOrders = this.soldCategories.reduce((sum, elm) => sum + elm.total_quantity_new, 0);
      this.totalReturnedOrders = this.soldCategories.reduce((sum, elm) => sum + elm.total_quantity_return, 0);
      this.totalPriceNewOrders = this.soldCategories.reduce((sum, elm) => sum + elm.total_new, 0);
      this.totalReturnedOrders = this.soldCategories.reduce((sum, elm) => sum + elm.total_postpone, 0);
      this.length=res.total;
      this.pageSize=res.per_page;
    });
  }

  sort(e){
    this.param['sort'] = e;
    this.categoriesSellReports();
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
    this.page = event.pageIndex;
    this.categoriesSellReports();
  }


  onDateFromChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateFrom = target.value;
    console.log(this.dateFrom);
    this.categoriesSellReports();
  }
  onDateToChange(event: Event) {
    const target = event.target as HTMLInputElement;
    this.dateTo = target.value;
    this.categoriesSellReports();
  }

}
