import { Component } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { CategoryService } from 'src/app/categories/services/category.service';

@Component({
  selector: 'app-cat-details',
  templateUrl: './cat-details.component.html',
  styleUrls: ['./cat-details.component.css']
})
export class CatDetailsComponent {
  id!:any;
  productsData:any = [];
  name:string='';
  type!:string;

  length = 50;
  pageSize = 15;
  page = 0;

  pageSizeOptions = [15,50];

constructor(private cat:CategoryService, private route:ActivatedRoute){}


  ngOnInit(){
    this.id = this.route.snapshot.paramMap.get('id');
    this.getData();
  }

  getData(){
    this.cat.categories_details(this.id , this.pageSize,this.page+1 , this.param).subscribe((res:any)=>{
      console.log(res);

      this.length=res.details.total;
      this.pageSize=res.details.per_page;

      this.productsData = res.details.data;
      this.name = res.name
      this.type = this.productsData[0].type;

    })
  }

  param = {};
  search(event :any){
    if (event) {
      this.param['ref']=event.target.value;
    }
    this.getData();
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
      this.page = event.pageIndex;
      this.getData();
  }
}
